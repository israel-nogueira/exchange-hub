<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Okx;

use IsraelNogueira\ExchangeHub\Core\AbstractExchange;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};
use IsraelNogueira\ExchangeHub\Exceptions\OrderNotFoundException;

class OkxExchange extends AbstractExchange
{
    private OkxSigner     $signer;
    private OkxNormalizer $normalizer;
    private bool          $demo = false;

    protected function configure(): void
    {
        $this->name       = 'okx';
        $this->baseUrl    = OkxConfig::HOST . OkxConfig::BASE_URL;
        $this->demo       = $this->options['demo'] ?? $this->testnet;
        $this->signer     = new OkxSigner($this->apiKey, $this->apiSecret, $this->passphrase);
        $this->normalizer = new OkxNormalizer();
    }

    // ── Helpers HTTP específicos da OKX ───────────────────────────────────────

    private function okxGet(string $path, array $params = [], bool $signed = false): array
    {
        $query   = $params ? '?' . http_build_query($params) : '';
        $url     = $this->baseUrl . $path . $query;
        $headers = $signed
            ? $this->signer->getHeaders('GET', OkxConfig::BASE_URL . $path . $query, '', $this->demo)
            : ['Content-Type: application/json'];

        $hdrs = [];
        foreach ($headers as $k => $v) {
            $hdrs[] = is_int($k) ? $v : "{$k}: {$v}";
        }

        $res = $this->http->get($url, $hdrs, 'okx');
        return $res['data'] ?? $res;
    }

    private function okxPost(string $path, array $body = []): array
    {
        $bodyStr = json_encode($body);
        $url     = $this->baseUrl . $path;
        $headers = $this->signer->getHeaders('POST', OkxConfig::BASE_URL . $path, $bodyStr, $this->demo);

        $hdrs = [];
        foreach ($headers as $k => $v) {
            $hdrs[] = "{$k}: {$v}";
        }

        $res = $this->http->post($url, $bodyStr, $hdrs, 'okx');
        return $res['data'] ?? $res;
    }

    // ── Market Data ───────────────────────────────────────────────────────────

    public function ping(): bool
    {
        try {
            $this->okxGet(OkxConfig::TICKERS, ['instType' => 'SPOT']);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getServerTime(): int
    {
        return time() * 1000;
    }

    public function getExchangeInfo(): ExchangeInfoDTO
    {
        $res     = $this->okxGet(OkxConfig::INSTRUMENTS, ['instType' => 'SPOT']);
        $symbols = array_map(fn($s) => $s['instId'], $res);
        return new ExchangeInfoDTO('OKX', 'ONLINE', $symbols, 0.0008, 0.001, [], [], time() * 1000);
    }

    public function getSymbols(): array
    {
        $res = $this->okxGet(OkxConfig::INSTRUMENTS, ['instType' => 'SPOT']);
        return array_map(fn($s) => $s['instId'], $res);
    }

    public function getTicker(string $symbol): TickerDTO
    {
        $res = $this->okxGet(OkxConfig::TICKER, ['instId' => $symbol]);
        return $this->normalizer->ticker($res[0] ?? []);
    }

    public function getTicker24h(string $symbol): TickerDTO
    {
        return $this->getTicker($symbol);
    }

    public function getAllTickers(): array
    {
        $res = $this->okxGet(OkxConfig::TICKERS, ['instType' => 'SPOT']);
        return array_map(fn($t) => $this->normalizer->ticker($t), $res);
    }

    public function getOrderBook(string $symbol, int $limit = 20): OrderBookDTO
    {
        $url = $this->baseUrl . OkxConfig::BOOKS . '?instId=' . $symbol . '&sz=' . $limit;
        $raw = $this->http->get($url, [], 'okx');
        return $this->normalizer->orderBook($raw, $symbol);
    }

    public function getRecentTrades(string $symbol, int $limit = 50): array
    {
        $res = $this->okxGet(OkxConfig::TRADES, ['instId' => $symbol, 'limit' => $limit]);
        return array_map(fn($t) => new TradeDTO($t['tradeId'], '', $symbol, strtoupper($t['side']), (float)$t['px'], (float)$t['sz'], (float)$t['px'] * (float)$t['sz'], 0, '', false, (int)$t['ts'], 'okx'), $res);
    }

    public function getHistoricalTrades(string $symbol, int $limit = 100, ?int $fromId = null): array
    {
        return $this->getRecentTrades($symbol, $limit);
    }

    public function getCandles(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $okxInterval = OkxConfig::INTERVAL_MAP[$interval] ?? '1H';
        $params      = $this->filterNulls(['instId' => $symbol, 'bar' => $okxInterval, 'limit' => $limit, 'before' => $startTime, 'after' => $endTime]);
        $res         = $this->okxGet(OkxConfig::CANDLES, $params);
        return array_map(fn($c) => $this->normalizer->candle($symbol, $interval, $c), $res);
    }

    public function getAvgPrice(string $symbol): float
    {
        return $this->getTicker($symbol)->price;
    }

    // ── Account ───────────────────────────────────────────────────────────────

    public function getAccountInfo(): array
    {
        return $this->okxGet(OkxConfig::ACCOUNT_INFO, [], true);
    }

    public function getBalances(): array
    {
        $res    = $this->okxGet(OkxConfig::ACCOUNT_BALANCE, [], true);
        $result = [];
        foreach ($res[0]['details'] ?? [] as $b) {
            $asset  = $b['ccy'];
            $result[$asset] = $this->normalizer->balance($asset, $b);
        }
        return $result;
    }

    public function getBalance(string $asset): BalanceDTO
    {
        $res = $this->okxGet(OkxConfig::ACCOUNT_BALANCE, ['ccy' => strtoupper($asset)], true);
        $b   = $res[0]['details'][0] ?? [];
        return $this->normalizer->balance(strtoupper($asset), $b);
    }

    public function getCommissionRates(): array
    {
        return ['maker' => 0.0008, 'taker' => 0.001];
    }

    public function getDepositAddress(string $asset, ?string $network = null): DepositDTO
    {
        $params = $this->filterNulls(['ccy' => strtoupper($asset), 'chain' => $network]);
        $res    = $this->okxGet(OkxConfig::DEPOSIT_ADDRESS, $params, true);
        return $this->normalizer->depositAddress(strtoupper($asset), $res[0] ?? []);
    }

    public function getDepositHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $params = $asset ? ['ccy' => strtoupper($asset)] : [];
        $res    = $this->okxGet(OkxConfig::DEPOSIT_HISTORY, $params, true);
        return array_map(fn($d) => $this->normalizer->depositAddress($d['ccy'] ?? '', $d), $res);
    }

    public function getWithdrawHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $params = $asset ? ['ccy' => strtoupper($asset)] : [];
        $res    = $this->okxGet(OkxConfig::WITHDRAWAL_HISTORY, $params, true);
        return array_map(fn($w) => $this->normalizer->withdraw($w), $res);
    }

    public function withdraw(string $asset, string $address, float $amount, ?string $network = null, ?string $memo = null): WithdrawDTO
    {
        $body = $this->filterNulls([
            'ccy'    => strtoupper($asset),
            'amt'    => $amount,
            'dest'   => 4,
            'toAddr' => $address,
            'fee'    => '0',
            'chain'  => $network,
            'tag'    => $memo,
        ]);
        $res = $this->okxPost(OkxConfig::WITHDRAWAL, $body);
        return new WithdrawDTO($res[0]['wdId'] ?? '', strtoupper($asset), $address, $memo, $network ?? '', $amount, 0, $amount, null, WithdrawDTO::STATUS_PENDING, time() * 1000, 'okx');
    }

    // ── Trading ───────────────────────────────────────────────────────────────

    public function createOrder(string $symbol, string $side, string $type, float $quantity, ?float $price = null, ?float $stopPrice = null, ?string $timeInForce = 'GTC', ?string $clientOrderId = null): OrderDTO
    {
        $typeMap = ['MARKET' => 'market', 'LIMIT' => 'limit', 'STOP_LIMIT' => 'limit', 'STOP_MARKET' => 'market'];
        $body    = $this->filterNulls([
            'instId'  => $symbol,
            'tdMode'  => 'cash',
            'side'    => strtolower($side),
            'ordType' => $typeMap[strtoupper($type)] ?? 'limit',
            'sz'      => $quantity,
            'px'      => $price,
            'clOrdId' => $clientOrderId,
        ]);
        $res = $this->okxPost(OkxConfig::ORDER, $body);
        return $this->getOrder($symbol, $res[0]['ordId'] ?? '');
    }

    public function cancelOrder(string $symbol, string $orderId): OrderDTO
    {
        $order = $this->getOrder($symbol, $orderId);
        $this->okxPost(OkxConfig::CANCEL_ORDER, ['instId' => $symbol, 'ordId' => $orderId]);
        return $order;
    }

    public function cancelAllOrders(string $symbol): array
    {
        $open  = $this->getOpenOrders($symbol);
        $batch = array_map(fn($o) => ['instId' => $symbol, 'ordId' => $o->orderId], $open);
        if (!empty($batch)) {
            $this->okxPost(OkxConfig::CANCEL_BATCH, $batch);
        }
        return $open;
    }

    public function getOrder(string $symbol, string $orderId): OrderDTO
    {
        $res = $this->okxGet(OkxConfig::ORDER, ['instId' => $symbol, 'ordId' => $orderId], true);
        if (empty($res[0])) {
            throw new OrderNotFoundException($orderId, 'okx');
        }
        return $this->normalizer->order($res[0]);
    }

    public function getOpenOrders(?string $symbol = null): array
    {
        $params = ['instType' => 'SPOT'];
        if ($symbol) $params['instId'] = $symbol;
        $res = $this->okxGet(OkxConfig::ORDERS_PENDING, $params, true);
        return array_map(fn($o) => $this->normalizer->order($o), $res);
    }

    public function getOrderHistory(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $res = $this->okxGet(OkxConfig::ORDERS_HISTORY, ['instType' => 'SPOT', 'instId' => $symbol, 'limit' => $limit], true);
        return array_map(fn($o) => $this->normalizer->order($o), $res);
    }

    public function getMyTrades(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $res = $this->okxGet(OkxConfig::FILLS, ['instType' => 'SPOT', 'instId' => $symbol, 'limit' => $limit], true);
        return array_map(fn($t) => $this->normalizer->trade($t), $res);
    }

    public function editOrder(string $symbol, string $orderId, ?float $price = null, ?float $quantity = null): OrderDTO
    {
        $body = $this->filterNulls(['instId' => $symbol, 'ordId' => $orderId, 'newPx' => $price, 'newSz' => $quantity]);
        $res  = $this->okxPost(OkxConfig::AMEND_ORDER, $body);
        return $this->getOrder($symbol, $res[0]['ordId'] ?? $orderId);
    }

    public function createOCOOrder(string $symbol, string $side, float $quantity, float $price, float $stopPrice, float $stopLimitPrice): array
    {
        $limit = $this->createOrder($symbol, $side, 'LIMIT', $quantity, $price);
        $stop  = $this->createOrder($symbol, $side, 'LIMIT', $quantity, $stopLimitPrice);
        return ['oco_group_id' => null, 'limit_order' => $limit, 'stop_order' => $stop];
    }

    // ── Staking ───────────────────────────────────────────────────────────────

    public function stakeAsset(string $asset, float $amount): array
    {
        $offers    = $this->okxGet(OkxConfig::EARN_OFFERS, ['ccy' => strtoupper($asset)], true);
        $productId = $offers[0]['productId'] ?? null;
        if (!$productId) throw new \RuntimeException("Produto Earn não encontrado para {$asset}");
        $res = $this->okxPost(OkxConfig::EARN_PURCHASE, ['productId' => $productId, 'investData' => [['ccy' => strtoupper($asset), 'amt' => $amount]], 'term' => 0]);
        return ['asset' => strtoupper($asset), 'staked' => $amount, 'order_id' => $res[0]['ordId'] ?? null, 'status' => 'STAKED'];
    }

    public function unstakeAsset(string $asset, float $amount): array
    {
        $active = $this->okxGet(OkxConfig::EARN_ACTIVE, ['ccy' => strtoupper($asset)], true);
        $ordId  = $active[0]['ordId'] ?? null;
        if (!$ordId) throw new \RuntimeException("Posição Earn não encontrada para {$asset}");
        $this->okxPost(OkxConfig::EARN_REDEEM, ['ordId' => $ordId, 'protocolType' => 'defi', 'allowEarlyRedeem' => true]);
        return ['asset' => strtoupper($asset), 'unstaked' => $amount, 'status' => 'UNSTAKED'];
    }

    public function getStakingPositions(): array
    {
        return $this->okxGet(OkxConfig::EARN_ACTIVE, [], true);
    }
}
