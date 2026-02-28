<?php

namespace Exchanges\Exchanges\Binance;

use Exchanges\Core\AbstractExchange;
use Exchanges\DTOs\{TickerDTO, OrderBookDTO, OrderDTO, TradeDTO, BalanceDTO, CandleDTO, DepositDTO, WithdrawDTO, ExchangeInfoDTO};
use Exchanges\Exceptions\{InvalidSymbolException, InvalidOrderException, InsufficientBalanceException};

class BinanceExchange extends AbstractExchange
{
    private BinanceSigner     $signer;
    private BinanceNormalizer $normalizer;

    protected function configure(): void
    {
        $this->name    = 'binance';
        $this->baseUrl = $this->testnet ? BinanceConfig::TESTNET_URL : BinanceConfig::BASE_URL;
        $this->signer  = new BinanceSigner($this->apiKey, $this->apiSecret);
        $this->normalizer = new BinanceNormalizer();
    }

    protected function buildHeaders(string $method, string $endpoint, array $params, array $body, bool $signed): array
    {
        return $signed
            ? $this->signer->getHeaders()
            : ['Content-Type: application/json'];
    }

    protected function signParams(array $params): array
    {
        return $this->signer->signParams($params);
    }

    protected function signRequest(string $method, string $endpoint, array $params, array $body): array
    {
        return $this->signer->signWithBody($params, $body);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MARKET DATA
    // ─────────────────────────────────────────────────────────────────────────

    public function ping(): bool
    {
        $res = $this->get(BinanceConfig::PING);
        return $res !== null;
    }

    public function getServerTime(): int
    {
        $res = $this->get(BinanceConfig::TIME);
        return (int)($res['serverTime'] ?? time() * 1000);
    }

    public function getExchangeInfo(): ExchangeInfoDTO
    {
        $res = $this->get(BinanceConfig::EXCHANGE_INFO);
        return $this->normalizer->exchangeInfo($res);
    }

    public function getSymbols(): array
    {
        $res = $this->get(BinanceConfig::EXCHANGE_INFO);
        return array_map(
            fn($s) => $s['symbol'],
            array_filter($res['symbols'] ?? [], fn($s) => $s['status'] === 'TRADING')
        );
    }

    public function getTicker(string $symbol): TickerDTO
    {
        $res = $this->get(BinanceConfig::TICKER_24H, ['symbol' => $symbol]);
        return $this->normalizer->ticker($res);
    }

    public function getTicker24h(string $symbol): TickerDTO
    {
        return $this->getTicker($symbol);
    }

    public function getAllTickers(): array
    {
        $res = $this->get(BinanceConfig::TICKER_24H);
        return array_map(fn($t) => $this->normalizer->ticker($t), $res);
    }

    public function getOrderBook(string $symbol, int $limit = 20): OrderBookDTO
    {
        $res = $this->get(BinanceConfig::DEPTH, ['symbol' => $symbol, 'limit' => $limit]);
        return $this->normalizer->orderBook($res, $symbol);
    }

    public function getRecentTrades(string $symbol, int $limit = 50): array
    {
        $res = $this->get(BinanceConfig::TRADES, ['symbol' => $symbol, 'limit' => $limit]);
        return array_map(fn($t) => $this->normalizer->trade($t, $symbol), $res);
    }

    public function getHistoricalTrades(string $symbol, int $limit = 100, ?int $fromId = null): array
    {
        $params = ['symbol' => $symbol, 'limit' => $limit];
        if ($fromId !== null) $params['fromId'] = $fromId;
        $res = $this->get(BinanceConfig::HISTORICAL_TRADES, $params, true);
        return array_map(fn($t) => $this->normalizer->trade($t, $symbol), $res);
    }

    public function getCandles(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $params = $this->filterNulls(['symbol' => $symbol, 'interval' => $interval, 'limit' => $limit, 'startTime' => $startTime, 'endTime' => $endTime]);
        $res    = $this->get(BinanceConfig::KLINES, $params);
        return array_map(fn($c) => $this->normalizer->candle($symbol, $interval, $c), $res);
    }

    public function getAvgPrice(string $symbol): float
    {
        $res = $this->get(BinanceConfig::AVG_PRICE, ['symbol' => $symbol]);
        return (float)($res['price'] ?? 0);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ACCOUNT
    // ─────────────────────────────────────────────────────────────────────────

    public function getAccountInfo(): array
    {
        return $this->get(BinanceConfig::ACCOUNT, [], true);
    }

    public function getBalances(): array
    {
        $account = $this->get(BinanceConfig::ACCOUNT, [], true);
        $result  = [];
        foreach ($account['balances'] ?? [] as $b) {
            if ((float)$b['free'] > 0 || (float)$b['locked'] > 0) {
                $result[$b['asset']] = $this->normalizer->balance($b['asset'], $b);
            }
        }
        return $result;
    }

    public function getBalance(string $asset): BalanceDTO
    {
        $account = $this->get(BinanceConfig::ACCOUNT, [], true);
        foreach ($account['balances'] ?? [] as $b) {
            if ($b['asset'] === strtoupper($asset)) {
                return $this->normalizer->balance($b['asset'], $b);
            }
        }
        return new BalanceDTO(strtoupper($asset), 0, 0, 0, 'binance');
    }

    public function getCommissionRates(): array
    {
        $res = $this->get(BinanceConfig::TRADE_FEE, [], true);
        return $res;
    }

    public function getDepositAddress(string $asset, ?string $network = null): DepositDTO
    {
        $params = $this->filterNulls(['coin' => strtoupper($asset), 'network' => $network]);
        $res    = $this->get(BinanceConfig::DEPOSIT_ADDRESS, $params, true);
        return $this->normalizer->depositAddress($res);
    }

    public function getDepositHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $params = $this->filterNulls(['coin' => $asset ? strtoupper($asset) : null, 'startTime' => $startTime, 'endTime' => $endTime, 'limit' => 1000]);
        $res    = $this->get(BinanceConfig::DEPOSIT_HISTORY, $params, true);
        return array_map(fn($d) => $this->normalizer->deposit($d), $res);
    }

    public function getWithdrawHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array
    {
        $params = $this->filterNulls(['coin' => $asset ? strtoupper($asset) : null, 'startTime' => $startTime, 'endTime' => $endTime, 'limit' => 1000]);
        $res    = $this->get(BinanceConfig::WITHDRAW_HISTORY, $params, true);
        return array_map(fn($w) => $this->normalizer->withdraw($w), $res);
    }

    public function withdraw(string $asset, string $address, float $amount, ?string $network = null, ?string $memo = null): WithdrawDTO
    {
        $params = $this->filterNulls([
            'coin'          => strtoupper($asset),
            'address'       => $address,
            'addressTag'    => $memo,
            'amount'        => $amount,
            'network'       => $network,
        ]);
        $res = $this->post(BinanceConfig::WITHDRAW, [], $params, true);
        // Binance retorna apenas o id; buscamos no histórico para montar o DTO
        $history = $this->getWithdrawHistory($asset);
        foreach ($history as $w) {
            if ($w->withdrawId === ($res['id'] ?? '')) return $w;
        }
        return new WithdrawDTO($res['id'] ?? '', strtoupper($asset), $address, $memo, $network ?? '', $amount, 0, $amount, null, WithdrawDTO::STATUS_PENDING, time() * 1000, 'binance');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TRADING
    // ─────────────────────────────────────────────────────────────────────────

    public function createOrder(string $symbol, string $side, string $type, float $quantity, ?float $price = null, ?float $stopPrice = null, ?string $timeInForce = 'GTC', ?string $clientOrderId = null): OrderDTO
    {
        $params = $this->filterNulls([
            'symbol'           => $symbol,
            'side'             => strtoupper($side),
            'type'             => strtoupper($type),
            'quantity'         => $quantity,
            'price'            => in_array(strtoupper($type), ['LIMIT','STOP_LOSS_LIMIT','TAKE_PROFIT_LIMIT']) ? $price : null,
            'stopPrice'        => $stopPrice,
            'timeInForce'      => in_array(strtoupper($type), ['LIMIT','STOP_LOSS_LIMIT','TAKE_PROFIT_LIMIT']) ? ($timeInForce ?? 'GTC') : null,
            'newClientOrderId' => $clientOrderId,
            'newOrderRespType' => 'FULL',
        ]);
        $res = $this->post(BinanceConfig::ORDER, [], $params, true);
        return $this->normalizer->order($res);
    }

    public function cancelOrder(string $symbol, string $orderId): OrderDTO
    {
        $params = ['symbol' => $symbol, 'orderId' => $orderId];
        $res    = $this->delete(BinanceConfig::ORDER, $params, true);
        return $this->normalizer->order($res);
    }

    public function cancelAllOrders(string $symbol): array
    {
        $params = ['symbol' => $symbol];
        $res    = $this->delete(BinanceConfig::OPEN_ORDERS, $params, true);
        return array_map(fn($o) => $this->normalizer->order($o), $res);
    }

    public function getOrder(string $symbol, string $orderId): OrderDTO
    {
        $params = ['symbol' => $symbol, 'orderId' => $orderId];
        $res    = $this->get(BinanceConfig::ORDER, $params, true);
        return $this->normalizer->order($res);
    }

    public function getOpenOrders(?string $symbol = null): array
    {
        $params = $this->filterNulls(['symbol' => $symbol]);
        $res    = $this->get(BinanceConfig::OPEN_ORDERS, $params, true);
        return array_map(fn($o) => $this->normalizer->order($o), $res);
    }

    public function getOrderHistory(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $params = $this->filterNulls(['symbol' => $symbol, 'limit' => $limit, 'startTime' => $startTime, 'endTime' => $endTime]);
        $res    = $this->get(BinanceConfig::ALL_ORDERS, $params, true);
        return array_map(fn($o) => $this->normalizer->order($o), $res);
    }

    public function getMyTrades(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array
    {
        $params = $this->filterNulls(['symbol' => $symbol, 'limit' => $limit, 'startTime' => $startTime, 'endTime' => $endTime]);
        $res    = $this->get(BinanceConfig::MY_TRADES, $params, true);
        return array_map(fn($t) => $this->normalizer->trade($t, $symbol), $res);
    }

    public function editOrder(string $symbol, string $orderId, ?float $price = null, ?float $quantity = null): OrderDTO
    {
        // Binance não suporta edição direta — cancela e recria
        $original = $this->getOrder($symbol, $orderId);
        $this->cancelOrder($symbol, $orderId);
        return $this->createOrder(
            $symbol,
            $original->side,
            $original->type,
            $quantity  ?? $original->quantity,
            $price     ?? $original->price,
            $original->stopPrice ?: null,
            $original->timeInForce,
        );
    }

    public function createOCOOrder(string $symbol, string $side, float $quantity, float $price, float $stopPrice, float $stopLimitPrice): array
    {
        $params = [
            'symbol'            => $symbol,
            'side'              => strtoupper($side),
            'quantity'          => $quantity,
            'price'             => $price,
            'stopPrice'         => $stopPrice,
            'stopLimitPrice'    => $stopLimitPrice,
            'stopLimitTimeInForce' => 'GTC',
        ];
        $res = $this->post(BinanceConfig::ORDER_OCO, [], $params, true);
        return [
            'oco_group_id' => $res['orderListId'] ?? null,
            'limit_order'  => isset($res['orders'][0]) ? $this->getOrder($symbol, (string)$res['orders'][0]['orderId']) : null,
            'stop_order'   => isset($res['orders'][1]) ? $this->getOrder($symbol, (string)$res['orders'][1]['orderId']) : null,
            'raw'          => $res,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STAKING
    // ─────────────────────────────────────────────────────────────────────────

    public function stakeAsset(string $asset, float $amount): array
    {
        $products = $this->get(BinanceConfig::STAKING_PRODUCT_LIST, ['product' => 'STAKING', 'asset' => strtoupper($asset)], true);
        $productId = $products[0]['projectId'] ?? null;
        if (!$productId) throw new InvalidOrderException("Nenhum produto de staking encontrado para {$asset}", 'binance');

        $res = $this->post(BinanceConfig::STAKING_PURCHASE, [], ['product' => 'STAKING', 'productId' => $productId, 'amount' => $amount], true);
        return ['asset' => strtoupper($asset), 'staked' => $amount, 'position_id' => $res['positionId'] ?? null, 'status' => 'STAKED'];
    }

    public function unstakeAsset(string $asset, float $amount): array
    {
        $positions = $this->getStakingPositions();
        $position  = null;
        foreach ($positions as $p) {
            if (strtoupper($p['asset'] ?? '') === strtoupper($asset)) { $position = $p; break; }
        }
        if (!$position) throw new InvalidOrderException("Posição de staking não encontrada para {$asset}", 'binance');

        $res = $this->post(BinanceConfig::STAKING_REDEEM, [], ['product' => 'STAKING', 'productId' => $position['productId'], 'positionId' => $position['positionId'], 'amount' => $amount], true);
        return ['asset' => strtoupper($asset), 'unstaked' => $amount, 'status' => 'UNSTAKED'];
    }

    public function getStakingPositions(): array
    {
        return $this->get(BinanceConfig::STAKING_POSITION, ['product' => 'STAKING'], true);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EXTRAS BINANCE
    // ─────────────────────────────────────────────────────────────────────────

    /** Converte saldo mínimo (dust) em BNB */
    public function convertDustToBNB(array $assets): array
    {
        return $this->post(BinanceConfig::DUST_TRANSFER, [], ['asset' => $assets], true);
    }

    /** Histórico de dust convertido */
    public function getDustLog(): array
    {
        return $this->get(BinanceConfig::DUST_LOG, [], true);
    }

    /** Detalhe de taxas por par */
    public function getTradeFee(?string $symbol = null): array
    {
        $params = $this->filterNulls(['symbol' => $symbol]);
        return $this->get(BinanceConfig::TRADE_FEE, $params, true);
    }
}
