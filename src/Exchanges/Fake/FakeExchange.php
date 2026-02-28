<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Fake;

use IsraelNogueira\ExchangeHub\Contracts\ExchangeInterface;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};
use IsraelNogueira\ExchangeHub\Exceptions\{InsufficientBalanceException,InvalidSymbolException,OrderNotFoundException,InvalidOrderException,WithdrawException};
use IsraelNogueira\ExchangeHub\Storage\JsonStorage;

class FakeExchange implements ExchangeInterface
{
    private FakeConfig       $config;
    private JsonStorage      $storage;
    private FakePriceEngine  $engine;
    private FakeOrderMatcher $matcher;
    private FakeNormalizer   $normalizer;
    private FakeLogs         $logs;

    public function __construct(array $options = [])
    {
        $this->config         = FakeConfig::fromArray($options['fake'] ?? $options);
        $dataPath             = $options['data_path'] ?? __DIR__ . '/data';
        $this->config->dataPath = $dataPath;

        $this->storage    = new JsonStorage($dataPath);
        $this->engine     = new FakePriceEngine($this->storage, $this->config);
        $this->matcher    = new FakeOrderMatcher($this->storage, $this->engine, $this->config);
        $this->normalizer = new FakeNormalizer();
        $this->logs       = new FakeLogs($dataPath);

        $this->bootStorage();
    }

    private function bootStorage(): void
    {
        if (!$this->storage->read('account/balances')) {
            $balances = [];
            foreach ($this->config->initialBalances as $asset => $amount) {
                $balances[$asset] = ['free' => $amount, 'locked' => 0, 'staked' => 0];
            }
            $this->storage->write('account/balances', $balances);
        }

        if (!$this->storage->read('market/symbols')) {
            $this->storage->write('market/symbols', array_keys($this->config->basePrices));
        }

        foreach (['trading/open_orders','trading/order_history','trading/trade_history','account/deposit_history','account/withdraw_history'] as $key) {
            if (!$this->storage->read($key)) {
                $this->storage->write($key, []);
            }
        }
    }

    // ── Market Data ───────────────────────────────────────────────────────────

    public function ping(): bool
    {
        $this->logs->info('ping');
        return true;
    }

    public function getServerTime(): int { return time() * 1000; }

    public function getExchangeInfo(): ExchangeInfoDTO
    {
        return new ExchangeInfoDTO(
            exchangeName: $this->config->exchangeName,
            status:       'ONLINE',
            symbols:      $this->getSymbols(),
            makerFee:     $this->config->makerFee,
            takerFee:     $this->config->takerFee,
            rateLimits:   [['type' => 'REQUESTS', 'limit' => 999999, 'interval' => '1m']],
            networks:     $this->config->depositNetworks,
            timestamp:    time() * 1000,
        );
    }

    public function getSymbols(): array
    {
        return $this->storage->read('market/symbols') ?? array_keys($this->config->basePrices);
    }

    public function getTicker(string $symbol): TickerDTO
    {
        $this->assertValidSymbol($symbol);
        $this->matcher->checkAndExecute($symbol);
        $data = $this->engine->getTicker($symbol);
        $data['symbol'] = $symbol;
        return $this->normalizer->ticker($data);
    }

    public function getTicker24h(string $symbol): TickerDTO { return $this->getTicker($symbol); }

    public function getAllTickers(): array
    {
        return array_map(fn($s) => $this->getTicker($s), $this->getSymbols());
    }

    public function getOrderBook(string $symbol, int $limit = 20): OrderBookDTO
    {
        $this->assertValidSymbol($symbol);
        $book = $this->engine->getOrderBook($symbol, min($limit, $this->config->orderBookDepth));
        return $this->normalizer->orderBook($book);
    }

    public function getRecentTrades(string $symbol, int $limit = 50): array
    {
        $this->assertValidSymbol($symbol);
        $trades = $this->storage->read('trading/trade_history') ?? [];
        $filtered = array_filter($trades, fn($t) => $t['symbol'] === $symbol);
        $filtered = array_slice(array_reverse(array_values($filtered)), 0, $limit);
        return array_map(fn($t) => $this->normalizer->trade($t), $filtered);
    }

    public function getHistoricalTrades(string $symbol, int $limit = 100, ?int $fromId = null): array
    {
        return $this->getRecentTrades($symbol, $limit);
    }

    public function getCandles(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $this->assertValidSymbol($symbol);
        $key     = "market/candles_{$symbol}_{$interval}";
        $candles = $this->storage->read($key);
        if (!$candles) {
            $candles = $this->engine->generateCandles($symbol, $interval, max($limit, 200));
            $this->storage->write($key, $candles);
        }
        return array_map(fn($c) => $this->normalizer->candle($symbol, $interval, $c), array_slice($candles, -$limit));
    }

    public function getAvgPrice(string $symbol): float
    {
        $ticker = $this->getTicker($symbol);
        return ($ticker->bid + $ticker->ask) / 2;
    }

    // ── Account ───────────────────────────────────────────────────────────────

    public function getAccountInfo(): array
    {
        return ['exchange' => 'fake', 'status' => 'ACTIVE', 'balances' => $this->storage->read('account/balances') ?? []];
    }

    public function getBalances(): array
    {
        $data   = $this->storage->read('account/balances') ?? [];
        $result = [];
        foreach ($data as $asset => $balance) {
            if ($balance['free'] > 0 || $balance['locked'] > 0 || $balance['staked'] > 0) {
                $result[$asset] = $this->normalizer->balance($asset, $balance);
            }
        }
        return $result;
    }

    public function getBalance(string $asset): BalanceDTO
    {
        $data    = $this->storage->read('account/balances') ?? [];
        $balance = $data[strtoupper($asset)] ?? ['free' => 0, 'locked' => 0, 'staked' => 0];
        return $this->normalizer->balance(strtoupper($asset), $balance);
    }

    public function getCommissionRates(): array
    {
        return ['maker' => $this->config->makerFee, 'taker' => $this->config->takerFee];
    }

    public function getDepositAddress(string $asset, ?string $network = null): DepositDTO
    {
        $asset    = strtoupper($asset);
        $networks = $this->config->depositNetworks[$asset] ?? [];
        if (empty($networks)) throw new InvalidOrderException("Ativo {$asset} não suporta depósito", 'fake');
        $net     = $network ?? array_key_first($networks);
        $address = $networks[$net] ?? array_values($networks)[0];
        return $this->normalizer->deposit($asset, $address, $net);
    }

    public function getDepositHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $history  = $this->storage->read('account/deposit_history') ?? [];
        return array_values(array_filter($history, function ($d) use ($asset, $startTime, $endTime) {
            if ($asset && strtoupper($d['asset'] ?? '') !== strtoupper($asset)) return false;
            if ($startTime && $d['timestamp'] < $startTime) return false;
            if ($endTime && $d['timestamp'] > $endTime)     return false;
            return true;
        }));
    }

    public function getWithdrawHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $history = $this->storage->read('account/withdraw_history') ?? [];
        $result  = array_values(array_filter($history, function ($w) use ($asset, $startTime, $endTime) {
            if ($asset && strtoupper($w['asset'] ?? '') !== strtoupper($asset)) return false;
            if ($startTime && $w['timestamp'] < $startTime) return false;
            if ($endTime && $w['timestamp'] > $endTime)     return false;
            return true;
        }));
        return array_map(fn($w) => $this->normalizer->withdraw($w), $result);
    }

    public function withdraw(string $asset, string $address, float $amount, ?string $network = null, ?string $memo = null): WithdrawDTO
    {
        $asset     = strtoupper($asset);
        $fee       = $this->config->withdrawFees[$asset] ?? 0;
        $netAmount = $amount - $fee;
        $balances  = $this->storage->read('account/balances') ?? [];

        if (($balances[$asset]['free'] ?? 0) < $amount) {
            throw new InsufficientBalanceException($asset, $amount, $balances[$asset]['free'] ?? 0, 'fake');
        }
        if ($netAmount <= 0) {
            throw new WithdrawException("Valor de saque menor que a taxa ({$fee} {$asset})", 'fake');
        }

        $balances[$asset]['free'] -= $amount;
        $this->storage->write('account/balances', $balances);

        $withdraw = [
            'id'         => 'WD-' . bin2hex(random_bytes(8)),
            'asset'      => $asset,
            'address'    => $address,
            'memo'       => $memo,
            'network'    => $network ?? array_key_first($this->config->depositNetworks[$asset] ?? ['MAIN' => '']),
            'amount'     => $amount,
            'fee'        => $fee,
            'net_amount' => $netAmount,
            'tx_id'      => 'FAKETX' . strtoupper(bin2hex(random_bytes(16))),
            'status'     => WithdrawDTO::STATUS_CONFIRMED,
            'timestamp'  => time() * 1000,
        ];
        $this->storage->append('account/withdraw_history', $withdraw);
        $this->logs->info('withdraw', compact('asset', 'amount', 'address'), $withdraw);

        return $this->normalizer->withdraw($withdraw);
    }

    // ── Trading ───────────────────────────────────────────────────────────────

    public function createOrder(string $symbol, string $side, string $type, float $quantity, ?float $price = null, ?float $stopPrice = null, ?string $timeInForce = 'GTC', ?string $clientOrderId = null): OrderDTO
    {
        $this->assertValidSymbol($symbol);
        $side         = strtoupper($side);
        $type         = strtoupper($type);
        $currentPrice = $this->engine->getPrice($symbol);
        $execPrice    = ($type === 'MARKET') ? $currentPrice : ($price ?? $currentPrice);

        $this->assertSufficientBalance($symbol, $side, $type, $quantity, $execPrice);
        $this->lockBalance($symbol, $side, $quantity, $execPrice);

        $orderId = 'ORD-' . bin2hex(random_bytes(8));
        $now     = time() * 1000;

        $order = [
            'id'              => $orderId,
            'client_order_id' => $clientOrderId ?? 'CLI-' . bin2hex(random_bytes(4)),
            'symbol'          => $symbol,
            'side'            => $side,
            'type'            => $type,
            'status'          => 'OPEN',
            'quantity'        => $quantity,
            'executed_qty'    => 0,
            'price'           => $price ?? 0,
            'avg_price'       => 0,
            'stop_price'      => $stopPrice ?? 0,
            'time_in_force'   => $timeInForce ?? 'GTC',
            'fee'             => 0,
            'fee_asset'       => $this->quoteAsset($symbol),
            'oco_group_id'    => null,
            'stop_triggered'  => false,
            'created_at'      => $now,
            'updated_at'      => $now,
        ];

        if ($type === 'MARKET') {
            $this->matcher->execute($order, $currentPrice);
        } else {
            $this->storage->append('trading/open_orders', $order);
        }

        $this->logs->info('createOrder', compact('symbol', 'side', 'type', 'quantity', 'price'), $order);
        return $this->normalizer->order($order);
    }

    public function cancelOrder(string $symbol, string $orderId): OrderDTO
    {
        $openOrders = $this->storage->read('trading/open_orders') ?? [];
        $found      = null;
        foreach ($openOrders as $order) {
            if ($order['id'] === $orderId && $order['symbol'] === $symbol) {
                $found = $order;
                break;
            }
        }
        if (!$found) throw new OrderNotFoundException($orderId, 'fake');

        $this->unlockBalance($symbol, $found['side'], $found['quantity'], $found['price']);

        $found['status']     = 'CANCELLED';
        $found['updated_at'] = time() * 1000;

        $openOrders = array_values(array_filter($openOrders, fn($o) => $o['id'] !== $orderId));
        $this->storage->write('trading/open_orders', $openOrders);
        $this->storage->append('trading/order_history', $found);

        return $this->normalizer->order($found);
    }

    public function cancelAllOrders(string $symbol): array
    {
        $openOrders = $this->storage->read('trading/open_orders') ?? [];
        $cancelled  = [];
        foreach ($openOrders as $order) {
            if ($order['symbol'] === $symbol) {
                $cancelled[] = $this->cancelOrder($symbol, $order['id']);
            }
        }
        return $cancelled;
    }

    public function getOrder(string $symbol, string $orderId): OrderDTO
    {
        $open = $this->storage->read('trading/open_orders') ?? [];
        foreach ($open as $o) {
            if ($o['id'] === $orderId) return $this->normalizer->order($o);
        }
        $history = $this->storage->read('trading/order_history') ?? [];
        foreach ($history as $o) {
            if ($o['id'] === $orderId) return $this->normalizer->order($o);
        }
        throw new OrderNotFoundException($orderId, 'fake');
    }

    public function getOpenOrders(?string $symbol = null): array
    {
        $orders = $this->storage->read('trading/open_orders') ?? [];
        if ($symbol) $orders = array_filter($orders, fn($o) => $o['symbol'] === $symbol);
        return array_map(fn($o) => $this->normalizer->order($o), array_values($orders));
    }

    public function getOrderHistory(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $history = $this->storage->read('trading/order_history') ?? [];
        $filtered = array_filter($history, fn($o) => $o['symbol'] === $symbol);
        return array_map(fn($o) => $this->normalizer->order($o), array_slice(array_reverse(array_values($filtered)), 0, $limit));
    }

    public function getMyTrades(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        return $this->getRecentTrades($symbol, $limit);
    }

    public function editOrder(string $symbol, string $orderId, ?float $price = null, ?float $quantity = null): OrderDTO
    {
        $original = $this->getOrder($symbol, $orderId);
        $this->cancelOrder($symbol, $orderId);
        return $this->createOrder($symbol, $original->side, $original->type, $quantity ?? $original->quantity, $price ?? $original->price);
    }

    public function createOCOOrder(string $symbol, string $side, float $quantity, float $price, float $stopPrice, float $stopLimitPrice): array
    {
        $groupId         = 'OCO-' . bin2hex(random_bytes(8));
        $limitOrder      = ['clientOrderId' => $groupId . '-LIMIT',  'oco_group_id' => $groupId, 'type' => 'LIMIT',      'price' => $price];
        $stopLimitOrder  = ['clientOrderId' => $groupId . '-STOP',   'oco_group_id' => $groupId, 'type' => 'STOP_LIMIT', 'price' => $stopLimitPrice, 'stop_price' => $stopPrice];
        $l = $this->createOrder($symbol, $side, 'LIMIT',      $quantity, $price,          null,       'GTC', $groupId . '-LIMIT');
        $s = $this->createOrder($symbol, $side, 'STOP_LIMIT', $quantity, $stopLimitPrice, $stopPrice, 'GTC', $groupId . '-STOP');
        return ['oco_group_id' => $groupId, 'limit_order' => $l, 'stop_order' => $s];
    }

    // ── Staking ───────────────────────────────────────────────────────────────

    public function stakeAsset(string $asset, float $amount): array
    {
        $asset    = strtoupper($asset);
        $balances = $this->storage->read('account/balances') ?? [];
        if (($balances[$asset]['free'] ?? 0) < $amount) {
            throw new InsufficientBalanceException($asset, $amount, $balances[$asset]['free'] ?? 0, 'fake');
        }
        $balances[$asset]['free']   -= $amount;
        $balances[$asset]['staked']  = ($balances[$asset]['staked'] ?? 0) + $amount;
        $this->storage->write('account/balances', $balances);
        return ['asset' => $asset, 'staked' => $amount, 'apy' => '5.00%', 'status' => 'STAKED'];
    }

    public function unstakeAsset(string $asset, float $amount): array
    {
        $asset    = strtoupper($asset);
        $balances = $this->storage->read('account/balances') ?? [];
        if (($balances[$asset]['staked'] ?? 0) < $amount) {
            throw new InsufficientBalanceException($asset, $amount, $balances[$asset]['staked'] ?? 0, 'fake');
        }
        $balances[$asset]['staked'] -= $amount;
        $balances[$asset]['free']   += $amount;
        $this->storage->write('account/balances', $balances);
        return ['asset' => $asset, 'unstaked' => $amount, 'status' => 'UNSTAKED'];
    }

    public function getStakingPositions(): array
    {
        $balances = $this->storage->read('account/balances') ?? [];
        $result   = [];
        foreach ($balances as $asset => $balance) {
            if (($balance['staked'] ?? 0) > 0) {
                $result[] = ['asset' => $asset, 'amount' => $balance['staked'], 'apy' => '5.00%', 'status' => 'STAKED'];
            }
        }
        return $result;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function assertValidSymbol(string $symbol): void
    {
        $symbols = $this->getSymbols();
        if (!in_array($symbol, $symbols)) {
            throw new InvalidSymbolException($symbol, 'fake');
        }
    }

    private function assertSufficientBalance(string $symbol, string $side, string $type, float $quantity, float $price): void
    {
        $balances = $this->storage->read('account/balances') ?? [];
        [$base, $quote] = $this->splitSymbol($symbol);

        if ($side === 'BUY') {
            $required = $type === 'MARKET' ? $quantity * $price * 1.01 : $quantity * $price;
            $available = $balances[$quote]['free'] ?? 0;
            if ($available < $required) {
                throw new InsufficientBalanceException($quote, $required, $available, 'fake');
            }
        } else {
            $available = $balances[$base]['free'] ?? 0;
            if ($available < $quantity) {
                throw new InsufficientBalanceException($base, $quantity, $available, 'fake');
            }
        }
    }

    private function lockBalance(string $symbol, string $side, float $quantity, float $price): void
    {
        $balances = $this->storage->read('account/balances') ?? [];
        [$base, $quote] = $this->splitSymbol($symbol);

        if ($side === 'BUY') {
            $cost = $quantity * $price;
            $balances[$quote]['free']   -= $cost;
            $balances[$quote]['locked'] = ($balances[$quote]['locked'] ?? 0) + $cost;
        } else {
            $balances[$base]['free']   -= $quantity;
            $balances[$base]['locked'] = ($balances[$base]['locked'] ?? 0) + $quantity;
        }
        $this->storage->write('account/balances', $balances);
    }

    private function unlockBalance(string $symbol, string $side, float $quantity, float $price): void
    {
        $balances = $this->storage->read('account/balances') ?? [];
        [$base, $quote] = $this->splitSymbol($symbol);

        if ($side === 'BUY') {
            $cost = $quantity * $price;
            $balances[$quote]['locked'] = max(0, ($balances[$quote]['locked'] ?? 0) - $cost);
            $balances[$quote]['free']  += $cost;
        } else {
            $balances[$base]['locked'] = max(0, ($balances[$base]['locked'] ?? 0) - $quantity);
            $balances[$base]['free']  += $quantity;
        }
        $this->storage->write('account/balances', $balances);
    }

    private function splitSymbol(string $symbol): array
    {
        $fiats = ['USDT','USDC','BRL','BUSD','EUR','USD'];
        foreach ($fiats as $fiat) {
            if (str_ends_with($symbol, $fiat)) {
                return [str_replace($fiat, '', $symbol), $fiat];
            }
        }
        return [substr($symbol, 0, 3), substr($symbol, 3)];
    }

    private function quoteAsset(string $symbol): string
    {
        return $this->splitSymbol($symbol)[1];
    }
}
