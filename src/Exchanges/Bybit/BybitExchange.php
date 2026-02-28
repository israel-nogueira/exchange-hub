<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Bybit;

use IsraelNogueira\ExchangeHub\Core\AbstractExchange;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};
use IsraelNogueira\ExchangeHub\Exceptions\OrderNotFoundException;

class BybitExchange extends AbstractExchange
{
    private BybitSigner     $signer;
    private BybitNormalizer $normalizer;

    protected function configure(): void
    {
        $this->name       = 'bybit';
        $this->baseUrl    = $this->testnet ? BybitConfig::TESTNET_URL : BybitConfig::BASE_URL;
        $this->signer     = new BybitSigner($this->apiKey, $this->apiSecret);
        $this->normalizer = new BybitNormalizer();
    }

    private function bGet(string $path, array $params = [], bool $signed = false): array
    {
        $s    = $signed ? $this->signer->signGet($params) : ['params' => $params, 'headers' => []];
        $q    = $s['params'] ? '?' . http_build_query($s['params']) : '';
        $url  = $this->baseUrl . $path . $q;
        $hdrs = [];
        foreach ($s['headers'] as $k => $v) $hdrs[] = "{$k}: {$v}";
        $r = $this->http->get($url, $hdrs, 'bybit');
        return $r['result'] ?? $r;
    }

    private function bPost(string $path, array $body = []): array
    {
        $s    = $this->signer->signPost($body);
        $url  = $this->baseUrl . $path;
        $hdrs = [];
        foreach ($s['headers'] as $k => $v) $hdrs[] = "{$k}: {$v}";
        $r = $this->http->post($url, json_encode($s['body']), $hdrs, 'bybit');
        return $r['result'] ?? $r;
    }

    // ── Market Data ───────────────────────────────────────────────────────────

    public function ping(): bool
    {
        try {
            $this->bGet(BybitConfig::TICKER, ['category' => 'spot', 'symbol' => 'BTCUSDT']);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getServerTime(): int { return time() * 1000; }

    public function getExchangeInfo(): ExchangeInfoDTO
    {
        $r = $this->bGet(BybitConfig::INSTRUMENTS, ['category' => 'spot', 'limit' => 1000]);
        return new ExchangeInfoDTO('Bybit', 'ONLINE', array_map(fn($s) => $s['symbol'], $r['list'] ?? []), 0.001, 0.001, [], [], time() * 1000);
    }

    public function getSymbols(): array
    {
        $r = $this->bGet(BybitConfig::INSTRUMENTS, ['category' => 'spot']);
        return array_map(fn($s) => $s['symbol'], $r['list'] ?? []);
    }

    public function getTicker(string $symbol): TickerDTO
    {
        $r = $this->bGet(BybitConfig::TICKER, ['category' => 'spot', 'symbol' => $symbol]);
        return $this->normalizer->ticker($r['list'][0] ?? []);
    }

    public function getTicker24h(string $symbol): TickerDTO { return $this->getTicker($symbol); }

    public function getAllTickers(): array
    {
        $r = $this->bGet(BybitConfig::TICKER, ['category' => 'spot']);
        return array_map(fn($t) => $this->normalizer->ticker($t), $r['list'] ?? []);
    }

    public function getOrderBook(string $symbol, int $limit = 20): OrderBookDTO
    {
        $url = $this->baseUrl . BybitConfig::ORDERBOOK . '?category=spot&symbol=' . $symbol . '&limit=' . $limit;
        $raw = $this->http->get($url, [], 'bybit');
        return $this->normalizer->orderBook($symbol, $raw);
    }

    public function getRecentTrades(string $symbol, int $limit = 50): array
    {
        $r = $this->bGet(BybitConfig::RECENT_TRADES, ['category' => 'spot', 'symbol' => $symbol, 'limit' => $limit]);
        return array_map(fn($t) => new TradeDTO(
            $t['execId'] ?? '', $t['orderId'] ?? '', $symbol, $t['side'],
            (float)$t['price'], (float)$t['size'], (float)$t['price'] * (float)$t['size'],
            0, '', false, (int)$t['time'], 'bybit'
        ), $r['list'] ?? []);
    }

    public function getHistoricalTrades(string $symbol, int $limit = 100, ?int $fromId = null): array
    {
        return $this->getRecentTrades($symbol, $limit);
    }

    public function getCandles(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $im = ['1m'=>'1','3m'=>'3','5m'=>'5','15m'=>'15','30m'=>'30','1h'=>'60','2h'=>'120','4h'=>'240','6h'=>'360','12h'=>'720','1d'=>'D','1w'=>'W','1M'=>'M'];
        $r  = $this->bGet(BybitConfig::KLINE, ['category' => 'spot', 'symbol' => $symbol, 'interval' => $im[$interval] ?? '60', 'limit' => $limit]);
        return array_map(fn($c) => $this->normalizer->candle($symbol, $interval, $c), $r['list'] ?? []);
    }

    public function getAvgPrice(string $symbol): float { return $this->getTicker($symbol)->price; }

    // ── Account ───────────────────────────────────────────────────────────────

    public function getAccountInfo(): array
    {
        return $this->bGet(BybitConfig::WALLET_BALANCE, ['accountType' => 'UNIFIED'], true);
    }

    public function getBalances(): array
    {
        $r   = $this->bGet(BybitConfig::WALLET_BALANCE, ['accountType' => 'UNIFIED'], true);
        $out = [];
        foreach ($r['list'][0]['coin'] ?? [] as $c) {
            if ((float)$c['walletBalance'] > 0) {
                $out[$c['coin']] = $this->normalizer->balance($c['coin'], $c);
            }
        }
        return $out;
    }

    public function getBalance(string $asset): BalanceDTO
    {
        $r = $this->bGet(BybitConfig::WALLET_BALANCE, ['accountType' => 'UNIFIED', 'coin' => strtoupper($asset)], true);
        $c = $r['list'][0]['coin'][0] ?? [];
        return $this->normalizer->balance(strtoupper($asset), $c);
    }

    public function getCommissionRates(): array { return ['maker' => 0.001, 'taker' => 0.001]; }

    public function getDepositAddress(string $asset, ?string $network = null): DepositDTO
    {
        $params = ['coin' => strtoupper($asset), 'chainType' => $network ?? ''];
        $r      = $this->bGet(BybitConfig::DEPOSIT_ADDR, $params, true);
        return $this->normalizer->depositAddress($asset, $r['chains'][0] ?? []);
    }

    public function getDepositHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $params = ['limit' => 50];
        if ($asset) $params['coin'] = strtoupper($asset);
        $r = $this->bGet(BybitConfig::DEPOSIT_RECORDS, $params, true);
        return array_map(fn($d) => $this->normalizer->depositAddress($d['coin'] ?? '', $d), $r['rows'] ?? []);
    }

    public function getWithdrawHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $params = ['limit' => 50];
        if ($asset) $params['coin'] = strtoupper($asset);
        $r = $this->bGet(BybitConfig::WITHDRAW_RECORDS, $params, true);
        return array_map(fn($w) => $this->normalizer->withdraw($w), $r['rows'] ?? []);
    }

    public function withdraw(string $asset, string $address, float $amount, ?string $network = null, ?string $memo = null): WithdrawDTO
    {
        $body = ['coin' => strtoupper($asset), 'address' => $address, 'amount' => $amount, 'accountType' => 'FUND'];
        if ($network) $body['chain'] = $network;
        if ($memo)    $body['tag']   = $memo;
        $r = $this->bPost(BybitConfig::WITHDRAW, $body);
        return new WithdrawDTO($r['id'] ?? '', strtoupper($asset), $address, $memo, $network ?? '', $amount, 0, $amount, null, WithdrawDTO::STATUS_PENDING, time() * 1000, 'bybit');
    }

    // ── Trading ───────────────────────────────────────────────────────────────

    public function createOrder(string $symbol, string $side, string $type, float $quantity, ?float $price = null, ?float $stopPrice = null, ?string $timeInForce = 'GTC', ?string $clientOrderId = null): OrderDTO
    {
        $body = ['category' => 'spot', 'symbol' => $symbol, 'side' => ucfirst(strtolower($side)), 'orderType' => ucfirst(strtolower($type)), 'qty' => (string)$quantity, 'timeInForce' => $timeInForce ?? 'GTC'];
        if ($price)         $body['price']        = (string)$price;
        if ($stopPrice)     $body['triggerPrice']  = (string)$stopPrice;
        if ($clientOrderId) $body['orderLinkId']   = $clientOrderId;
        $r = $this->bPost(BybitConfig::ORDER, $body);
        return $this->getOrder($symbol, $r['orderId'] ?? '');
    }

    public function cancelOrder(string $symbol, string $orderId): OrderDTO
    {
        $order = $this->getOrder($symbol, $orderId);
        $this->bPost(BybitConfig::CANCEL_ORDER, ['category' => 'spot', 'symbol' => $symbol, 'orderId' => $orderId]);
        return $order;
    }

    public function cancelAllOrders(string $symbol): array
    {
        $open = $this->getOpenOrders($symbol);
        $this->bPost(BybitConfig::CANCEL_ALL, ['category' => 'spot', 'symbol' => $symbol]);
        return $open;
    }

    public function getOrder(string $symbol, string $orderId): OrderDTO
    {
        $r = $this->bGet(BybitConfig::ORDER_REALTIME, ['category' => 'spot', 'symbol' => $symbol, 'orderId' => $orderId], true);
        if (empty($r['list'][0])) throw new OrderNotFoundException($orderId, 'bybit');
        return $this->normalizer->order($r['list'][0]);
    }

    public function getOpenOrders(?string $symbol = null): array
    {
        $params = ['category' => 'spot'];
        if ($symbol) $params['symbol'] = $symbol;
        $r = $this->bGet(BybitConfig::ORDER_REALTIME, $params, true);
        return array_map(fn($o) => $this->normalizer->order($o), $r['list'] ?? []);
    }

    public function getOrderHistory(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $r = $this->bGet(BybitConfig::ORDER_HISTORY, ['category' => 'spot', 'symbol' => $symbol, 'limit' => $limit], true);
        return array_map(fn($o) => $this->normalizer->order($o), $r['list'] ?? []);
    }

    public function getMyTrades(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $r = $this->bGet(BybitConfig::MY_TRADES, ['category' => 'spot', 'symbol' => $symbol, 'limit' => $limit], true);
        return array_map(fn($t) => new TradeDTO(
            $t['execId'], $t['orderId'], $symbol, strtoupper($t['side']),
            (float)$t['execPrice'], (float)$t['execQty'], (float)$t['execValue'],
            (float)$t['execFee'], $t['feeCurrency'] ?? '', $t['isMaker'] ?? false,
            (int)$t['execTime'], 'bybit'
        ), $r['list'] ?? []);
    }

    public function editOrder(string $symbol, string $orderId, ?float $price = null, ?float $quantity = null): OrderDTO
    {
        $body = ['category' => 'spot', 'symbol' => $symbol, 'orderId' => $orderId];
        if ($price)    $body['price'] = (string)$price;
        if ($quantity) $body['qty']   = (string)$quantity;
        $r = $this->bPost(BybitConfig::AMEND_ORDER, $body);
        return $this->getOrder($symbol, $r['orderId'] ?? $orderId);
    }

    public function createOCOOrder(string $symbol, string $side, float $quantity, float $price, float $stopPrice, float $stopLimitPrice): array
    {
        $limit = $this->createOrder($symbol, $side, 'LIMIT', $quantity, $price);
        $stop  = $this->createOrder($symbol, $side, 'LIMIT', $quantity, $stopLimitPrice);
        return ['oco_group_id' => null, 'limit_order' => $limit, 'stop_order' => $stop];
    }

    public function stakeAsset(string $asset, float $amount): array
    {
        return ['asset' => strtoupper($asset), 'staked' => $amount, 'status' => 'UNSUPPORTED'];
    }

    public function unstakeAsset(string $asset, float $amount): array
    {
        return ['asset' => strtoupper($asset), 'unstaked' => $amount, 'status' => 'UNSUPPORTED'];
    }

    public function getStakingPositions(): array { return []; }
}
