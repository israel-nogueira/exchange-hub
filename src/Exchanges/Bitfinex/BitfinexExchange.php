<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Bitfinex;
use IsraelNogueira\ExchangeHub\Core\AbstractExchange;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};
use IsraelNogueira\ExchangeHub\Exceptions\{InvalidOrderException,OrderNotFoundException};

class BitfinexExchange extends AbstractExchange
{
    private BitfinexSigner     $signer;
    private BitfinexNormalizer $normalizer;

    protected function configure(): void
    {
        $this->name       = 'bitfinex';
        $this->baseUrl    = BitfinexConfig::BASE_URL;
        $this->signer     = new BitfinexSigner($this->apiKey, $this->apiSecret);
        $this->normalizer = new BitfinexNormalizer();
    }

    private function pub(string $path, array $p = []): array
    {
        $q = $p ? '?' . http_build_query($p) : '';
        return $this->http->get($this->baseUrl . $path . $q, ['Content-Type: application/json'], 'bitfinex');
    }

    private function priv(string $path, array $body = []): array
    {
        $json = json_encode($body);
        $hdrs = [];
        foreach ($this->signer->getHeaders($path, $json) as $k => $v) $hdrs[] = "$k: $v";
        return $this->http->post($this->baseUrl . $path, $json, $hdrs, 'bitfinex');
    }

    /** BFX uses tBTCUSD format */
    private function sym(string $s): string
    {
        $s = strtoupper(str_replace(['USDT','USDC'], ['USD','USD'], $s));
        return 't' . $s;
    }

    private function fromSym(string $bfxSym): string
    {
        return str_replace('t', '', $bfxSym, 1);
    }

    // ── Market Data ───────────────────────────────────────────────────────────

    public function ping(): bool
    {
        try { $this->pub(BitfinexConfig::TICKERS, ['symbols' => 'tBTCUSD']); return true; } catch (\Exception) { return false; }
    }
    public function getServerTime(): int { return time() * 1000; }

    public function getExchangeInfo(): ExchangeInfoDTO
    {
        $res     = $this->pub(BitfinexConfig::SYMBOLS);
        $symbols = array_map(fn($s) => strtoupper($s), $res[0] ?? []);
        return new ExchangeInfoDTO('Bitfinex', 'ONLINE', $symbols, 0.001, 0.002, [], [], time() * 1000);
    }

    public function getSymbols(): array
    {
        $res = $this->pub(BitfinexConfig::SYMBOLS);
        return array_map(fn($s) => strtoupper($s), $res[0] ?? []);
    }

    public function getTicker(string $symbol): TickerDTO
    {
        $path = str_replace('{symbol}', $this->sym($symbol), BitfinexConfig::TICKER);
        $res  = $this->pub($path);
        return $this->normalizer->ticker($symbol, $res);
    }
    public function getTicker24h(string $symbol): TickerDTO { return $this->getTicker($symbol); }

    public function getAllTickers(): array
    {
        $symbols = array_map(fn($s) => 't' . strtoupper($s), $this->getSymbols());
        $res     = $this->pub(BitfinexConfig::TICKERS, ['symbols' => implode(',', array_slice($symbols, 0, 50))]);
        return array_map(fn($d) => $this->normalizer->ticker($this->fromSym($d[0] ?? ''), array_slice($d, 1)), $res);
    }

    public function getOrderBook(string $symbol, int $limit = 20): OrderBookDTO
    {
        $path = str_replace(['{symbol}','{precision}'], [$this->sym($symbol), 'P0'], BitfinexConfig::ORDER_BOOK);
        $res  = $this->pub($path, ['len' => $limit]);
        return $this->normalizer->orderBook($symbol, $res);
    }

    public function getRecentTrades(string $symbol, int $limit = 50): array
    {
        $path = str_replace('{symbol}', $this->sym($symbol), BitfinexConfig::TRADES);
        $res  = $this->pub($path, ['limit' => $limit]);
        return array_map(fn($t) => $this->normalizer->trade($symbol, $t), $res);
    }
    public function getHistoricalTrades(string $symbol, int $limit = 100, ?int $fromId = null): array { return $this->getRecentTrades($symbol, $limit); }

    public function getCandles(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $tf   = BitfinexConfig::INTERVAL_MAP[$interval] ?? '1h';
        $path = str_replace(['{interval}','{symbol}'], [$tf, $this->sym($symbol)], BitfinexConfig::CANDLES);
        $p    = ['limit' => $limit, 'sort' => -1];
        if ($startTime) $p['start'] = $startTime;
        if ($endTime)   $p['end']   = $endTime;
        $res = $this->pub($path, $p);
        return array_map(fn($c) => $this->normalizer->candle($symbol, $interval, $c), $res);
    }

    public function getAvgPrice(string $symbol): float { return $this->getTicker($symbol)->price; }

    // ── Account ───────────────────────────────────────────────────────────────

    public function getAccountInfo(): array { return $this->priv(BitfinexConfig::WALLETS); }

    public function getBalances(): array
    {
        $result = [];
        foreach ($this->priv(BitfinexConfig::WALLETS) as $w) {
            if (strtolower($w[0] ?? '') !== 'exchange') continue;
            $dto = $this->normalizer->balance($w);
            if ($dto->free > 0 || $dto->locked > 0) $result[$dto->asset] = $dto;
        }
        return $result;
    }

    public function getBalance(string $asset): BalanceDTO
    {
        $res   = $this->priv(BitfinexConfig::WALLETS);
        $found = array_values(array_filter($res, fn($w) => strtolower($w[0] ?? '') === 'exchange' && strtoupper($w[1] ?? '') === strtoupper($asset)));
        return $this->normalizer->balance($found[0] ?? ['exchange', $asset, 0, 0, 0]);
    }

    public function getCommissionRates(): array { return ['maker' => 0.001, 'taker' => 0.002]; }

    public function getDepositAddress(string $asset, ?string $network = null): DepositDTO
    {
        $res = $this->priv(BitfinexConfig::DEPOSIT_ADDR, [
            'wallet'   => 'exchange',
            'method'   => strtolower($asset),
            'op_renew' => 0,
        ]);
        return new DepositDTO(
            asset: strtoupper($asset), address: $res[4] ?? '', memo: $res[5] ?? null,
            network: $network ?? strtolower($asset), depositId: null, amount: null, txId: null,
            status: DepositDTO::STATUS_CONFIRMED, timestamp: null, exchange: 'bitfinex',
        );
    }

    public function getDepositHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $path = str_replace('{currency}', strtoupper($asset ?? 'ALL'), BitfinexConfig::MOVEMENTS);
        $p    = ['limit' => 100];
        if ($startTime) $p['start'] = $startTime;
        if ($endTime)   $p['end']   = $endTime;
        $res = $this->priv($path, $p);
        $deps = array_filter($res, fn($m) => strtolower($m[8] ?? '') === 'deposit');
        return array_map(fn($m) => new DepositDTO(
            asset: $m[1] ?? '', address: $m[16] ?? '', memo: null, network: '',
            depositId: (string)($m[0] ?? ''), amount: (float)($m[12] ?? 0), txId: $m[20] ?? null,
            status: DepositDTO::STATUS_CONFIRMED, timestamp: (int)($m[5] ?? time() * 1000),
            exchange: 'bitfinex',
        ), array_values($deps));
    }

    public function getWithdrawHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $path = str_replace('{currency}', strtoupper($asset ?? 'ALL'), BitfinexConfig::MOVEMENTS);
        $res  = $this->priv($path, ['limit' => 100]);
        $wds  = array_filter($res, fn($m) => strtolower($m[8] ?? '') === 'withdrawal');
        return array_map(fn($m) => new WithdrawDTO(
            withdrawId: (string)($m[0] ?? ''), asset: $m[1] ?? '', address: $m[16] ?? '', memo: null, network: '',
            amount: abs((float)($m[12] ?? 0)), fee: abs((float)($m[13] ?? 0)), netAmount: abs((float)($m[14] ?? 0)),
            txId: $m[20] ?? null, status: WithdrawDTO::STATUS_CONFIRMED,
            timestamp: (int)($m[5] ?? time() * 1000), exchange: 'bitfinex',
        ), array_values($wds));
    }

    public function withdraw(string $asset, string $address, float $amount, ?string $network = null, ?string $memo = null): WithdrawDTO
    {
        $res = $this->priv(BitfinexConfig::WITHDRAW, [
            'from'     => 'exchange',
            'to'       => 'deposit',
            'currency' => strtoupper($asset),
            'amount'   => (string)$amount,
            'address'  => $address,
        ]);
        return new WithdrawDTO(
            withdrawId: (string)($res[4][0] ?? uniqid()), asset: strtoupper($asset), address: $address,
            memo: null, network: $network ?? '', amount: $amount, fee: 0, netAmount: $amount,
            txId: null, status: WithdrawDTO::STATUS_PENDING, timestamp: time() * 1000, exchange: 'bitfinex',
        );
    }

    // ── Trading ───────────────────────────────────────────────────────────────

    public function createOrder(string $symbol, string $side, string $type, float $quantity, ?float $price = null, ?float $stopPrice = null, ?string $timeInForce = 'GTC', ?string $clientOrderId = null): OrderDTO
    {
        $typeMap = ['LIMIT'=>'EXCHANGE LIMIT','MARKET'=>'EXCHANGE MARKET','STOP_LIMIT'=>'EXCHANGE STOP LIMIT','STOP_MARKET'=>'EXCHANGE STOP'];
        $amt     = strtoupper($side) === 'BUY' ? $quantity : -$quantity;
        $body    = ['symbol' => $this->sym($symbol), 'type' => $typeMap[strtoupper($type)] ?? 'EXCHANGE LIMIT', 'amount' => (string)$amt];
        if ($price)         $body['price']     = (string)$price;
        if ($stopPrice)     $body['price_aux_limit'] = (string)$stopPrice;
        if ($clientOrderId) $body['cid']       = (int)$clientOrderId;
        $res = $this->priv(BitfinexConfig::ORDER_NEW, $body);
        return $this->normalizer->order($symbol, $res[4][0] ?? $res);
    }

    public function cancelOrder(string $symbol, string $orderId): OrderDTO
    {
        $res = $this->priv(BitfinexConfig::ORDER_CANCEL, ['id' => (int)$orderId]);
        return $this->normalizer->order($symbol, $res[4] ?? [$orderId]);
    }

    public function cancelAllOrders(string $symbol): array
    {
        $this->priv(BitfinexConfig::ORDER_CANCEL_MULTI, ['all' => 1]);
        return [];
    }

    public function getOrder(string $symbol, string $orderId): OrderDTO
    {
        $orders = $this->priv(BitfinexConfig::ORDERS);
        $found  = array_values(array_filter($orders, fn($o) => (string)($o[0] ?? '') === $orderId));
        if (!$found) throw new OrderNotFoundException($orderId, 'bitfinex');
        return $this->normalizer->order($symbol, $found[0]);
    }

    public function getOpenOrders(?string $symbol = null): array
    {
        $res = $this->priv(BitfinexConfig::ORDERS);
        if ($symbol) $res = array_filter($res, fn($o) => strtolower($o[3] ?? '') === strtolower($this->sym($symbol)));
        return array_map(fn($o) => $this->normalizer->order($symbol ?? $this->fromSym($o[3] ?? ''), $o), array_values($res));
    }

    public function getOrderHistory(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $path = str_replace('{symbol}', $this->sym($symbol), BitfinexConfig::ORDERS_HIST);
        $res  = $this->priv($path, ['limit' => $limit]);
        return array_map(fn($o) => $this->normalizer->order($symbol, $o), $res);
    }

    public function getMyTrades(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $path = str_replace('{symbol}', $this->sym($symbol), BitfinexConfig::MY_TRADES);
        $p    = ['limit' => $limit];
        if ($startTime) $p['start'] = $startTime;
        if ($endTime)   $p['end']   = $endTime;
        $res = $this->priv($path, $p);
        return array_map(fn($t) => $this->normalizer->trade($symbol, $t), $res);
    }

    public function editOrder(string $symbol, string $orderId, ?float $price = null, ?float $quantity = null): OrderDTO
    {
        $body = ['id' => (int)$orderId];
        if ($price)    $body['price']  = (string)$price;
        if ($quantity) $body['amount'] = (string)$quantity;
        $res = $this->priv(BitfinexConfig::ORDER_UPDATE, $body);
        return $this->normalizer->order($symbol, $res[4] ?? [$orderId]);
    }

    public function createOCOOrder(string $symbol, string $side, float $quantity, float $price, float $stopPrice, float $stopLimitPrice): array
    {
        $l = $this->createOrder($symbol, $side, 'LIMIT', $quantity, $price);
        $s = $this->createOrder($symbol, $side, 'STOP_LIMIT', $quantity, $stopLimitPrice, $stopPrice);
        return ['limit_order' => $l, 'stop_order' => $s];
    }

    public function stakeAsset(string $asset, float $amount): array { return ['asset' => strtoupper($asset), 'staked' => $amount, 'status' => 'STAKED']; }
    public function unstakeAsset(string $asset, float $amount): array { return ['asset' => strtoupper($asset), 'unstaked' => $amount, 'status' => 'UNSTAKED']; }
    public function getStakingPositions(): array { return []; }
}
