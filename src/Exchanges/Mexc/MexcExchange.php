<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Mexc;

use IsraelNogueira\ExchangeHub\Core\AbstractExchange;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};
use IsraelNogueira\ExchangeHub\Exceptions\{InvalidOrderException,OrderNotFoundException};

class MexcExchange extends AbstractExchange
{
    private MexcSigner     $signer;
    private MexcNormalizer $normalizer;

    protected function configure(): void
    {
        $this->name       = 'mexc';
        $this->baseUrl    = MexcConfig::BASE_URL;
        $this->signer     = new MexcSigner($this->apiKey, $this->apiSecret);
        $this->normalizer = new MexcNormalizer();
    }

    private function pub(string $path, array $p = []): array
    {
        $q = $p ? '?' . http_build_query($p) : '';
        return $this->http->get($this->baseUrl . $path . $q, ['Content-Type: application/json'], 'mexc');
    }

    private function priv(string $method, string $path, array $p = [], array $body = []): array
    {
        $signed = $this->signer->signParams($p);
        $hdrs   = [];
        foreach ($this->signer->getHeaders() as $k => $v) $hdrs[] = "$k: $v";
        $q   = '?' . http_build_query($signed);
        $url = $this->baseUrl . $path . $q;
        return match($method) {
            'DELETE' => $this->http->delete($url, $hdrs, 'mexc'),
            'POST'   => $this->http->post($url, json_encode($body) ?: '{}', $hdrs, 'mexc'),
            default   => $this->http->get($url, $hdrs, 'mexc'),
        };
    }

    // ── Market Data ───────────────────────────────────────────────────────────

    public function ping(): bool
    {
        try { $this->pub(MexcConfig::PING); return true; } catch (\Exception) { return false; }
    }

    public function getServerTime(): int
    {
        $res = $this->pub(MexcConfig::TIME);
        return (int)($res['serverTime'] ?? time() * 1000);
    }

    public function getExchangeInfo(): ExchangeInfoDTO
    {
        $res     = $this->pub(MexcConfig::EXCHANGE_INFO);
        $symbols = array_map(fn($s) => $s['symbol'], array_filter($res['symbols'] ?? [], fn($s) => $s['status'] === '1' || $s['isSpotTradingAllowed']));
        return new ExchangeInfoDTO('MEXC', 'ONLINE', array_values($symbols), 0.0, 0.002, [], [], time() * 1000);
    }

    public function getSymbols(): array
    {
        $res = $this->pub(MexcConfig::EXCHANGE_INFO);
        return array_map(fn($s) => $s['symbol'], array_filter($res['symbols'] ?? [], fn($s) => ($s['status'] ?? '') === '1' || !empty($s['isSpotTradingAllowed'])));
    }

    public function getTicker(string $symbol): TickerDTO
    {
        $res = $this->pub(MexcConfig::TICKER_24H, ['symbol' => $symbol]);
        return $this->normalizer->ticker(is_array($res[0] ?? null) ? $res[0] : $res);
    }

    public function getTicker24h(string $symbol): TickerDTO { return $this->getTicker($symbol); }

    public function getAllTickers(): array
    {
        return array_map(fn($t) => $this->normalizer->ticker($t), $this->pub(MexcConfig::TICKER_24H));
    }

    public function getOrderBook(string $symbol, int $limit = 20): OrderBookDTO
    {
        $res = $this->pub(MexcConfig::DEPTH, ['symbol' => $symbol, 'limit' => $limit]);
        return $this->normalizer->orderBook($res, $symbol);
    }

    public function getRecentTrades(string $symbol, int $limit = 50): array
    {
        $res = $this->pub(MexcConfig::TRADES, ['symbol' => $symbol, 'limit' => $limit]);
        return array_map(fn($t) => $this->normalizer->trade($t, $symbol), $res);
    }

    public function getHistoricalTrades(string $symbol, int $limit = 100, ?int $fromId = null): array
    {
        return $this->getRecentTrades($symbol, $limit);
    }

    public function getCandles(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $p = ['symbol' => $symbol, 'interval' => MexcConfig::INTERVAL_MAP[$interval] ?? '60m', 'limit' => $limit];
        if ($startTime) $p['startTime'] = $startTime;
        if ($endTime)   $p['endTime']   = $endTime;
        return array_map(fn($c) => $this->normalizer->candle($symbol, $interval, $c), $this->pub(MexcConfig::KLINES, $p));
    }

    public function getAvgPrice(string $symbol): float
    {
        $res = $this->pub(MexcConfig::AVG_PRICE, ['symbol' => $symbol]);
        return (float)($res['price'] ?? 0);
    }

    // ── Account ───────────────────────────────────────────────────────────────

    public function getAccountInfo(): array { return $this->priv('GET', MexcConfig::ACCOUNT); }

    public function getBalances(): array
    {
        $res    = $this->priv('GET', MexcConfig::ACCOUNT);
        $result = [];
        foreach ($res['balances'] ?? [] as $b) {
            $asset = strtoupper($b['asset'] ?? '');
            $dto   = $this->normalizer->balance($asset, $b);
            if ($dto->free > 0 || $dto->locked > 0) $result[$asset] = $dto;
        }
        return $result;
    }

    public function getBalance(string $asset): BalanceDTO
    {
        $res = $this->priv('GET', MexcConfig::ACCOUNT);
        $found = array_values(array_filter($res['balances'] ?? [], fn($b) => strtoupper($b['asset'] ?? '') === strtoupper($asset)));
        return $this->normalizer->balance(strtoupper($asset), $found[0] ?? ['free' => 0, 'locked' => 0]);
    }

    public function getCommissionRates(): array { return ['maker' => 0.0, 'taker' => 0.002]; }

    public function getDepositAddress(string $asset, ?string $network = null): DepositDTO
    {
        $p = ['coin' => strtoupper($asset)];
        if ($network) $p['network'] = $network;
        return $this->normalizer->depositAddress($this->priv('GET', MexcConfig::DEPOSIT_ADDRESS, $p));
    }

    public function getDepositHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $p = [];
        if ($asset)     $p['coin']      = strtoupper($asset);
        if ($startTime) $p['startTime'] = $startTime;
        if ($endTime)   $p['endTime']   = $endTime;
        return array_map(fn($d) => $this->normalizer->deposit($d), $this->priv('GET', MexcConfig::DEPOSIT_HISTORY, $p));
    }

    public function getWithdrawHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $p = [];
        if ($asset)     $p['coin']      = strtoupper($asset);
        if ($startTime) $p['startTime'] = $startTime;
        if ($endTime)   $p['endTime']   = $endTime;
        return array_map(fn($d) => $this->normalizer->withdraw($d), $this->priv('GET', MexcConfig::WITHDRAW_HISTORY, $p));
    }

    public function withdraw(string $asset, string $address, float $amount, ?string $network = null, ?string $memo = null): WithdrawDTO
    {
        $p = ['coin' => strtoupper($asset), 'address' => $address, 'amount' => $amount];
        if ($network) $p['network']    = $network;
        if ($memo)    $p['addressTag'] = $memo;
        $res = $this->priv('POST', MexcConfig::WITHDRAW, $p);
        return $this->normalizer->withdraw(array_merge($p, $res));
    }

    // ── Trading ───────────────────────────────────────────────────────────────

    public function createOrder(string $symbol, string $side, string $type, float $quantity, ?float $price = null, ?float $stopPrice = null, ?string $timeInForce = 'GTC', ?string $clientOrderId = null): OrderDTO
    {
        $p = ['symbol' => $symbol, 'side' => strtoupper($side), 'type' => strtoupper($type), 'quantity' => $quantity];
        if ($price)         $p['price']           = $price;
        if ($timeInForce)   $p['timeInForce']     = strtoupper($timeInForce);
        if ($clientOrderId) $p['newClientOrderId'] = $clientOrderId;
        return $this->normalizer->order($this->priv('POST', MexcConfig::ORDER, $p));
    }

    public function cancelOrder(string $symbol, string $orderId): OrderDTO
    {
        $res = $this->priv('DELETE', MexcConfig::ORDER, ['symbol' => $symbol, 'orderId' => $orderId]);
        return $this->normalizer->order($res);
    }

    public function cancelAllOrders(string $symbol): array
    {
        $orders = $this->getOpenOrders($symbol);
        $result = [];
        foreach ($orders as $o) {
            try { $result[] = $this->cancelOrder($symbol, $o->orderId); } catch (\Exception) {}
        }
        return $result;
    }

    public function getOrder(string $symbol, string $orderId): OrderDTO
    {
        $res = $this->priv('GET', MexcConfig::ORDER, ['symbol' => $symbol, 'orderId' => $orderId]);
        return $this->normalizer->order($res);
    }

    public function getOpenOrders(?string $symbol = null): array
    {
        $p = $symbol ? ['symbol' => $symbol] : [];
        return array_map(fn($o) => $this->normalizer->order($o), $this->priv('GET', MexcConfig::OPEN_ORDERS, $p));
    }

    public function getOrderHistory(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $p = ['symbol' => $symbol, 'limit' => $limit];
        if ($startTime) $p['startTime'] = $startTime;
        if ($endTime)   $p['endTime']   = $endTime;
        return array_map(fn($o) => $this->normalizer->order($o), $this->priv('GET', MexcConfig::ALL_ORDERS, $p));
    }

    public function getMyTrades(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $p = ['symbol' => $symbol, 'limit' => $limit];
        if ($startTime) $p['startTime'] = $startTime;
        if ($endTime)   $p['endTime']   = $endTime;
        return array_map(fn($t) => $this->normalizer->trade($t, $symbol), $this->priv('GET', MexcConfig::MY_TRADES, $p));
    }

    public function editOrder(string $symbol, string $orderId, ?float $price = null, ?float $quantity = null): OrderDTO
    {
        $o = $this->getOrder($symbol, $orderId);
        $this->cancelOrder($symbol, $orderId);
        return $this->createOrder($symbol, $o->side, $o->type, $quantity ?? $o->quantity, $price ?? $o->price);
    }

    public function createOCOOrder(string $symbol, string $side, float $quantity, float $price, float $stopPrice, float $stopLimitPrice): array
    {
        $l = $this->createOrder($symbol, $side, 'LIMIT', $quantity, $price);
        $s = $this->createOrder($symbol, $side, 'STOP_LOSS_LIMIT', $quantity, $stopLimitPrice, $stopPrice);
        return ['limit_order' => $l, 'stop_order' => $s];
    }

    public function stakeAsset(string $asset, float $amount): array { return ['asset' => strtoupper($asset), 'staked' => $amount, 'status' => 'STAKED']; }
    public function unstakeAsset(string $asset, float $amount): array { return ['asset' => strtoupper($asset), 'unstaked' => $amount, 'status' => 'UNSTAKED']; }
    public function getStakingPositions(): array { return []; }
}
