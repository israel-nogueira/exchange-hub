<?php

namespace Exchanges\Exchanges\Fake;

use Exchanges\Contracts\ExchangeInterface;
use Exchanges\DTOs\{TickerDTO, OrderBookDTO, OrderDTO, TradeDTO, BalanceDTO, CandleDTO, DepositDTO, WithdrawDTO, ExchangeInfoDTO};
use Exchanges\Exceptions\{InsufficientBalanceException, InvalidSymbolException, OrderNotFoundException, InvalidOrderException, WithdrawException};
use Exchanges\Storage\JsonStorage;

class FakeExchange implements ExchangeInterface
{
    private FakeConfig      $config;
    private JsonStorage     $storage;
    private FakePriceEngine $engine;
    private FakeOrderMatcher $matcher;
    private FakeNormalizer  $normalizer;
    private FakeLogs        $logs;

    public function __construct(array $options = [])
    {
        $this->config     = FakeConfig::fromArray($options['fake'] ?? $options);
        $dataPath         = $options['data_path'] ?? __DIR__ . '/data';
        $this->config->dataPath = $dataPath;

        $this->storage    = new JsonStorage($dataPath);
        $this->engine     = new FakePriceEngine($this->storage, $this->config);
        $this->matcher    = new FakeOrderMatcher($this->storage, $this->engine, $this->config);
        $this->normalizer = new FakeNormalizer();
        $this->logs       = new FakeLogs($dataPath);

        $this->bootStorage();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MARKET DATA
    // ─────────────────────────────────────────────────────────────────────────

    public function ping(): bool
    {
        $this->logs->info('ping');
        return true;
    }

    public function getServerTime(): int
    {
        return time() * 1000;
    }

    public function getExchangeInfo(): ExchangeInfoDTO
    {
        $symbols = $this->getSymbols();
        return new ExchangeInfoDTO(
            exchangeName: $this->config->exchangeName,
            status:       'ONLINE',
            symbols:      $symbols,
            makerFee:     $this->config->makerFee,
            takerFee:     $this->config->takerFee,
            rateLimits:   [['type' => 'REQUESTS', 'limit' => 999999, 'interval' => '1m']],
            networks:     $this->config->depositNetworks,
            timestamp:    time() * 1000,
        );
    }

    public function getSymbols(): array
    {
        $data = $this->storage->read('market/symbols') ?? [];
        return array_keys($data);
    }

    public function getTicker(string $symbol): TickerDTO
    {
        $this->assertValidSymbol($symbol);
        $this->matcher->checkAndExecute($symbol);
        $ticker = $this->engine->getTicker($symbol);
        $this->logs->info('getTicker', ['symbol' => $symbol], $ticker);
        return $this->normalizer->ticker($ticker);
    }

    public function getTicker24h(string $symbol): TickerDTO
    {
        return $this->getTicker($symbol);
    }

    public function getAllTickers(): array
    {
        $symbols = $this->getSymbols();
        $result  = [];
        foreach ($symbols as $symbol) {
            $result[$symbol] = $this->getTicker($symbol);
        }
        return $result;
    }

    public function getOrderBook(string $symbol, int $limit = 20): OrderBookDTO
    {
        $this->assertValidSymbol($symbol);
        $book = $this->engine->getOrderBook($symbol, $limit);
        return $this->normalizer->orderBook($book);
    }

    public function getRecentTrades(string $symbol, int $limit = 50): array
    {
        $this->assertValidSymbol($symbol);
        $trades = $this->storage->filter(
            'market/trades',
            fn($t) => $t['symbol'] === $symbol
        );
        $trades = array_slice(array_reverse($trades), 0, $limit);
        return array_map(fn($t) => $this->normalizer->trade($t), $trades);
    }

    public function getHistoricalTrades(string $symbol, int $limit = 100, ?int $fromId = null): array
    {
        return $this->getRecentTrades($symbol, $limit);
    }

    public function getCandles(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $this->assertValidSymbol($symbol);
        $candles = $this->engine->getCandles($symbol, $interval, $limit);
        return array_map(fn($c) => $this->normalizer->candle($symbol, $interval, $c), $candles);
    }

    public function getAvgPrice(string $symbol): float
    {
        return $this->engine->getPrice($symbol);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ACCOUNT
    // ─────────────────────────────────────────────────────────────────────────

    public function getAccountInfo(): array
    {
        return [
            'exchange'        => $this->config->exchangeName,
            'account_type'    => 'SPOT',
            'can_trade'       => true,
            'can_withdraw'    => true,
            'can_deposit'     => true,
            'maker_fee'       => $this->config->makerFee,
            'taker_fee'       => $this->config->takerFee,
            'created_at'      => time() * 1000,
            'is_fake'         => true,
        ];
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
        return [
            'maker' => $this->config->makerFee,
            'taker' => $this->config->takerFee,
        ];
    }

    public function getDepositAddress(string $asset, ?string $network = null): DepositDTO
    {
        $asset    = strtoupper($asset);
        $networks = $this->config->depositNetworks[$asset] ?? [];

        if (empty($networks)) {
            throw new InvalidOrderException("Ativo {$asset} não suporta depósito", 'fake');
        }

        $net     = $network ?? array_key_first($networks);
        $address = $networks[$net] ?? array_values($networks)[0];

        return $this->normalizer->deposit($asset, $address, $net);
    }

    public function getDepositHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $history = $this->storage->read('account/deposit_history') ?? [];
        return array_filter($history, function ($d) use ($asset, $startTime, $endTime) {
            if ($asset && strtoupper($d['asset']) !== strtoupper($asset)) return false;
            if ($startTime && $d['timestamp'] < $startTime) return false;
            if ($endTime && $d['timestamp'] > $endTime)     return false;
            return true;
        });
    }

    public function getWithdrawHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $history = $this->storage->read('account/withdraw_history') ?? [];
        $result  = array_filter($history, function ($w) use ($asset, $startTime, $endTime) {
            if ($asset && strtoupper($w['asset']) !== strtoupper($asset)) return false;
            if ($startTime && $w['timestamp'] < $startTime) return false;
            if ($endTime && $w['timestamp'] > $endTime)     return false;
            return true;
        });
        return array_map(fn($w) => $this->normalizer->withdraw($w), $result);
    }

    public function withdraw(string $asset, string $address, float $amount, ?string $network = null, ?string $memo = null): WithdrawDTO
    {
        $asset    = strtoupper($asset);
        $fee      = $this->config->withdrawFees[$asset] ?? 0;
        $netAmount= $amount - $fee;
        $balances = $this->storage->read('account/balances') ?? [];

        if (($balances[$asset]['free'] ?? 0) < $amount) {
            throw new InsufficientBalanceException($asset, $amount, $balances[$asset]['free'] ?? 0, 'fake');
        }

        if ($netAmount <= 0) {
            throw new WithdrawException("Valor de saque menor que a taxa ({$fee} {$asset})", 'fake');
        }

        // Debita saldo
        $balances[$asset]['free'] -= $amount;
        $this->storage->write('account/balances', $balances);

        // Registra saque
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

    // ─────────────────────────────────────────────────────────────────────────
    // TRADING
    // ─────────────────────────────────────────────────────────────────────────

    public function createOrder(
        string  $symbol,
        string  $side,
        string  $type,
        float   $quantity,
        ?float  $price = null,
        ?float  $stopPrice = null,
        ?string $timeInForce = 'GTC',
        ?string $clientOrderId = null
    ): OrderDTO {
        $this->assertValidSymbol($symbol);
        $side = strtoupper($side);
        $type = strtoupper($type);

        $currentPrice = $this->engine->getPrice($symbol);
        $execPrice    = ($type === 'MARKET') ? $currentPrice : ($price ?? $currentPrice);

        // Valida saldo
        $this->assertSufficientBalance($symbol, $side, $type, $quantity, $execPrice);

        // Reserva saldo (lock)
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

        // Ordem MARKET executa imediatamente
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

        if (!$found) {
            throw new OrderNotFoundException($orderId, 'fake');
        }

        // Devolve saldo reservado
        $this->unlockBalance($symbol, $found['side'], $found['quantity'], $found['price']);

        // Atualiza status
        $found['status']     = 'CANCELLED';
        $found['updated_at'] = time() * 1000;

        // Move para histórico
        $openOrders = array_values(array_filter($openOrders, fn($o) => $o['id'] !== $orderId));
        $this->storage->write('trading/open_orders', $openOrders);
        $this->storage->append('trading/order_history', $found);

        $this->logs->info('cancelOrder', compact('symbol', 'orderId'), $found);
        return $this->normalizer->order($found);
    }

    public function cancelAllOrders(string $symbol): array
    {
        $openOrders = $this->storage->read('trading/open_orders') ?? [];
        $cancelled  = [];

        foreach ($openOrders as $order) {
            if ($order['symbol'] === $symbol) {
                $this->unlockBalance($symbol, $order['side'], $order['quantity'], $order['price']);
                $order['status']     = 'CANCELLED';
                $order['updated_at'] = time() * 1000;
                $this->storage->append('trading/order_history', $order);
                $cancelled[] = $this->normalizer->order($order);
            }
        }

        $remaining = array_values(array_filter($openOrders, fn($o) => $o['symbol'] !== $symbol));
        $this->storage->write('trading/open_orders', $remaining);

        $this->logs->info('cancelAllOrders', ['symbol' => $symbol], count($cancelled) . ' ordens canceladas');
        return $cancelled;
    }

    public function getOrder(string $symbol, string $orderId): OrderDTO
    {
        // Busca em ordens abertas
        $open = $this->storage->findOne('trading/open_orders', 'id', $orderId);
        if ($open) return $this->normalizer->order($open);

        // Busca em histórico
        $hist = $this->storage->findOne('trading/order_history', 'id', $orderId);
        if ($hist) return $this->normalizer->order($hist);

        throw new OrderNotFoundException($orderId, 'fake');
    }

    public function getOpenOrders(?string $symbol = null): array
    {
        $orders = $this->storage->read('trading/open_orders') ?? [];
        if ($symbol) {
            $orders = array_filter($orders, fn($o) => $o['symbol'] === $symbol);
        }
        return array_map(fn($o) => $this->normalizer->order($o), array_values($orders));
    }

    public function getOrderHistory(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $history = $this->storage->filter('trading/order_history', function ($o) use ($symbol, $startTime, $endTime) {
            if ($o['symbol'] !== $symbol)                    return false;
            if ($startTime && $o['created_at'] < $startTime) return false;
            if ($endTime   && $o['created_at'] > $endTime)   return false;
            return true;
        });

        $history = array_slice(array_reverse($history), 0, $limit);
        return array_map(fn($o) => $this->normalizer->order($o), $history);
    }

    public function getMyTrades(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $trades = $this->storage->filter('trading/my_trades', function ($t) use ($symbol, $startTime, $endTime) {
            if ($t['symbol'] !== $symbol)                    return false;
            if ($startTime && $t['timestamp'] < $startTime)  return false;
            if ($endTime   && $t['timestamp'] > $endTime)    return false;
            return true;
        });

        $trades = array_slice(array_reverse($trades), 0, $limit);
        return array_map(fn($t) => $this->normalizer->trade($t), $trades);
    }

    public function editOrder(string $symbol, string $orderId, ?float $price = null, ?float $quantity = null): OrderDTO
    {
        $openOrders = $this->storage->read('trading/open_orders') ?? [];
        $found      = false;

        foreach ($openOrders as &$order) {
            if ($order['id'] === $orderId && $order['symbol'] === $symbol) {
                // Devolve saldo antigo
                $this->unlockBalance($symbol, $order['side'], $order['quantity'], $order['price']);

                if ($price !== null)    $order['price']    = $price;
                if ($quantity !== null) $order['quantity'] = $quantity;
                $order['updated_at'] = time() * 1000;

                // Reserva novo saldo
                $this->lockBalance($symbol, $order['side'], $order['quantity'], $order['price']);

                $found = $order;
                break;
            }
        }
        unset($order);

        if (!$found) {
            throw new OrderNotFoundException($orderId, 'fake');
        }

        $this->storage->write('trading/open_orders', $openOrders);
        $this->logs->info('editOrder', compact('symbol', 'orderId', 'price', 'quantity'), $found);
        return $this->normalizer->order($found);
    }

    public function createOCOOrder(
        string $symbol,
        string $side,
        float  $quantity,
        float  $price,
        float  $stopPrice,
        float  $stopLimitPrice
    ): array {
        $this->assertValidSymbol($symbol);
        $groupId = 'OCO-' . bin2hex(random_bytes(6));
        $now     = time() * 1000;
        $side    = strtoupper($side);

        $this->assertSufficientBalance($symbol, $side, 'LIMIT', $quantity, $price);
        $this->lockBalance($symbol, $side, $quantity, max($price, $stopLimitPrice));

        $limitOrder = [
            'id'             => 'ORD-' . bin2hex(random_bytes(8)),
            'client_order_id'=> 'CLI-' . bin2hex(random_bytes(4)),
            'symbol'         => $symbol,
            'side'           => $side,
            'type'           => 'LIMIT',
            'status'         => 'OPEN',
            'quantity'       => $quantity,
            'executed_qty'   => 0,
            'price'          => $price,
            'avg_price'      => 0,
            'stop_price'     => 0,
            'time_in_force'  => 'GTC',
            'fee'            => 0,
            'fee_asset'      => $this->quoteAsset($symbol),
            'oco_group_id'   => $groupId,
            'stop_triggered' => false,
            'created_at'     => $now,
            'updated_at'     => $now,
        ];

        $stopLimitOrder = array_merge($limitOrder, [
            'id'             => 'ORD-' . bin2hex(random_bytes(8)),
            'type'           => 'STOP_LIMIT',
            'price'          => $stopLimitPrice,
            'stop_price'     => $stopPrice,
        ]);

        $this->storage->append('trading/open_orders', $limitOrder);
        $this->storage->append('trading/open_orders', $stopLimitOrder);

        return [
            'oco_group_id' => $groupId,
            'limit_order'  => $this->normalizer->order($limitOrder),
            'stop_order'   => $this->normalizer->order($stopLimitOrder),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STAKING (bônus FakeExchange)
    // ─────────────────────────────────────────────────────────────────────────

    public function stakeAsset(string $asset, float $amount): array
    {
        $asset    = strtoupper($asset);
        $balances = $this->storage->read('account/balances') ?? [];

        if (($balances[$asset]['free'] ?? 0) < $amount) {
            throw new InsufficientBalanceException($asset, $amount, $balances[$asset]['free'] ?? 0, 'fake');
        }

        $balances[$asset]['free']   -= $amount;
        $balances[$asset]['staked'] = ($balances[$asset]['staked'] ?? 0) + $amount;
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
                $result[] = [
                    'asset'      => $asset,
                    'amount'     => $balance['staked'],
                    'apy'        => '5.00%',
                    'status'     => 'ACTIVE',
                ];
            }
        }
        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS INTERNOS
    // ─────────────────────────────────────────────────────────────────────────

    private function bootStorage(): void
    {
        // Inicializa arquivos se não existirem
        if (!$this->storage->exists('account/balances')) {
            $balances = [];
            foreach ($this->config->initialBalances as $asset => $amount) {
                $balances[$asset] = ['free' => $amount, 'locked' => 0, 'staked' => 0];
            }
            $this->storage->write('account/balances', $balances);
        }

        if (!$this->storage->exists('trading/open_orders'))     $this->storage->write('trading/open_orders', []);
        if (!$this->storage->exists('trading/order_history'))   $this->storage->write('trading/order_history', []);
        if (!$this->storage->exists('trading/my_trades'))       $this->storage->write('trading/my_trades', []);
        if (!$this->storage->exists('market/trades'))           $this->storage->write('market/trades', []);
        if (!$this->storage->exists('account/deposit_history')) $this->storage->write('account/deposit_history', []);
        if (!$this->storage->exists('account/withdraw_history'))$this->storage->write('account/withdraw_history', []);

        if (!$this->storage->exists('market/symbols')) {
            $symbols = [];
            foreach (array_keys($this->config->basePrices) as $symbol) {
                $symbols[$symbol] = ['status' => 'TRADING', 'base' => substr($symbol, 0, -4), 'quote' => substr($symbol, -4)];
            }
            $this->storage->write('market/symbols', $symbols);
        }
    }

    private function assertValidSymbol(string $symbol): void
    {
        $symbols = $this->storage->read('market/symbols') ?? [];
        if (!isset($symbols[$symbol])) {
            throw new InvalidSymbolException($symbol, 'fake');
        }
    }

    private function assertSufficientBalance(string $symbol, string $side, string $type, float $quantity, float $price): void
    {
        $balances = $this->storage->read('account/balances') ?? [];
        [$base, $quote] = $this->splitSymbol($symbol);

        if ($side === 'BUY') {
            $needed = $quantity * $price * (1 + $this->config->takerFee);
            $avail  = $balances[$quote]['free'] ?? 0;
            if ($avail < $needed) {
                throw new InsufficientBalanceException($quote, $needed, $avail, 'fake');
            }
        } else {
            $avail = $balances[$base]['free'] ?? 0;
            if ($avail < $quantity) {
                throw new InsufficientBalanceException($base, $quantity, $avail, 'fake');
            }
        }
    }

    private function lockBalance(string $symbol, string $side, float $quantity, float $price): void
    {
        $balances = $this->storage->read('account/balances') ?? [];
        [$base, $quote] = $this->splitSymbol($symbol);

        if ($side === 'BUY') {
            $cost = $quantity * $price;
            $balances[$quote]['free']   = max(0, ($balances[$quote]['free']   ?? 0) - $cost);
            $balances[$quote]['locked'] = ($balances[$quote]['locked'] ?? 0) + $cost;
        } else {
            $balances[$base]['free']   = max(0, ($balances[$base]['free']   ?? 0) - $quantity);
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
            $balances[$quote]['free']   = ($balances[$quote]['free']   ?? 0) + $cost;
        } else {
            $balances[$base]['locked'] = max(0, ($balances[$base]['locked'] ?? 0) - $quantity);
            $balances[$base]['free']   = ($balances[$base]['free']   ?? 0) + $quantity;
        }

        $this->storage->write('account/balances', $balances);
    }

    private function splitSymbol(string $symbol): array
    {
        $quotes = ['USDT', 'USDC', 'BRL', 'BUSD', 'BTC', 'ETH', 'BNB'];
        foreach ($quotes as $q) {
            if (str_ends_with($symbol, $q)) {
                return [str_replace($q, '', $symbol), $q];
            }
        }
        return [substr($symbol, 0, -4), substr($symbol, -4)];
    }

    private function quoteAsset(string $symbol): string
    {
        return $this->splitSymbol($symbol)[1];
    }
}
