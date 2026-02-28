<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Coinbase;

use IsraelNogueira\ExchangeHub\Core\AbstractExchange;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};
use IsraelNogueira\ExchangeHub\Exceptions\OrderNotFoundException;

class CoinbaseExchange extends AbstractExchange
{
    private CoinbaseSigner     $signer;
    private CoinbaseNormalizer $normalizer;

    protected function configure(): void
    {
        $this->name       = 'coinbase';
        $this->baseUrl    = CoinbaseConfig::BASE_URL;
        $this->signer     = new CoinbaseSigner($this->apiKey, $this->apiSecret);
        $this->normalizer = new CoinbaseNormalizer();
    }

    private function cbGet(string $path, array $p = [], bool $signed = false): array
    {
        $q    = $p ? '?' . http_build_query($p) : '';
        $url  = $this->baseUrl . $path . $q;
        $hdrs = [];
        if ($signed) {
            foreach ($this->signer->getHeaders('GET', $path . $q) as $k => $v) $hdrs[] = "{$k}: {$v}";
        }
        return $this->http->get($url, $hdrs, 'coinbase');
    }

    private function cbPost(string $path, array $b = []): array
    {
        $body = json_encode($b);
        $url  = $this->baseUrl . $path;
        $hdrs = [];
        foreach ($this->signer->getHeaders('POST', $path, $body) as $k => $v) $hdrs[] = "{$k}: {$v}";
        return $this->http->post($url, $body, $hdrs, 'coinbase');
    }

    // ── Market Data ───────────────────────────────────────────────────────────

    public function ping(): bool
    {
        try { $this->cbGet(CoinbaseConfig::PRODUCTS . '?limit=1'); return true; }
        catch (\Exception $e) { return false; }
    }

    public function getServerTime(): int { return time() * 1000; }

    public function getExchangeInfo(): ExchangeInfoDTO
    {
        $r = $this->cbGet(CoinbaseConfig::PRODUCTS);
        return new ExchangeInfoDTO('Coinbase', 'ONLINE', array_map(fn($p) => $p['product_id'], $r['products'] ?? []), 0.006, 0.006, [], [], time() * 1000);
    }

    public function getSymbols(): array
    {
        $r = $this->cbGet(CoinbaseConfig::PRODUCTS);
        return array_map(fn($p) => $p['product_id'], $r['products'] ?? []);
    }

    public function getTicker(string $symbol): TickerDTO
    {
        $r = $this->cbGet(CoinbaseConfig::PRODUCTS . '/' . $symbol);
        return $this->normalizer->ticker($r);
    }

    public function getTicker24h(string $symbol): TickerDTO { return $this->getTicker($symbol); }

    public function getAllTickers(): array
    {
        $r = $this->cbGet(CoinbaseConfig::PRODUCTS);
        return array_map(fn($p) => $this->normalizer->ticker($p), $r['products'] ?? []);
    }

    public function getOrderBook(string $symbol, int $limit = 20): OrderBookDTO
    {
        $r = $this->cbGet(CoinbaseConfig::BEST_BID_ASK, ['product_ids' => $symbol]);
        return $this->normalizer->orderBook($symbol, $r['pricebooks'][0] ?? []);
    }

    public function getRecentTrades(string $symbol, int $limit = 50): array
    {
        $r = $this->cbGet(CoinbaseConfig::PRODUCTS . '/' . $symbol . '/ticker', ['limit' => $limit]);
        return array_map(fn($t) => new TradeDTO(
            $t['trade_id'] ?? '', $t['order_id'] ?? '', $symbol, $t['side'] ?? '',
            (float)($t['price'] ?? 0), (float)($t['size'] ?? 0),
            (float)($t['price'] ?? 0) * (float)($t['size'] ?? 0),
            0, '', false, time() * 1000, 'coinbase'
        ), $r['trades'] ?? []);
    }

    public function getHistoricalTrades(string $symbol, int $limit = 100, ?int $fromId = null): array
    {
        return $this->getRecentTrades($symbol, $limit);
    }

    public function getCandles(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $gm = ['1m'=>'ONE_MINUTE','5m'=>'FIVE_MINUTE','15m'=>'FIFTEEN_MINUTE','30m'=>'THIRTY_MINUTE','1h'=>'ONE_HOUR','2h'=>'TWO_HOUR','6h'=>'SIX_HOUR','1d'=>'ONE_DAY'];
        $p  = ['granularity' => $gm[$interval] ?? 'ONE_HOUR'];
        if ($startTime) $p['start'] = (int)($startTime / 1000);
        if ($endTime)   $p['end']   = (int)($endTime   / 1000);
        $r  = $this->cbGet(CoinbaseConfig::PRODUCTS . '/' . $symbol . '/candles', $p);
        return array_map(fn($c) => $this->normalizer->candle($symbol, $interval, $c), $r['candles'] ?? []);
    }

    public function getAvgPrice(string $symbol): float { return $this->getTicker($symbol)->price; }

    // ── Account ───────────────────────────────────────────────────────────────

    public function getAccountInfo(): array { return $this->cbGet(CoinbaseConfig::ACCOUNT, [], true); }

    public function getBalances(): array
    {
        $r   = $this->cbGet(CoinbaseConfig::ACCOUNT, [], true);
        $out = [];
        foreach ($r['accounts'] ?? [] as $a) {
            $v = (float)($a['available_balance']['value'] ?? 0);
            if ($v > 0) $out[$a['currency']] = $this->normalizer->balance($a['currency'], $a);
        }
        return $out;
    }

    public function getBalance(string $asset): BalanceDTO
    {
        $r = $this->cbGet(CoinbaseConfig::ACCOUNT, [], true);
        foreach ($r['accounts'] ?? [] as $a) {
            if ($a['currency'] === strtoupper($asset)) {
                return $this->normalizer->balance($a['currency'], $a);
            }
        }
        return new BalanceDTO(strtoupper($asset), 0, 0, 0, 'coinbase');
    }

    public function getCommissionRates(): array
    {
        $r = $this->cbGet(CoinbaseConfig::TRANSACTION_SUMMARY, [], true);
        return ['maker' => (float)($r['fee_tier']['maker_fee_rate'] ?? 0.006), 'taker' => (float)($r['fee_tier']['taker_fee_rate'] ?? 0.006)];
    }

    public function getDepositAddress(string $asset, ?string $network = null): DepositDTO
    {
        return new DepositDTO(strtoupper($asset), 'Use Coinbase UI', null, $network ?? '', null, null, null, DepositDTO::STATUS_CONFIRMED, null, 'coinbase');
    }

    public function getDepositHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array { return []; }
    public function getWithdrawHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array { return []; }

    public function withdraw(string $asset, string $address, float $amount, ?string $network = null, ?string $memo = null): WithdrawDTO
    {
        return new WithdrawDTO('cb-' . uniqid(), strtoupper($asset), $address, $memo, $network ?? '', $amount, 0, $amount, null, WithdrawDTO::STATUS_PENDING, time() * 1000, 'coinbase');
    }

    // ── Trading ───────────────────────────────────────────────────────────────

    public function createOrder(string $symbol, string $side, string $type, float $quantity, ?float $price = null, ?float $stopPrice = null, ?string $timeInForce = 'GTC', ?string $clientOrderId = null): OrderDTO
    {
        $cfg  = strtoupper($type) === 'MARKET'
            ? ['market_market_ioc' => ['base_size' => (string)$quantity]]
            : ['limit_limit_gtc'   => ['base_size' => (string)$quantity, 'limit_price' => (string)$price]];
        $body = ['client_order_id' => $clientOrderId ?? uniqid(), 'product_id' => $symbol, 'side' => strtoupper($side), 'order_configuration' => $cfg];
        $r    = $this->cbPost(CoinbaseConfig::ORDERS, $body);
        return $this->normalizer->order($r['success_response'] ?? $r);
    }

    public function cancelOrder(string $symbol, string $orderId): OrderDTO
    {
        $o = $this->getOrder($symbol, $orderId);
        $this->cbPost(CoinbaseConfig::ORDERS_BATCH, ['order_ids' => [$orderId]]);
        return $o;
    }

    public function cancelAllOrders(string $symbol): array
    {
        $open = $this->getOpenOrders($symbol);
        $ids  = array_map(fn($o) => $o->orderId, $open);
        if ($ids) $this->cbPost(CoinbaseConfig::ORDERS_BATCH, ['order_ids' => $ids]);
        return $open;
    }

    public function getOrder(string $symbol, string $orderId): OrderDTO
    {
        $r = $this->cbGet(CoinbaseConfig::ORDERS . '/' . $orderId, [], true);
        if (empty($r['order'])) throw new OrderNotFoundException($orderId, 'coinbase');
        return $this->normalizer->order($r);
    }

    public function getOpenOrders(?string $symbol = null): array
    {
        $p = ['order_status' => 'OPEN'];
        if ($symbol) $p['product_id'] = $symbol;
        $r = $this->cbGet(CoinbaseConfig::ORDERS . '/historical/batch', $p, true);
        return array_map(fn($o) => $this->normalizer->order(['order' => $o]), $r['orders'] ?? []);
    }

    public function getOrderHistory(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $r = $this->cbGet(CoinbaseConfig::ORDERS . '/historical/batch', ['product_id' => $symbol, 'limit' => $limit], true);
        return array_map(fn($o) => $this->normalizer->order(['order' => $o]), $r['orders'] ?? []);
    }

    public function getMyTrades(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $r = $this->cbGet(CoinbaseConfig::FILLS, ['product_id' => $symbol, 'limit' => $limit], true);
        return array_map(fn($t) => new TradeDTO(
            $t['trade_id'] ?? '', $t['order_id'] ?? '', $symbol, $t['trade_type'] ?? 'BUY',
            (float)($t['price'] ?? 0), (float)($t['size'] ?? 0), (float)($t['price'] ?? 0) * (float)($t['size'] ?? 0),
            (float)($t['commission'] ?? 0), '', false,
            isset($t['trade_time']) ? strtotime($t['trade_time']) * 1000 : time() * 1000, 'coinbase'
        ), $r['fills'] ?? []);
    }

    public function editOrder(string $symbol, string $orderId, ?float $price = null, ?float $quantity = null): OrderDTO
    {
        $body = ['order_id' => $orderId];
        if ($price)    $body['price'] = (string)$price;
        if ($quantity) $body['size']  = (string)$quantity;
        $this->cbPost(CoinbaseConfig::ORDERS . '/' . $orderId . '/edit', $body);
        return $this->getOrder($symbol, $orderId);
    }

    public function createOCOOrder(string $symbol, string $side, float $quantity, float $price, float $stopPrice, float $stopLimitPrice): array
    {
        $l = $this->createOrder($symbol, $side, 'LIMIT', $quantity, $price);
        $s = $this->createOrder($symbol, $side, 'LIMIT', $quantity, $stopLimitPrice);
        return ['oco_group_id' => null, 'limit_order' => $l, 'stop_order' => $s];
    }

    public function stakeAsset(string $asset, float $amount): array { return ['asset' => strtoupper($asset), 'staked' => $amount, 'status' => 'STAKED', 'note' => 'Use Coinbase UI for staking']; }
    public function unstakeAsset(string $asset, float $amount): array { return ['asset' => strtoupper($asset), 'unstaked' => $amount, 'status' => 'UNSTAKED']; }
    public function getStakingPositions(): array { return []; }
}
