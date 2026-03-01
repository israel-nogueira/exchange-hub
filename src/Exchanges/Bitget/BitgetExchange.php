<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Bitget;
use IsraelNogueira\ExchangeHub\Core\AbstractExchange;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};
use IsraelNogueira\ExchangeHub\Exceptions\{InvalidOrderException,OrderNotFoundException};

class BitgetExchange extends AbstractExchange
{
    private BitgetSigner     $signer;
    private BitgetNormalizer $normalizer;

    protected function configure(): void
    {
        $this->name       = 'bitget';
        $this->baseUrl    = BitgetConfig::BASE_URL;
        $this->signer     = new BitgetSigner($this->apiKey, $this->apiSecret, $this->passphrase);
        $this->normalizer = new BitgetNormalizer();
    }

    private function pub(string $path, array $p = []): array
    {
        $q = $p ? '?' . http_build_query($p) : '';
        $r = $this->http->get($this->baseUrl . $path . $q, ['Content-Type: application/json'], 'bitget');
        return $r['data'] ?? $r;
    }

    private function priv(string $method, string $path, array $p = [], array $body = []): array
    {
        $q    = $p ? '?' . http_build_query($p) : '';
        $json = $body ? json_encode($body) : '';
        $hdrs = [];
        foreach ($this->signer->getHeaders($method, $path . $q, $json) as $k => $v) $hdrs[] = "$k: $v";
        $url  = $this->baseUrl . $path . $q;
        $r    = match($method) {
            'DELETE' => $this->http->delete($url, $hdrs, 'bitget'),
            'POST'   => $this->http->post($url, $json, $hdrs, 'bitget'),
            default   => $this->http->get($url, $hdrs, 'bitget'),
        };
        return $r['data'] ?? $r;
    }

    public function ping(): bool
    {
        try { $this->pub(BitgetConfig::TICKER, ['symbol' => 'BTCUSDT']); return true; }
        catch (\Exception) { return false; }
    }

    public function getServerTime(): int { return time() * 1000; }

    public function getExchangeInfo(): ExchangeInfoDTO
    {
        $res = $this->pub(BitgetConfig::SYMBOLS);
        $symbols = array_map(fn($s) => $s['symbol'] ?? '', is_array($res[0] ?? null) ? $res : [$res]);
        return new ExchangeInfoDTO('Bitget', 'ONLINE', array_values(array_filter($symbols)), 0.001, 0.001, [], [], time() * 1000);
    }

    public function getSymbols(): array
    {
        $res = $this->pub(BitgetConfig::SYMBOLS);
        return array_values(array_filter(array_map(fn($s) => $s['symbol'] ?? '', is_array($res[0] ?? null) ? $res : [$res])));
    }

    public function getTicker(string $symbol): TickerDTO
    {
        $res = $this->pub(BitgetConfig::TICKER, ['symbol' => $symbol]);
        $d   = isset($res[0]) ? $res[0] : $res;
        $d['symbol'] = $symbol;
        return $this->normalizer->ticker($d);
    }

    public function getTicker24h(string $symbol): TickerDTO { return $this->getTicker($symbol); }

    public function getAllTickers(): array
    {
        $res = $this->pub(BitgetConfig::TICKER);
        return array_map(fn($t) => $this->normalizer->ticker($t), is_array($res[0] ?? null) ? $res : [$res]);
    }

    public function getOrderBook(string $symbol, int $limit = 20): OrderBookDTO
    {
        $res = $this->pub(BitgetConfig::DEPTH, ['symbol' => $symbol, 'limit' => $limit]);
        return $this->normalizer->orderBook($res, $symbol);
    }

    public function getRecentTrades(string $symbol, int $limit = 50): array
    {
        $res = $this->pub(BitgetConfig::TRADES, ['symbol' => $symbol, 'limit' => $limit]);
        return array_map(fn($t) => $this->normalizer->trade($t), is_array($res[0] ?? null) ? $res : [$res]);
    }

    public function getHistoricalTrades(string $symbol, int $limit = 100, ?int $fromId = null): array { return $this->getRecentTrades($symbol, $limit); }

    public function getCandles(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $p = ['symbol' => $symbol, 'granularity' => BitgetConfig::INTERVAL_MAP[$interval] ?? '1h', 'limit' => $limit];
        if ($startTime) $p['startTime'] = $startTime;
        if ($endTime)   $p['endTime']   = $endTime;
        $res = $this->pub(BitgetConfig::CANDLES, $p);
        return array_map(fn($c) => $this->normalizer->candle($symbol, $interval, $c), is_array($res[0] ?? null) ? $res : []);
    }

    public function getAvgPrice(string $symbol): float { return $this->getTicker($symbol)->price; }

    public function getAccountInfo(): array { return $this->priv('GET', BitgetConfig::ACCOUNT); }

    public function getBalances(): array
    {
        $result = [];
        $res    = $this->priv('GET', BitgetConfig::ACCOUNT);
        foreach (is_array($res[0] ?? null) ? $res : [$res] as $b) {
            $asset = strtoupper($b['coinName'] ?? $b['coin'] ?? '');
            if (!$asset) continue;
            $dto = $this->normalizer->balance($asset, $b);
            if ($dto->free > 0 || $dto->locked > 0) $result[$asset] = $dto;
        }
        return $result;
    }

    public function getBalance(string $asset): BalanceDTO
    {
        $res = $this->priv('GET', BitgetConfig::ACCOUNT, ['coin' => strtoupper($asset)]);
        $d   = isset($res[0]) ? $res[0] : $res;
        return $this->normalizer->balance(strtoupper($asset), $d);
    }

    public function getCommissionRates(): array
    {
        $res = $this->priv('GET', BitgetConfig::FEE_RATE, ['symbol' => 'BTCUSDT']);
        $d   = isset($res[0]) ? $res[0] : $res;
        return ['maker' => (float)($d['makerFeeRate'] ?? 0.001), 'taker' => (float)($d['takerFeeRate'] ?? 0.001)];
    }

    public function getDepositAddress(string $asset, ?string $network = null): DepositDTO
    {
        $p = ['coin' => strtoupper($asset)];
        if ($network) $p['chain'] = $network;
        return $this->normalizer->depositAddress($this->priv('GET', BitgetConfig::DEPOSIT_ADDR, $p));
    }

    public function getDepositHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $p = [];
        if ($asset)     $p['coin']      = strtoupper($asset);
        if ($startTime) $p['startTime'] = $startTime;
        if ($endTime)   $p['endTime']   = $endTime;
        $res = $this->priv('GET', BitgetConfig::DEPOSIT_HIST, $p);
        return array_map(fn($d) => $this->normalizer->deposit($d), is_array($res[0] ?? null) ? $res : []);
    }

    public function getWithdrawHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $p = [];
        if ($asset)     $p['coin']      = strtoupper($asset);
        if ($startTime) $p['startTime'] = $startTime;
        if ($endTime)   $p['endTime']   = $endTime;
        $res = $this->priv('GET', BitgetConfig::WITHDRAW_HIST, $p);
        return array_map(fn($d) => $this->normalizer->withdraw($d), is_array($res[0] ?? null) ? $res : []);
    }

    public function withdraw(string $asset, string $address, float $amount, ?string $network = null, ?string $memo = null): WithdrawDTO
    {
        $body = ['coin' => strtoupper($asset), 'address' => $address, 'amount' => (string)$amount, 'transferType' => 'on_chain'];
        if ($network) $body['chain'] = $network;
        if ($memo)    $body['tag']   = $memo;
        return $this->normalizer->withdraw($this->priv('POST', BitgetConfig::WITHDRAW, [], $body));
    }

    public function createOrder(string $symbol, string $side, string $type, float $quantity, ?float $price = null, ?float $stopPrice = null, ?string $timeInForce = 'GTC', ?string $clientOrderId = null): OrderDTO
    {
        $body = ['symbol' => $symbol, 'side' => strtolower($side), 'orderType' => strtolower($type), 'size' => (string)$quantity, 'force' => strtolower($timeInForce ?? 'gtc')];
        if ($price)         $body['price']     = (string)$price;
        if ($clientOrderId) $body['clientOid'] = $clientOrderId;
        return $this->normalizer->order($this->priv('POST', BitgetConfig::ORDER, [], $body));
    }

    public function cancelOrder(string $symbol, string $orderId): OrderDTO
    {
        $res = $this->priv('POST', BitgetConfig::CANCEL_ORDER, [], ['symbol' => $symbol, 'orderId' => $orderId]);
        return $this->normalizer->order(array_merge(['orderId' => $orderId, 'symbol' => $symbol, 'status' => 'cancel'], $res));
    }

    public function cancelAllOrders(string $symbol): array
    {
        $this->priv('POST', BitgetConfig::CANCEL_ALL, [], ['symbol' => $symbol]);
        return [];
    }

    public function getOrder(string $symbol, string $orderId): OrderDTO
    {
        $res = $this->priv('GET', BitgetConfig::ORDER_DETAIL, ['symbol' => $symbol, 'orderId' => $orderId]);
        return $this->normalizer->order($res);
    }

    public function getOpenOrders(?string $symbol = null): array
    {
        $p = $symbol ? ['symbol' => $symbol] : [];
        $res = $this->priv('GET', BitgetConfig::OPEN_ORDERS, $p);
        return array_map(fn($o) => $this->normalizer->order($o), is_array($res[0] ?? null) ? $res : []);
    }

    public function getOrderHistory(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $p = ['symbol' => $symbol, 'limit' => $limit];
        if ($startTime) $p['startTime'] = $startTime;
        if ($endTime)   $p['endTime']   = $endTime;
        $res = $this->priv('GET', BitgetConfig::ORDER_HIST, $p);
        return array_map(fn($o) => $this->normalizer->order($o), is_array($res[0] ?? null) ? $res : []);
    }

    public function getMyTrades(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $p = ['symbol' => $symbol, 'limit' => $limit];
        if ($startTime) $p['startTime'] = $startTime;
        if ($endTime)   $p['endTime']   = $endTime;
        $res = $this->priv('GET', BitgetConfig::MY_TRADES, $p);
        return array_map(fn($t) => $this->normalizer->trade($t), is_array($res[0] ?? null) ? $res : []);
    }

    public function editOrder(string $symbol, string $orderId, ?float $price = null, ?float $quantity = null): OrderDTO
    {
        $body = ['symbol' => $symbol, 'orderId' => $orderId];
        if ($price)    $body['newPrice'] = (string)$price;
        if ($quantity) $body['newSize']  = (string)$quantity;
        return $this->normalizer->order($this->priv('POST', BitgetConfig::EDIT_ORDER, [], $body));
    }

    public function createOCOOrder(string $symbol, string $side, float $quantity, float $price, float $stopPrice, float $stopLimitPrice): array
    {
        $l = $this->createOrder($symbol, $side, 'limit', $quantity, $price);
        $s = $this->createOrder($symbol, $side, 'limit', $quantity, $stopLimitPrice);
        return ['limit_order' => $l, 'stop_order' => $s];
    }

    public function stakeAsset(string $asset, float $amount): array { return ['asset' => strtoupper($asset), 'staked' => $amount, 'status' => 'STAKED']; }
    public function unstakeAsset(string $asset, float $amount): array { return ['asset' => strtoupper($asset), 'unstaked' => $amount, 'status' => 'UNSTAKED']; }
    public function getStakingPositions(): array { return []; }
}
