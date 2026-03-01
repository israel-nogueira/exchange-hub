<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Bitstamp;
use IsraelNogueira\ExchangeHub\Core\AbstractExchange;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};
use IsraelNogueira\ExchangeHub\Exceptions\{InvalidOrderException,OrderNotFoundException};

class BitstampExchange extends AbstractExchange
{
    private BitstampSigner     $signer;
    private BitstampNormalizer $normalizer;

    protected function configure(): void
    {
        $this->name       = 'bitstamp';
        $this->baseUrl    = BitstampConfig::BASE_URL;
        $this->signer     = new BitstampSigner($this->apiKey, $this->apiSecret);
        $this->normalizer = new BitstampNormalizer();
    }

    /** Bitstamp uses lowercased pair: BTCUSDT -> btcusd */
    private function pair(string $symbol): string
    {
        return strtolower(str_replace(['USDT','USDC'], ['usd','usd'], $symbol));
    }

    private function pub(string $path, array $p = []): array
    {
        $q = $p ? '?' . http_build_query($p) : '';
        return $this->http->get($this->baseUrl . $path . $q, ['Content-Type: application/json'], 'bitstamp');
    }

    private function priv(string $method, string $path, array $body = []): array
    {
        $encoded = $body ? http_build_query($body) : '';
        $host    = 'www.bitstamp.net';
        $fullPath= '/api/v2' . $path;
        $hdrs    = [];
        foreach ($this->signer->getHeaders($method, $host, $fullPath, '', $encoded) as $k => $v) $hdrs[] = "$k: $v";
        $url     = $this->baseUrl . $path;
        $r = $this->http->post($url, $encoded, $hdrs, 'bitstamp');
        return $r;
    }

    public function ping(): bool
    {
        try { $this->pub(BitstampConfig::TICKERS); return true; } catch (\Exception) { return false; }
    }

    public function getServerTime(): int { return time() * 1000; }

    public function getExchangeInfo(): ExchangeInfoDTO
    {
        $pairs   = $this->pub(BitstampConfig::TRADING_PAIRS);
        $symbols = array_map(fn($p) => strtoupper(str_replace('/', '', $p['url_symbol'] ?? '')), $pairs);
        return new ExchangeInfoDTO('Bitstamp', 'ONLINE', array_values(array_filter($symbols)), 0.003, 0.005, [], [], time() * 1000);
    }

    public function getSymbols(): array
    {
        return array_values(array_filter(array_map(
            fn($p) => strtoupper(str_replace('/', '', $p['url_symbol'] ?? '')),
            $this->pub(BitstampConfig::TRADING_PAIRS)
        )));
    }

    public function getTicker(string $symbol): TickerDTO
    {
        $path = str_replace('{pair}', $this->pair($symbol), BitstampConfig::TICKER);
        return $this->normalizer->ticker($symbol, $this->pub($path));
    }

    public function getTicker24h(string $symbol): TickerDTO { return $this->getTicker($symbol); }

    public function getAllTickers(): array
    {
        $res = $this->pub(BitstampConfig::TICKERS);
        return array_map(fn($d) => $this->normalizer->ticker($d['pair'] ?? '', $d), $res);
    }

    public function getOrderBook(string $symbol, int $limit = 20): OrderBookDTO
    {
        $path = str_replace('{pair}', $this->pair($symbol), BitstampConfig::ORDER_BOOK);
        return $this->normalizer->orderBook($symbol, $this->pub($path, ['group' => 1]));
    }

    public function getRecentTrades(string $symbol, int $limit = 50): array
    {
        $path = str_replace('{pair}', $this->pair($symbol), BitstampConfig::TRANSACTIONS);
        $res  = $this->pub($path, ['time' => 'hour']);
        return array_slice(array_map(fn($t) => $this->normalizer->trade($t), $res), 0, $limit);
    }

    public function getHistoricalTrades(string $symbol, int $limit = 100, ?int $fromId = null): array
    {
        return $this->getRecentTrades($symbol, $limit);
    }

    public function getCandles(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $path   = str_replace('{pair}', $this->pair($symbol), BitstampConfig::OHLC);
        $step   = BitstampConfig::INTERVAL_MAP[$interval] ?? '3600';
        $params = ['step' => $step, 'limit' => $limit];
        if ($startTime) $params['start'] = (int)($startTime / 1000);
        if ($endTime)   $params['end']   = (int)($endTime / 1000);
        $res = $this->pub($path, $params);
        return array_map(fn($c) => $this->normalizer->candle($symbol, $interval, $c), $res['data']['ohlc'] ?? []);
    }

    public function getAvgPrice(string $symbol): float { return $this->getTicker($symbol)->price; }

    public function getAccountInfo(): array { return $this->priv('POST', BitstampConfig::BALANCE); }

    public function getBalances(): array
    {
        $res    = $this->priv('POST', BitstampConfig::BALANCE);
        $result = [];
        // Bitstamp returns btc_available, btc_reserved, usd_available, etc.
        foreach ($res as $key => $val) {
            if (str_ends_with($key, '_available')) {
                $asset    = strtoupper(str_replace('_available', '', $key));
                $reserved = (float)($res[$asset . '_reserved'] ?? 0);
                $avail    = (float)$val;
                if ($avail > 0 || $reserved > 0) {
                    $result[$asset] = $this->normalizer->balance($asset, $avail, $reserved);
                }
            }
        }
        return $result;
    }

    public function getBalance(string $asset): BalanceDTO
    {
        $res   = $this->priv('POST', BitstampConfig::BALANCE);
        $lc    = strtolower($asset);
        $avail = (float)($res[$lc . '_available'] ?? 0);
        $lock  = (float)($res[$lc . '_reserved'] ?? 0);
        return $this->normalizer->balance($asset, $avail, $lock);
    }

    public function getCommissionRates(): array
    {
        $res = $this->priv('POST', BitstampConfig::BALANCE);
        return ['maker' => (float)($res['fee'] ?? 0.003) / 100, 'taker' => (float)($res['fee'] ?? 0.005) / 100];
    }

    public function getDepositAddress(string $asset, ?string $network = null): DepositDTO
    {
        $lc  = strtolower($asset);
        $res = $this->priv('POST', BitstampConfig::DEPOSIT_ADDR, ['currency' => $lc]);
        return new DepositDTO(
            asset: strtoupper($asset), address: $res['address'] ?? '', memo: $res['destination_tag'] ?? null,
            network: $network ?? '', depositId: null, amount: null, txId: null,
            status: DepositDTO::STATUS_CONFIRMED, timestamp: null, exchange: 'bitstamp',
        );
    }

    public function getDepositHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array { return []; }
    public function getWithdrawHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $res = $this->priv('POST', BitstampConfig::WITHDRAWAL_REQUESTS);
        return array_map(fn($w) => new WithdrawDTO(
            withdrawId: (string)($w['id'] ?? ''), asset: '', address: $w['address'] ?? '', memo: null,
            network: '', amount: (float)($w['amount'] ?? 0), fee: 0, netAmount: (float)($w['amount'] ?? 0),
            txId: $w['transaction_id'] ?? null, status: WithdrawDTO::STATUS_PENDING,
            timestamp: isset($w['datetime']) ? strtotime($w['datetime']) * 1000 : time() * 1000,
            exchange: 'bitstamp',
        ), $res);
    }

    public function withdraw(string $asset, string $address, float $amount, ?string $network = null, ?string $memo = null): WithdrawDTO
    {
        $res = $this->priv('POST', BitstampConfig::CRYPTO_WITHDRAW, [
            'amount'   => $amount,
            'address'  => $address,
            'currency' => strtolower($asset),
        ]);
        return new WithdrawDTO(
            withdrawId: (string)($res['id'] ?? uniqid()), asset: strtoupper($asset),
            address: $address, memo: $memo, network: $network ?? '',
            amount: $amount, fee: 0, netAmount: $amount, txId: null,
            status: WithdrawDTO::STATUS_PENDING, timestamp: time() * 1000, exchange: 'bitstamp',
        );
    }

    public function createOrder(string $symbol, string $side, string $type, float $quantity, ?float $price = null, ?float $stopPrice = null, ?string $timeInForce = 'GTC', ?string $clientOrderId = null): OrderDTO
    {
        $pair = $this->pair($symbol);
        if (strtoupper($side) === 'BUY') {
            $path = strtoupper($type) === 'MARKET'
                ? str_replace('{pair}', $pair, BitstampConfig::BUY_MARKET)
                : str_replace('{pair}', $pair, BitstampConfig::BUY);
        } else {
            $path = strtoupper($type) === 'MARKET'
                ? str_replace('{pair}', $pair, BitstampConfig::SELL_MARKET)
                : str_replace('{pair}', $pair, BitstampConfig::SELL);
        }
        $body = ['amount' => $quantity];
        if ($price) $body['price'] = $price;
        $res = $this->priv('POST', $path, $body);
        return $this->normalizer->order($symbol, $res);
    }

    public function cancelOrder(string $symbol, string $orderId): OrderDTO
    {
        $res = $this->priv('POST', BitstampConfig::CANCEL_ORDER, ['id' => $orderId]);
        return $this->normalizer->order($symbol, array_merge(['id' => $orderId], $res));
    }

    public function cancelAllOrders(string $symbol): array
    {
        $this->priv('POST', BitstampConfig::CANCEL_ALL);
        return [];
    }

    public function getOrder(string $symbol, string $orderId): OrderDTO
    {
        $res = $this->priv('POST', BitstampConfig::ORDER_STATUS, ['id' => $orderId]);
        return $this->normalizer->order($symbol, $res);
    }

    public function getOpenOrders(?string $symbol = null): array
    {
        if ($symbol) {
            $path = str_replace('{pair}', $this->pair($symbol), BitstampConfig::OPEN_ORDERS);
        } else {
            $path = BitstampConfig::ALL_ORDERS;
        }
        $res = $this->priv('POST', $path);
        return array_map(fn($o) => $this->normalizer->order($symbol ?? '', $o), $res);
    }

    public function getOrderHistory(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $res = $this->priv('POST', BitstampConfig::USER_TXNS, ['limit' => $limit, 'offset' => 0]);
        return array_map(fn($t) => $this->normalizer->trade($t), $res);
    }

    public function getMyTrades(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        return $this->getOrderHistory($symbol, $limit, $startTime, $endTime);
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

    public function stakeAsset(string $asset, float $amount): array { return ['asset' => strtoupper($asset), 'staked' => $amount, 'status' => 'STAKED']; }
    public function unstakeAsset(string $asset, float $amount): array { return ['asset' => strtoupper($asset), 'unstaked' => $amount, 'status' => 'UNSTAKED']; }
    public function getStakingPositions(): array { return []; }
}
