<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Gateio;

use IsraelNogueira\ExchangeHub\Core\AbstractExchange;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};
use IsraelNogueira\ExchangeHub\Exceptions\{InvalidOrderException,OrderNotFoundException};

class GateioExchange extends AbstractExchange
{
    private GateioSigner     $signer;
    private GateioNormalizer $normalizer;

    protected function configure(): void
    {
        $this->name       = 'gateio';
        $this->baseUrl    = GateioConfig::BASE_URL;
        $this->signer     = new GateioSigner($this->apiKey, $this->apiSecret);
        $this->normalizer = new GateioNormalizer();
    }

    private function pub(string $path, array $params = []): array
    {
        $q   = $params ? '?' . http_build_query($params) : '';
        return $this->http->get($this->baseUrl . $path . $q, ['Accept: application/json'], 'gateio');
    }

    private function priv(string $method, string $path, array $params = [], array $body = []): array
    {
        $q    = $params ? http_build_query($params) : '';
        $json = $body ? json_encode($body) : '';
        $hdrs = [];
        foreach ($this->signer->getHeaders($method, $path, $q, $json) as $k => $v) {
            $hdrs[] = "$k: $v";
        }
        $url = $this->baseUrl . $path . ($q ? "?$q" : '');
        return match($method) {
            'GET'    => $this->http->get($url, $hdrs, 'gateio'),
            'DELETE' => $this->http->delete($url, $hdrs, 'gateio'),
            default  => $this->http->post($url, $json, $hdrs, 'gateio'),
        };
    }

    private function sym(string $s): string
    {
        if (str_contains($s, '_')) return $s;
        foreach (['USDT','USDC','BTC','ETH','BRL','EUR','USD','BNB'] as $q) {
            if (str_ends_with($s, $q)) return substr($s, 0, -strlen($q)) . '_' . $q;
        }
        return $s;
    }

    // ── Market Data ───────────────────────────────────────────────────────────

    public function ping(): bool
    {
        try { $this->pub(GateioConfig::TICKERS, ['currency_pair' => 'BTC_USDT']); return true; }
        catch (\Exception) { return false; }
    }

    public function getServerTime(): int { return time() * 1000; }

    public function getExchangeInfo(): ExchangeInfoDTO
    {
        $pairs   = $this->pub(GateioConfig::CURRENCY_PAIRS);
        $symbols = array_values(array_filter(array_map(fn($p) => str_replace('_', '', $p['id'] ?? ''), $pairs)));
        return new ExchangeInfoDTO('Gate.io', 'ONLINE', $symbols, 0.002, 0.002, [['type'=>'REQUESTS','limit'=>900,'interval'=>'1m']], [], time() * 1000);
    }

    public function getSymbols(): array
    {
        return array_values(array_filter(array_map(
            fn($p) => str_replace('_', '', $p['id'] ?? ''),
            $this->pub(GateioConfig::CURRENCY_PAIRS)
        )));
    }

    public function getTicker(string $symbol): TickerDTO
    {
        $res = $this->pub(GateioConfig::TICKERS, ['currency_pair' => $this->sym($symbol)]);
        $d   = isset($res[0]) ? $res[0] : $res;
        $d['currency_pair'] = $symbol;
        return $this->normalizer->ticker($d);
    }

    public function getTicker24h(string $symbol): TickerDTO { return $this->getTicker($symbol); }

    public function getAllTickers(): array
    {
        return array_map(function ($d) {
            $d['currency_pair'] = str_replace('_', '', $d['currency_pair'] ?? '');
            return $this->normalizer->ticker($d);
        }, $this->pub(GateioConfig::TICKERS));
    }

    public function getOrderBook(string $symbol, int $limit = 20): OrderBookDTO
    {
        $res = $this->pub(GateioConfig::ORDER_BOOK, ['currency_pair' => $this->sym($symbol), 'limit' => $limit]);
        return $this->normalizer->orderBook($res, $symbol);
    }

    public function getRecentTrades(string $symbol, int $limit = 50): array
    {
        $res = $this->pub(GateioConfig::TRADES, ['currency_pair' => $this->sym($symbol), 'limit' => $limit]);
        return array_map(fn($t) => $this->normalizer->trade($t), $res);
    }

    public function getHistoricalTrades(string $symbol, int $limit = 100, ?int $fromId = null): array
    {
        $p = ['currency_pair' => $this->sym($symbol), 'limit' => $limit];
        if ($fromId) $p['last_id'] = $fromId;
        return array_map(fn($t) => $this->normalizer->trade($t), $this->pub(GateioConfig::TRADES, $p));
    }

    public function getCandles(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $p = ['currency_pair' => $this->sym($symbol), 'interval' => GateioConfig::INTERVAL_MAP[$interval] ?? '1h', 'limit' => $limit];
        if ($startTime) $p['from'] = (int)($startTime / 1000);
        if ($endTime)   $p['to']   = (int)($endTime / 1000);
        return array_map(fn($c) => $this->normalizer->candle($symbol, $interval, $c), $this->pub(GateioConfig::CANDLES, $p));
    }

    public function getAvgPrice(string $symbol): float { return $this->getTicker($symbol)->price; }

    // ── Account ───────────────────────────────────────────────────────────────

    public function getAccountInfo(): array { return $this->priv('GET', GateioConfig::ACCOUNTS); }

    public function getBalances(): array
    {
        $result = [];
        foreach ($this->priv('GET', GateioConfig::ACCOUNTS) as $b) {
            $asset = strtoupper($b['currency'] ?? '');
            if (!$asset) continue;
            $dto = $this->normalizer->balance($asset, $b);
            if ($dto->free > 0 || $dto->locked > 0) $result[$asset] = $dto;
        }
        return $result;
    }

    public function getBalance(string $asset): BalanceDTO
    {
        $res = $this->priv('GET', GateioConfig::ACCOUNTS . '/' . strtoupper($asset));
        return $this->normalizer->balance(strtoupper($asset), $res);
    }

    public function getCommissionRates(): array { return ['maker' => 0.002, 'taker' => 0.002]; }

    public function getDepositAddress(string $asset, ?string $network = null): DepositDTO
    {
        $p = ['currency' => strtoupper($asset)];
        if ($network) $p['chain'] = $network;
        return $this->normalizer->depositAddress($this->priv('GET', GateioConfig::DEPOSIT_ADDRESS, $p));
    }

    public function getDepositHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $p = ['limit' => 100];
        if ($asset)     $p['currency'] = strtoupper($asset);
        if ($startTime) $p['from']     = (int)($startTime / 1000);
        if ($endTime)   $p['to']       = (int)($endTime / 1000);
        return array_map(fn($d) => $this->normalizer->deposit($d), $this->priv('GET', GateioConfig::DEPOSITS, $p));
    }

    public function getWithdrawHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $p = ['limit' => 100];
        if ($asset)     $p['currency'] = strtoupper($asset);
        if ($startTime) $p['from']     = (int)($startTime / 1000);
        if ($endTime)   $p['to']       = (int)($endTime / 1000);
        return array_map(fn($d) => $this->normalizer->withdraw($d), $this->priv('GET', GateioConfig::WITHDRAWALS, $p));
    }

    public function withdraw(string $asset, string $address, float $amount, ?string $network = null, ?string $memo = null): WithdrawDTO
    {
        $body = ['currency' => strtoupper($asset), 'address' => $address, 'amount' => (string)$amount];
        if ($network) $body['chain'] = $network;
        if ($memo)    $body['memo']  = $memo;
        return $this->normalizer->withdraw($this->priv('POST', GateioConfig::WITHDRAW, [], $body));
    }

    // ── Trading ───────────────────────────────────────────────────────────────

    public function createOrder(string $symbol, string $side, string $type, float $quantity, ?float $price = null, ?float $stopPrice = null, ?string $timeInForce = 'GTC', ?string $clientOrderId = null): OrderDTO
    {
        $body = [
            'currency_pair' => $this->sym($symbol),
            'type'          => strtolower($type) === 'market' ? 'market' : 'limit',
            'side'          => strtolower($side),
            'amount'        => (string)$quantity,
            'time_in_force' => strtolower($timeInForce ?? 'gtc'),
        ];
        if ($price)         $body['price'] = (string)$price;
        if ($clientOrderId) $body['text']  = 't-' . $clientOrderId;
        return $this->normalizer->order($this->priv('POST', GateioConfig::ORDERS, [], $body));
    }

    public function cancelOrder(string $symbol, string $orderId): OrderDTO
    {
        $res = $this->priv('DELETE', GateioConfig::ORDERS . '/' . $orderId, ['currency_pair' => $this->sym($symbol)]);
        return $this->normalizer->order($res);
    }

    public function cancelAllOrders(string $symbol): array
    {
        $res = $this->priv('DELETE', GateioConfig::ORDERS, ['currency_pair' => $this->sym($symbol), 'side' => 'both']);
        return array_map(fn($o) => $this->normalizer->order($o), $res);
    }

    public function getOrder(string $symbol, string $orderId): OrderDTO
    {
        $res = $this->priv('GET', GateioConfig::ORDERS . '/' . $orderId, ['currency_pair' => $this->sym($symbol)]);
        return $this->normalizer->order($res);
    }

    public function getOpenOrders(?string $symbol = null): array
    {
        $p = ['status' => 'open', 'limit' => 100];
        if ($symbol) $p['currency_pair'] = $this->sym($symbol);
        return array_map(fn($o) => $this->normalizer->order($o), $this->priv('GET', GateioConfig::ORDERS, $p));
    }

    public function getOrderHistory(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $p = ['currency_pair' => $this->sym($symbol), 'status' => 'finished', 'limit' => $limit];
        if ($startTime) $p['from'] = (int)($startTime / 1000);
        if ($endTime)   $p['to']   = (int)($endTime / 1000);
        return array_map(fn($o) => $this->normalizer->order($o), $this->priv('GET', GateioConfig::ORDERS, $p));
    }

    public function getMyTrades(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $p = ['currency_pair' => $this->sym($symbol), 'limit' => $limit];
        if ($startTime) $p['from'] = (int)($startTime / 1000);
        if ($endTime)   $p['to']   = (int)($endTime / 1000);
        return array_map(fn($t) => $this->normalizer->trade($t), $this->priv('GET', GateioConfig::MY_TRADES, $p));
    }

    public function editOrder(string $symbol, string $orderId, ?float $price = null, ?float $quantity = null): OrderDTO
    {
        $original = $this->getOrder($symbol, $orderId);
        $this->cancelOrder($symbol, $orderId);
        return $this->createOrder($symbol, $original->side, $original->type, $quantity ?? $original->quantity, $price ?? $original->price);
    }

    public function createOCOOrder(string $symbol, string $side, float $quantity, float $price, float $stopPrice, float $stopLimitPrice): array
    {
        $l = $this->createOrder($symbol, $side, 'LIMIT', $quantity, $price);
        $s = $this->createOrder($symbol, $side, 'LIMIT', $quantity, $stopLimitPrice);
        return ['limit_order' => $l, 'stop_order' => $s];
    }

    // ── Staking (Earn) ────────────────────────────────────────────────────────

    public function stakeAsset(string $asset, float $amount): array
    {
        $this->priv('POST', GateioConfig::EARN_LEND, [], ['currency' => strtoupper($asset), 'amount' => (string)$amount, 'min_rate' => '0.0001']);
        return ['asset' => strtoupper($asset), 'staked' => $amount, 'status' => 'STAKED'];
    }

    public function unstakeAsset(string $asset, float $amount): array
    {
        $this->priv('DELETE', GateioConfig::EARN_LEND, [], ['currency' => strtoupper($asset), 'amount' => (string)$amount]);
        return ['asset' => strtoupper($asset), 'unstaked' => $amount, 'status' => 'UNSTAKED'];
    }

    public function getStakingPositions(): array
    {
        $res = $this->priv('GET', GateioConfig::EARN_POSITIONS);
        return array_map(fn($p) => ['asset' => $p['currency'] ?? '', 'amount' => (float)($p['amount'] ?? 0), 'apy' => $p['current_rate'] ?? '0', 'status' => 'ACTIVE'], $res);
    }
}
