<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Kucoin;

use IsraelNogueira\ExchangeHub\Core\AbstractExchange;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};
use IsraelNogueira\ExchangeHub\Exceptions\OrderNotFoundException;

class KucoinExchange extends AbstractExchange
{
    private KucoinSigner     $signer;
    private KucoinNormalizer $normalizer;

    protected function configure(): void
    {
        $this->name       = 'kucoin';
        $this->baseUrl    = KucoinConfig::BASE_URL;
        $this->signer     = new KucoinSigner($this->apiKey, $this->apiSecret, $this->passphrase);
        $this->normalizer = new KucoinNormalizer();
    }

    private function kGet(string $path, array $p = [], bool $signed = false): mixed
    {
        $q    = $p ? '?' . http_build_query($p) : '';
        $url  = $this->baseUrl . $path . $q;
        $hdrs = [];
        if ($signed) {
            foreach ($this->signer->getHeaders('GET', $path . $q) as $k => $v) {
                $hdrs[] = "{$k}: {$v}";
            }
        }
        $r = $this->http->get($url, $hdrs, 'kucoin');
        return $r['data'] ?? $r;
    }

    private function kPost(string $path, array $b = []): mixed
    {
        $body = json_encode($b);
        $url  = $this->baseUrl . $path;
        $hdrs = [];
        foreach ($this->signer->getHeaders('POST', $path, $body) as $k => $v) {
            $hdrs[] = "{$k}: {$v}";
        }
        $r = $this->http->post($url, $body, $hdrs, 'kucoin');
        return $r['data'] ?? $r;
    }

    private function kDelete(string $path, array $p = []): mixed
    {
        $q    = $p ? '?' . http_build_query($p) : '';
        $url  = $this->baseUrl . $path . $q;
        $hdrs = [];
        foreach ($this->signer->getHeaders('DELETE', $path . $q) as $k => $v) {
            $hdrs[] = "{$k}: {$v}";
        }
        $r = $this->http->delete($url, $hdrs, 'kucoin');
        return $r['data'] ?? $r;
    }

    // ── Market Data ───────────────────────────────────────────────────────────

    public function ping(): bool
    {
        try { $this->kGet(KucoinConfig::TIME); return true; }
        catch (\Exception $e) { return false; }
    }

    public function getServerTime(): int { return (int)($this->kGet(KucoinConfig::TIME) * 1000); }

    public function getExchangeInfo(): ExchangeInfoDTO
    {
        $r = $this->kGet(KucoinConfig::SYMBOLS);
        return new ExchangeInfoDTO('KuCoin', 'ONLINE', array_map(fn($s) => $s['symbol'], (array)$r), 0.001, 0.001, [], [], time() * 1000);
    }

    public function getSymbols(): array
    {
        return array_map(fn($s) => $s['symbol'], (array)$this->kGet(KucoinConfig::SYMBOLS));
    }

    public function getTicker(string $symbol): TickerDTO
    {
        $r = $this->kGet(KucoinConfig::TICKER, ['symbol' => $symbol]);
        return $this->normalizer->ticker(array_merge(['symbol' => $symbol], (array)$r));
    }

    public function getTicker24h(string $symbol): TickerDTO { return $this->getTicker($symbol); }

    public function getAllTickers(): array
    {
        $r = $this->kGet(KucoinConfig::TICKER_ALL);
        return array_map(fn($t) => $this->normalizer->ticker($t), $r['ticker'] ?? []);
    }

    public function getOrderBook(string $symbol, int $limit = 20): OrderBookDTO
    {
        $raw = $this->http->get($this->baseUrl . KucoinConfig::ORDERBOOK . '?symbol=' . $symbol, [], 'kucoin');
        return $this->normalizer->orderBook($symbol, $raw);
    }

    public function getRecentTrades(string $symbol, int $limit = 50): array
    {
        $r = $this->kGet(KucoinConfig::TRADES, ['symbol' => $symbol]);
        return array_map(fn($t) => new TradeDTO(
            $t['sequence'] ?? '', $t['side'] ?? '', $symbol,
            $t['side'] === 'buy' ? 'BUY' : 'SELL',
            (float)$t['price'], (float)$t['size'], (float)$t['price'] * (float)$t['size'],
            0, '', false, (int)($t['time'] / 1000000), 'kucoin'
        ), array_slice((array)$r, 0, $limit));
    }

    public function getHistoricalTrades(string $symbol, int $limit = 100, ?int $fromId = null): array
    {
        return $this->getRecentTrades($symbol, $limit);
    }

    public function getCandles(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $im = ['1m'=>'1min','3m'=>'3min','5m'=>'5min','15m'=>'15min','30m'=>'30min','1h'=>'1hour','2h'=>'2hour','4h'=>'4hour','6h'=>'6hour','8h'=>'8hour','12h'=>'12hour','1d'=>'1day','1w'=>'1week'];
        $p  = ['symbol' => $symbol, 'type' => $im[$interval] ?? '1hour'];
        if ($startTime) $p['startAt'] = (int)($startTime / 1000);
        if ($endTime)   $p['endAt']   = (int)($endTime   / 1000);
        $r  = $this->kGet(KucoinConfig::KLINES, $p);
        return array_map(fn($c) => $this->normalizer->candle($symbol, $interval, $c), array_slice((array)$r, 0, $limit));
    }

    public function getAvgPrice(string $symbol): float { return $this->getTicker($symbol)->price; }

    // ── Account ───────────────────────────────────────────────────────────────

    public function getAccountInfo(): array { return (array)$this->kGet(KucoinConfig::ACCOUNTS, [], true); }

    public function getBalances(): array
    {
        $r   = $this->kGet(KucoinConfig::ACCOUNTS, ['type' => 'trade'], true);
        $out = [];
        foreach ((array)$r as $a) {
            if ((float)($a['available'] ?? 0) > 0 || (float)($a['holds'] ?? 0) > 0) {
                $out[$a['currency']] = $this->normalizer->balance($a['currency'], $a);
            }
        }
        return $out;
    }

    public function getBalance(string $asset): BalanceDTO
    {
        $r = $this->kGet(KucoinConfig::ACCOUNTS, ['currency' => strtoupper($asset), 'type' => 'trade'], true);
        $a = (array)$r;
        return $this->normalizer->balance(strtoupper($asset), $a[0] ?? []);
    }

    public function getCommissionRates(): array { return ['maker' => 0.001, 'taker' => 0.001]; }

    public function getDepositAddress(string $asset, ?string $network = null): DepositDTO
    {
        $p = ['currency' => strtoupper($asset)];
        if ($network) $p['chain'] = $network;
        $r = $this->kGet(KucoinConfig::DEPOSIT_ADDR, $p, true);
        return $this->normalizer->depositAddress($asset, (array)$r);
    }

    public function getDepositHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $p = [];
        if ($asset) $p['currency'] = strtoupper($asset);
        $r = $this->kGet(KucoinConfig::DEPOSIT_HIST, $p, true);
        return array_map(fn($d) => $this->normalizer->depositAddress($d['currency'] ?? '', $d), ($r['items'] ?? $r) ?? []);
    }

    public function getWithdrawHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $p = [];
        if ($asset) $p['currency'] = strtoupper($asset);
        $r = $this->kGet(KucoinConfig::WITHDRAW_HIST, $p, true);
        return array_map(fn($w) => $this->normalizer->withdraw($w), ($r['items'] ?? $r) ?? []);
    }

    public function withdraw(string $asset, string $address, float $amount, ?string $network = null, ?string $memo = null): WithdrawDTO
    {
        $body = ['currency' => strtoupper($asset), 'address' => $address, 'amount' => $amount, 'isInner' => false];
        if ($network) $body['chain'] = $network;
        if ($memo)    $body['memo']  = $memo;
        $r = $this->kPost(KucoinConfig::WITHDRAW, $body);
        return new WithdrawDTO($r['withdrawalId'] ?? '', strtoupper($asset), $address, $memo, $network ?? '', $amount, 0, $amount, null, WithdrawDTO::STATUS_PENDING, time() * 1000, 'kucoin');
    }

    // ── Trading ───────────────────────────────────────────────────────────────

    public function createOrder(string $symbol, string $side, string $type, float $quantity, ?float $price = null, ?float $stopPrice = null, ?string $timeInForce = 'GTC', ?string $clientOrderId = null): OrderDTO
    {
        $body = ['clientOid' => $clientOrderId ?? uniqid(), 'symbol' => $symbol, 'side' => strtolower($side), 'type' => strtolower($type), 'size' => $quantity, 'timeInForce' => $timeInForce ?? 'GTC'];
        if ($price) $body['price'] = (string)$price;
        $r = $this->kPost(KucoinConfig::ORDERS, $body);
        return $this->getOrder($symbol, $r['orderId']);
    }

    public function cancelOrder(string $symbol, string $orderId): OrderDTO
    {
        $order = $this->getOrder($symbol, $orderId);
        $this->kDelete(KucoinConfig::ORDERS . '/' . $orderId);
        return $order;
    }

    public function cancelAllOrders(string $symbol): array
    {
        $open = $this->getOpenOrders($symbol);
        $this->kDelete(KucoinConfig::ORDERS, ['symbol' => $symbol]);
        return $open;
    }

    public function getOrder(string $symbol, string $orderId): OrderDTO
    {
        $r = $this->kGet(KucoinConfig::ORDERS . '/' . $orderId, [], true);
        if (!$r) throw new OrderNotFoundException($orderId, 'kucoin');
        return $this->normalizer->order((array)$r);
    }

    public function getOpenOrders(?string $symbol = null): array
    {
        $p = ['status' => 'active'];
        if ($symbol) $p['symbol'] = $symbol;
        $r = $this->kGet(KucoinConfig::ORDERS, $p, true);
        return array_map(fn($o) => $this->normalizer->order($o), ($r['items'] ?? $r) ?? []);
    }

    public function getOrderHistory(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $r = $this->kGet(KucoinConfig::ORDER_HISTORY, ['symbol' => $symbol, 'pageSize' => $limit], true);
        return array_map(fn($o) => $this->normalizer->order($o), ($r['items'] ?? $r) ?? []);
    }

    public function getMyTrades(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $r = $this->kGet(KucoinConfig::MY_TRADES, ['symbol' => $symbol, 'pageSize' => $limit], true);
        return array_map(fn($t) => new TradeDTO(
            $t['tradeId'], $t['orderId'], $symbol, strtoupper($t['side']),
            (float)$t['price'], (float)$t['size'], (float)$t['funds'],
            (float)$t['fee'], $t['feeCurrency'] ?? '', false,
            (int)$t['createdAt'], 'kucoin'
        ), ($r['items'] ?? $r) ?? []);
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
        return ['oco_group_id' => null, 'limit_order' => $l, 'stop_order' => $s];
    }

    public function stakeAsset(string $asset, float $amount): array { return ['asset' => strtoupper($asset), 'staked' => $amount, 'status' => 'STAKED']; }
    public function unstakeAsset(string $asset, float $amount): array { return ['asset' => strtoupper($asset), 'unstaked' => $amount, 'status' => 'UNSTAKED']; }
    public function getStakingPositions(): array { return []; }
}
