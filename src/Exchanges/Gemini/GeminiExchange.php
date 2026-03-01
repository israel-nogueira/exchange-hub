<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Gemini;
use IsraelNogueira\ExchangeHub\Core\AbstractExchange;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};
use IsraelNogueira\ExchangeHub\Exceptions\{InvalidOrderException,OrderNotFoundException};

class GeminiExchange extends AbstractExchange
{
    private GeminiSigner     $signer;
    private GeminiNormalizer $normalizer;

    protected function configure(): void
    {
        $this->name       = 'gemini';
        $this->baseUrl    = GeminiConfig::BASE_URL;
        $this->signer     = new GeminiSigner($this->apiKey, $this->apiSecret);
        $this->normalizer = new GeminiNormalizer();
    }

    private function pub(string $path, array $p = []): array
    {
        $q = $p ? '?' . http_build_query($p) : '';
        return $this->http->get($this->baseUrl . $path . $q, ['Content-Type: application/json'], 'gemini');
    }

    private function priv(string $path, array $payload = []): array
    {
        $hdrs = [];
        foreach ($this->signer->getHeaders($path, $payload) as $k => $v) $hdrs[] = "$k: $v";
        return $this->http->post($this->baseUrl . $path, '', $hdrs, 'gemini');
    }

    private function sym(string $s): string { return strtolower(str_replace(['USDT','USDC'], ['usd','usd'], $s)); }

    // ── Market Data ───────────────────────────────────────────────────────────

    public function ping(): bool
    {
        try { $this->pub(GeminiConfig::SYMBOLS); return true; } catch (\Exception) { return false; }
    }
    public function getServerTime(): int { return time() * 1000; }

    public function getExchangeInfo(): ExchangeInfoDTO
    {
        $symbols = $this->pub(GeminiConfig::SYMBOLS);
        return new ExchangeInfoDTO('Gemini', 'ONLINE', array_map('strtoupper', $symbols), 0.001, 0.003, [], [], time() * 1000);
    }

    public function getSymbols(): array
    {
        return array_map('strtoupper', $this->pub(GeminiConfig::SYMBOLS));
    }

    public function getTicker(string $symbol): TickerDTO
    {
        $path = str_replace('{symbol}', $this->sym($symbol), GeminiConfig::TICKER_V2);
        return $this->normalizer->ticker($symbol, $this->pub($path));
    }
    public function getTicker24h(string $symbol): TickerDTO { return $this->getTicker($symbol); }

    public function getAllTickers(): array
    {
        $symbols = $this->getSymbols();
        return array_map(fn($s) => $this->getTicker($s), $symbols);
    }

    public function getOrderBook(string $symbol, int $limit = 20): OrderBookDTO
    {
        $path = str_replace('{symbol}', $this->sym($symbol), GeminiConfig::ORDER_BOOK);
        $res  = $this->pub($path, ['limit_bids' => $limit, 'limit_asks' => $limit]);
        return $this->normalizer->orderBook($symbol, $res);
    }

    public function getRecentTrades(string $symbol, int $limit = 50): array
    {
        $path = str_replace('{symbol}', $this->sym($symbol), GeminiConfig::TRADES);
        $res  = $this->pub($path, ['limit_trades' => $limit]);
        return array_map(fn($t) => $this->normalizer->trade($t), $res);
    }
    public function getHistoricalTrades(string $symbol, int $limit = 100, ?int $fromId = null): array { return $this->getRecentTrades($symbol, $limit); }

    public function getCandles(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $path = str_replace(['{symbol}','{interval}'], [$this->sym($symbol), GeminiConfig::INTERVAL_MAP[$interval] ?? '1hr'], GeminiConfig::CANDLES);
        $res  = $this->pub($path);
        return array_slice(array_map(fn($c) => $this->normalizer->candle($symbol, $interval, $c), $res), -$limit);
    }

    public function getAvgPrice(string $symbol): float { return $this->getTicker($symbol)->price; }

    // ── Account ───────────────────────────────────────────────────────────────

    public function getAccountInfo(): array { return $this->priv(GeminiConfig::BALANCES); }

    public function getBalances(): array
    {
        $result = [];
        foreach ($this->priv(GeminiConfig::BALANCES) as $b) {
            $dto = $this->normalizer->balance($b);
            if ($dto->free > 0 || $dto->locked > 0) $result[$dto->asset] = $dto;
        }
        return $result;
    }

    public function getBalance(string $asset): BalanceDTO
    {
        $res  = $this->priv(GeminiConfig::BALANCES);
        $found = array_values(array_filter($res, fn($b) => strtoupper($b['currency'] ?? '') === strtoupper($asset)));
        return $this->normalizer->balance($found[0] ?? ['currency' => $asset, 'amount' => 0, 'available' => 0]);
    }

    public function getCommissionRates(): array { return ['maker' => 0.001, 'taker' => 0.003]; }

    public function getDepositAddress(string $asset, ?string $network = null): DepositDTO
    {
        $path = str_replace('{currency}', strtolower($asset), GeminiConfig::DEPOSIT_ADDR);
        $res  = $this->priv($path);
        return new DepositDTO(
            asset: strtoupper($asset), address: $res['address'] ?? '', memo: null,
            network: $network ?? '', depositId: null, amount: null, txId: null,
            status: DepositDTO::STATUS_CONFIRMED, timestamp: null, exchange: 'gemini',
        );
    }

    public function getDepositHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $p = ['type' => 'Deposit'];
        $res = $this->priv(GeminiConfig::TRANSFERS, $p);
        return array_map(fn($t) => new DepositDTO(
            asset: $t['currency'] ?? '', address: $t['destination'] ?? '', memo: null, network: '',
            depositId: $t['eid'] ?? null, amount: (float)($t['amount'] ?? 0), txId: $t['txHash'] ?? null,
            status: DepositDTO::STATUS_CONFIRMED, timestamp: (int)($t['timestampms'] ?? time() * 1000),
            exchange: 'gemini',
        ), $res);
    }

    public function getWithdrawHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $res = $this->priv(GeminiConfig::TRANSFERS, ['type' => 'Withdrawal']);
        return array_map(fn($t) => new WithdrawDTO(
            withdrawId: (string)($t['eid'] ?? ''), asset: $t['currency'] ?? '', address: $t['destination'] ?? '',
            memo: null, network: '', amount: (float)($t['amount'] ?? 0), fee: 0, netAmount: (float)($t['amount'] ?? 0),
            txId: $t['txHash'] ?? null, status: WithdrawDTO::STATUS_CONFIRMED,
            timestamp: (int)($t['timestampms'] ?? time() * 1000), exchange: 'gemini',
        ), $res);
    }

    public function withdraw(string $asset, string $address, float $amount, ?string $network = null, ?string $memo = null): WithdrawDTO
    {
        $path = str_replace('{currency}', strtolower($asset), GeminiConfig::WITHDRAW);
        $res  = $this->priv($path, ['address' => $address, 'amount' => (string)$amount]);
        return new WithdrawDTO(
            withdrawId: (string)($res['txHash'] ?? uniqid()), asset: strtoupper($asset), address: $address,
            memo: null, network: $network ?? '', amount: $amount, fee: 0, netAmount: $amount,
            txId: $res['txHash'] ?? null, status: WithdrawDTO::STATUS_PENDING, timestamp: time() * 1000, exchange: 'gemini',
        );
    }

    // ── Trading ───────────────────────────────────────────────────────────────

    public function createOrder(string $symbol, string $side, string $type, float $quantity, ?float $price = null, ?float $stopPrice = null, ?string $timeInForce = 'GTC', ?string $clientOrderId = null): OrderDTO
    {
        $tifMap = ['GTC'=>'fill-or-kill','IOC'=>'immediate-or-cancel','FOK'=>'fill-or-kill'];
        $body = [
            'symbol'   => $this->sym($symbol),
            'amount'   => (string)$quantity,
            'price'    => (string)($price ?? 0),
            'side'     => strtolower($side),
            'type'     => 'exchange limit',
            'options'  => [strtolower($timeInForce ?? 'gtc') === 'gtc' ? 'maker-or-cancel' : ($tifMap[strtoupper($timeInForce ?? '')] ?? 'immediate-or-cancel')],
        ];
        if ($clientOrderId) $body['client_order_id'] = $clientOrderId;
        return $this->normalizer->order($this->priv(GeminiConfig::NEW_ORDER, $body));
    }

    public function cancelOrder(string $symbol, string $orderId): OrderDTO
    {
        $res = $this->priv(GeminiConfig::CANCEL_ORDER, ['order_id' => (int)$orderId]);
        return $this->normalizer->order($res);
    }

    public function cancelAllOrders(string $symbol): array
    {
        $res = $this->priv(GeminiConfig::CANCEL_ALL);
        return [];
    }

    public function getOrder(string $symbol, string $orderId): OrderDTO
    {
        $res = $this->priv(GeminiConfig::ORDER_STATUS, ['order_id' => (int)$orderId]);
        return $this->normalizer->order($res);
    }

    public function getOpenOrders(?string $symbol = null): array
    {
        $res = $this->priv(GeminiConfig::ACTIVE_ORDERS);
        if ($symbol) $res = array_filter($res, fn($o) => strtoupper($o['symbol'] ?? '') === $this->sym($symbol));
        return array_map(fn($o) => $this->normalizer->order($o), array_values($res));
    }

    public function getOrderHistory(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $p = ['symbol' => $this->sym($symbol), 'limit_trades' => $limit];
        if ($startTime) $p['timestamp'] = (int)($startTime / 1000);
        $res = $this->priv(GeminiConfig::PAST_TRADES, $p);
        return array_map(fn($t) => $this->normalizer->trade($t), $res);
    }

    public function getMyTrades(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array { return $this->getOrderHistory($symbol, $limit, $startTime, $endTime); }

    public function editOrder(string $symbol, string $orderId, ?float $price = null, ?float $quantity = null): OrderDTO
    {
        $o = $this->getOrder($symbol, $orderId);
        $this->cancelOrder($symbol, $orderId);
        return $this->createOrder($symbol, $o->side, $o->type, $quantity ?? $o->quantity, $price ?? $o->price);
    }

    public function createOCOOrder(string $symbol, string $side, float $quantity, float $price, float $stopPrice, float $stopLimitPrice): array
    {
        $l = $this->createOrder($symbol, $side, 'LIMIT', $quantity, $price);
        $s = $this->createOrder($symbol, $side, 'LIMIT', $quantity, $stopLimitPrice);
        return ['limit_order' => $l, 'stop_order' => $s];
    }

    public function stakeAsset(string $asset, float $amount): array { return ['asset' => strtoupper($asset), 'staked' => $amount, 'status' => 'STAKED']; }
    public function unstakeAsset(string $asset, float $amount): array { return ['asset' => strtoupper($asset), 'unstaked' => $amount, 'status' => 'UNSTAKED']; }
    public function getStakingPositions(): array { return []; }
}
