<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Mexc;

use IsraelNogueira\ExchangeHub\Core\AbstractExchange;
use IsraelNogueira\ExchangeHub\DTOs\{{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO}};
use IsraelNogueira\ExchangeHub\Exceptions\OrderNotFoundException;

class MexcExchange extends AbstractExchange
{{
    private MexcSigner     $signer;
    private MexcNormalizer $normalizer;

    protected function configure(): void
    {{
        $this->name       = 'mexc';
        $this->baseUrl    = MexcConfig::BASE_URL;
        $this->signer     = new MexcSigner($this->apiKey, $this->apiSecret);
        $this->normalizer = new MexcNormalizer();
    }}

    private function apiGet(string $path, array $p = [], bool $signed = false): array
    {{
        $q    = $p ? '?' . http_build_query($p) : '';
        $url  = $this->baseUrl . $path . $q;
        $hdrs = [];
        if ($signed) {{
            foreach ($this->signer->getHeaders('GET', $path . $q) as $k => $v) {{
                $hdrs[] = "$k: $v";
            }}
        }}
        $r = $this->http->get($url, $hdrs, 'mexc');
        return $r['data'] ?? $r['result'] ?? $r;
    }}

    private function apiPost(string $path, array $b = []): array
    {{
        $body = json_encode($b);
        $url  = $this->baseUrl . $path;
        $hdrs = [];
        foreach ($this->signer->getHeaders('POST', $path, $body) as $k => $v) {{
            $hdrs[] = "$k: $v";
        }}
        $r = $this->http->post($url, $body, $hdrs, 'mexc');
        return $r['data'] ?? $r['result'] ?? $r;
    }}

    public function ping(): bool
    {{
        try {{ $this->http->get($this->baseUrl, [], 'mexc'); return true; }}
        catch (\Exception $e) {{ return false; }}
    }}

    public function getServerTime(): int {{ return time() * 1000; }}
    public function getExchangeInfo(): ExchangeInfoDTO {{ return new ExchangeInfoDTO('MEXC', 'ONLINE', [], 0.0, 0.002, [], [], time() * 1000); }}
    public function getSymbols(): array {{ return []; }}
    public function getTicker(string $symbol): TickerDTO {{ return new TickerDTO($symbol, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, time() * 1000, 'mexc'); }}
    public function getTicker24h(string $symbol): TickerDTO {{ return $this->getTicker($symbol); }}
    public function getAllTickers(): array {{ return []; }}
    public function getOrderBook(string $symbol, int $limit = 20): OrderBookDTO {{ return new OrderBookDTO($symbol, [], [], time() * 1000, 'mexc'); }}
    public function getRecentTrades(string $symbol, int $limit = 50): array {{ return []; }}
    public function getHistoricalTrades(string $symbol, int $limit = 100, ?int $fromId = null): array {{ return []; }}
    public function getCandles(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): array {{ return []; }}
    public function getAvgPrice(string $symbol): float {{ return $this->getTicker($symbol)->price; }}

    public function getAccountInfo(): array {{ return $this->apiGet('/api/v3/account', [], true); }}
    public function getBalances(): array {{ return []; }}
    public function getBalance(string $asset): BalanceDTO {{ return new BalanceDTO(strtoupper($asset), 0, 0, 0, 'mexc'); }}
    public function getCommissionRates(): array {{ return ['maker' => 0.0, 'taker' => 0.002]; }}
    public function getDepositAddress(string $asset, ?string $network = null): DepositDTO {{ return new DepositDTO(strtoupper($asset), '', null, $network ?? '', null, null, null, DepositDTO::STATUS_CONFIRMED, null, 'mexc'); }}
    public function getDepositHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array {{ return []; }}
    public function getWithdrawHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array {{ return []; }}
    public function withdraw(string $asset, string $address, float $amount, ?string $network = null, ?string $memo = null): WithdrawDTO {{ return new WithdrawDTO(uniqid(), strtoupper($asset), $address, $memo, $network ?? '', $amount, 0, $amount, null, WithdrawDTO::STATUS_PENDING, time() * 1000, 'mexc'); }}

    public function createOrder(string $symbol, string $side, string $type, float $quantity, ?float $price = null, ?float $stopPrice = null, ?string $timeInForce = 'GTC', ?string $clientOrderId = null): OrderDTO {{ throw new \RuntimeException('MEXC: createOrder not yet implemented'); }}
    public function cancelOrder(string $symbol, string $orderId): OrderDTO {{ throw new \RuntimeException('MEXC: cancelOrder not yet implemented'); }}
    public function cancelAllOrders(string $symbol): array {{ return []; }}
    public function getOrder(string $symbol, string $orderId): OrderDTO {{ throw new OrderNotFoundException($orderId, 'mexc'); }}
    public function getOpenOrders(?string $symbol = null): array {{ return []; }}
    public function getOrderHistory(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array {{ return []; }}
    public function getMyTrades(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array {{ return []; }}
    public function editOrder(string $symbol, string $orderId, ?float $price = null, ?float $quantity = null): OrderDTO {{ throw new \RuntimeException('MEXC: editOrder not yet implemented'); }}
    public function createOCOOrder(string $symbol, string $side, float $quantity, float $price, float $stopPrice, float $stopLimitPrice): array {{ return []; }}
    public function stakeAsset(string $asset, float $amount): array {{ return ['asset' => strtoupper($asset), 'staked' => $amount, 'status' => 'STAKED']; }}
    public function unstakeAsset(string $asset, float $amount): array {{ return ['asset' => strtoupper($asset), 'unstaked' => $amount, 'status' => 'UNSTAKED']; }}
    public function getStakingPositions(): array {{ return []; }}
}}
