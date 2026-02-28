<?php
namespace Exchanges\Exchanges\Kraken;
use Exchanges\Core\AbstractExchange;
use Exchanges\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};
use Exchanges\Exceptions\OrderNotFoundException;

class KrakenExchange extends AbstractExchange
{
    private KrakenSigner     $signer;
    private KrakenNormalizer $normalizer;

    protected function configure(): void
    {
        $this->name       = 'kraken';
        $this->baseUrl    = KrakenConfig::BASE_URL;
        $this->signer     = new KrakenSigner($this->apiKey, $this->apiSecret);
        $this->normalizer = new KrakenNormalizer();
    }

    /** Kraken usa form-encoded no POST privado com assinatura especial */
    private function privatePost(string $path, array $data = []): array
    {
        $signed = $this->signer->sign($path, $data);
        $headers = [];
        foreach ($signed['headers'] as $k => $v) $headers[] = "{$k}: {$v}";
        $url = KrakenConfig::BASE_URL . $path;
        $this->logger->logRequest('POST', $url, $signed['data']);
        $body = http_build_query($signed['data']);
        $res  = $this->http->request('POST', $url, $body, $headers, 'kraken');
        if (!empty($res['error'])) throw new \RuntimeException(implode(', ', $res['error']));
        return $res['result'] ?? $res;
    }

    private function publicGet(string $path, array $params = []): array
    {
        $url = $this->buildUrl($path, $params);
        $res = $this->http->get($url, [], 'kraken');
        if (!empty($res['error'])) throw new \RuntimeException(implode(', ', $res['error']));
        return $res['result'] ?? $res;
    }

    public function ping(): bool
    {
        $res = $this->publicGet(KrakenConfig::PING);
        return ($res['status'] ?? '') === 'online';
    }

    public function getServerTime(): int
    {
        $res = $this->publicGet(KrakenConfig::TIME);
        return (int)(($res['unixtime'] ?? time()) * 1000);
    }

    public function getExchangeInfo(): ExchangeInfoDTO
    {
        $res = $this->publicGet(KrakenConfig::ASSET_PAIRS);
        return new ExchangeInfoDTO('Kraken','ONLINE',array_keys($res),0.0016,0.0026,[],[],time()*1000);
    }

    public function getSymbols(): array
    {
        return array_keys($this->publicGet(KrakenConfig::ASSET_PAIRS));
    }

    public function getTicker(string $symbol): TickerDTO
    {
        $res = $this->publicGet(KrakenConfig::TICKER, ['pair' => $symbol]);
        return $this->normalizer->ticker($symbol, $res);
    }

    public function getTicker24h(string $symbol): TickerDTO { return $this->getTicker($symbol); }

    public function getAllTickers(): array
    {
        $pairs = implode(',', array_slice($this->getSymbols(), 0, 50));
        $res   = $this->publicGet(KrakenConfig::TICKER, ['pair' => $pairs]);
        return array_map(fn($sym, $d) => $this->normalizer->ticker($sym, [$sym => $d]), array_keys($res), $res);
    }

    public function getOrderBook(string $symbol, int $limit = 20): OrderBookDTO
    {
        $res = $this->publicGet(KrakenConfig::DEPTH, ['pair' => $symbol, 'count' => $limit]);
        return $this->normalizer->orderBook($symbol, $res);
    }

    public function getRecentTrades(string $symbol, int $limit = 50): array
    {
        $res    = $this->publicGet(KrakenConfig::TRADES, ['pair' => $symbol]);
        $trades = reset($res);
        return array_map(fn($t) => new TradeDTO('', '', $symbol, $t[3]==='b'?'BUY':'SELL', (float)$t[0], (float)$t[1], 0, 0, '', false, (int)($t[2]*1000), 'kraken'), array_slice((array)$trades, 0, $limit));
    }

    public function getHistoricalTrades(string $symbol, int $limit = 100, ?int $fromId = null): array
    {
        return $this->getRecentTrades($symbol, $limit);
    }

    public function getCandles(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $intervalMap = ['1m'=>1,'5m'=>5,'15m'=>15,'30m'=>30,'1h'=>60,'4h'=>240,'1d'=>1440,'1w'=>10080,'1M'=>21600];
        $minutes     = $intervalMap[$interval] ?? 60;
        $params      = ['pair' => $symbol, 'interval' => $minutes];
        if ($startTime) $params['since'] = (int)($startTime / 1000);
        $res    = $this->publicGet(KrakenConfig::OHLC, $params);
        $candles= reset($res);
        return array_map(fn($c) => $this->normalizer->candle($symbol, $interval, $c), array_slice((array)$candles, -$limit));
    }

    public function getAvgPrice(string $symbol): float
    {
        return $this->getTicker($symbol)->price;
    }

    public function getAccountInfo(): array { return $this->privatePost(KrakenConfig::TRADE_BALANCE); }

    public function getBalances(): array
    {
        $res = $this->privatePost(KrakenConfig::BALANCE);
        $out = [];
        foreach ($res as $asset => $amount) {
            if ((float)$amount > 0) $out[$asset] = $this->normalizer->balance($asset, (float)$amount);
        }
        return $out;
    }

    public function getBalance(string $asset): BalanceDTO
    {
        $res = $this->privatePost(KrakenConfig::BALANCE);
        return $this->normalizer->balance($asset, (float)($res[$asset] ?? $res['X'.strtoupper($asset)] ?? 0));
    }

    public function getCommissionRates(): array
    {
        return $this->privatePost(KrakenConfig::TRADE_VOLUME, ['pair' => 'XXBTZUSD']);
    }

    public function getDepositAddress(string $asset, ?string $network = null): DepositDTO
    {
        $methods = $this->privatePost(KrakenConfig::DEPOSIT_METHODS, ['asset' => strtoupper($asset)]);
        $method  = $network ?? ($methods[0]['method'] ?? '');
        $res     = $this->privatePost(KrakenConfig::DEPOSIT_ADDR, ['asset' => strtoupper($asset), 'method' => $method]);
        return $this->normalizer->depositAddress($asset, $res[0] ?? []);
    }

    public function getDepositHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $params = $asset ? ['asset' => strtoupper($asset)] : [];
        $res    = $this->privatePost(KrakenConfig::DEPOSIT_STATUS, $params);
        return array_map(fn($d) => $this->normalizer->depositAddress($d['asset'] ?? '', $d), (array)$res);
    }

    public function getWithdrawHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $params = $asset ? ['asset' => strtoupper($asset)] : [];
        $res    = $this->privatePost(KrakenConfig::WITHDRAW_STATUS, $params);
        return array_map(fn($w) => $this->normalizer->withdraw($w), (array)$res);
    }

    public function withdraw(string $asset, string $address, float $amount, ?string $network = null, ?string $memo = null): WithdrawDTO
    {
        $res = $this->privatePost(KrakenConfig::WITHDRAW, ['asset' => strtoupper($asset), 'key' => $address, 'amount' => $amount]);
        return new WithdrawDTO($res['refid'] ?? '', strtoupper($asset), $address, $memo, $network ?? '', $amount, 0, $amount, null, WithdrawDTO::STATUS_PENDING, time()*1000, 'kraken');
    }

    public function createOrder(string $symbol, string $side, string $type, float $quantity, ?float $price = null, ?float $stopPrice = null, ?string $timeInForce = 'GTC', ?string $clientOrderId = null): OrderDTO
    {
        $typeMap = ['MARKET'=>'market','LIMIT'=>'limit','STOP_LIMIT'=>'stop-loss-limit','STOP_MARKET'=>'stop-loss'];
        $params  = ['pair'=>$symbol,'type'=>strtolower($side),'ordertype'=>$typeMap[strtoupper($type)]??'limit','volume'=>$quantity];
        if ($price)      $params['price']  = $price;
        if ($stopPrice)  $params['price2'] = $stopPrice;
        if ($clientOrderId) $params['userref'] = $clientOrderId;
        $res = $this->privatePost(KrakenConfig::ADD_ORDER, $params);
        $id  = $res['txid'][0] ?? 'unknown';
        return $this->getOrder($symbol, $id);
    }

    public function cancelOrder(string $symbol, string $orderId): OrderDTO
    {
        try { $order = $this->getOrder($symbol, $orderId); } catch (\Exception $e) { throw new OrderNotFoundException($orderId, 'kraken'); }
        $this->privatePost(KrakenConfig::CANCEL_ORDER, ['txid' => $orderId]);
        return $order;
    }

    public function cancelAllOrders(string $symbol): array
    {
        $open = $this->getOpenOrders($symbol);
        $this->privatePost(KrakenConfig::CANCEL_ALL);
        return $open;
    }

    public function getOrder(string $symbol, string $orderId): OrderDTO
    {
        $res = $this->privatePost(KrakenConfig::QUERY_ORDERS, ['txid' => $orderId, 'trades' => true]);
        if (empty($res[$orderId])) throw new OrderNotFoundException($orderId, 'kraken');
        return $this->normalizer->order($orderId, $res[$orderId]);
    }

    public function getOpenOrders(?string $symbol = null): array
    {
        $res    = $this->privatePost(KrakenConfig::OPEN_ORDERS, ['trades' => true]);
        $orders = $res['open'] ?? [];
        $result = [];
        foreach ($orders as $id => $order) {
            $dto = $this->normalizer->order($id, $order);
            if (!$symbol || $dto->symbol === $symbol) $result[] = $dto;
        }
        return $result;
    }

    public function getOrderHistory(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $res    = $this->privatePost(KrakenConfig::CLOSED_ORDERS, ['trades' => true]);
        $orders = $res['closed'] ?? [];
        $result = [];
        foreach ($orders as $id => $order) {
            $dto = $this->normalizer->order($id, $order);
            if ($dto->symbol === $symbol) $result[] = $dto;
        }
        return array_slice($result, 0, $limit);
    }

    public function getMyTrades(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $res    = $this->privatePost(KrakenConfig::TRADES_HISTORY, ['trades' => true]);
        $trades = $res['trades'] ?? [];
        $result = [];
        foreach ($trades as $id => $t) {
            if ($t['pair'] === $symbol) {
                $result[] = new TradeDTO($id, $t['ordertxid'] ?? '', $symbol, strtoupper($t['type']), (float)$t['price'], (float)$t['vol'], (float)$t['cost'], (float)$t['fee'], $t['feeabcur'] ?? '', ($t['misc'] ?? '') === 'maker', (int)($t['time']*1000), 'kraken');
            }
        }
        return array_slice($result, 0, $limit);
    }

    public function editOrder(string $symbol, string $orderId, ?float $price = null, ?float $quantity = null): OrderDTO
    {
        $params = ['txid' => $orderId];
        if ($price)    $params['price']  = $price;
        if ($quantity) $params['volume'] = $quantity;
        $res = $this->privatePost(KrakenConfig::EDIT_ORDER, $params);
        return $this->getOrder($symbol, $res['txid'] ?? $orderId);
    }

    public function createOCOOrder(string $symbol, string $side, float $quantity, float $price, float $stopPrice, float $stopLimitPrice): array
    {
        $limit = $this->createOrder($symbol, $side, 'LIMIT', $quantity, $price);
        $stop  = $this->createOrder($symbol, $side, 'STOP_LIMIT', $quantity, $stopLimitPrice, $stopPrice);
        return ['oco_group_id' => null, 'limit_order' => $limit, 'stop_order' => $stop];
    }

    /** Dead man's switch — cancela tudo após X segundos */
    public function cancelAllAfter(int $timeout): array
    {
        return $this->privatePost(KrakenConfig::CANCEL_AFTER, ['timeout' => $timeout]);
    }

    public function stakeAsset(string $asset, float $amount): array
    {
        $res = $this->privatePost(KrakenConfig::STAKE_ASSET, ['asset' => strtoupper($asset), 'amount' => $amount, 'method' => strtolower($asset) . '-staked']);
        return ['asset' => strtoupper($asset), 'staked' => $amount, 'refid' => $res['refid'] ?? null, 'status' => 'STAKED'];
    }

    public function unstakeAsset(string $asset, float $amount): array
    {
        $res = $this->privatePost(KrakenConfig::UNSTAKE_ASSET, ['asset' => strtoupper($asset), 'amount' => $amount]);
        return ['asset' => strtoupper($asset), 'unstaked' => $amount, 'refid' => $res['refid'] ?? null, 'status' => 'UNSTAKED'];
    }

    public function getStakingPositions(): array
    {
        return $this->privatePost(KrakenConfig::STAKE_PENDING);
    }
}
