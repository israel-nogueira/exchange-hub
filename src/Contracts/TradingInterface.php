<?php

namespace IsraelNogueira\ExchangeHub\Contracts;

use IsraelNogueira\ExchangeHub\DTOs\OrderDTO;
use IsraelNogueira\ExchangeHub\DTOs\TradeDTO;

interface TradingInterface
{
    public function createOrder(
        string  $symbol,
        string  $side,
        string  $type,
        float   $quantity,
        ?float  $price         = null,
        ?float  $stopPrice     = null,
        ?string $timeInForce   = 'GTC',
        ?string $clientOrderId = null
    ): OrderDTO;

    public function cancelOrder(string $symbol, string $orderId): OrderDTO;

    public function cancelAllOrders(string $symbol): array;

    public function getOrder(string $symbol, string $orderId): OrderDTO;

    public function getOpenOrders(?string $symbol = null): array;

    public function getOrderHistory(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array;

    public function getMyTrades(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array;

    public function editOrder(string $symbol, string $orderId, ?float $price = null, ?float $quantity = null): OrderDTO;

    public function createOCOOrder(
        string $symbol,
        string $side,
        float  $quantity,
        float  $price,
        float  $stopPrice,
        float  $stopLimitPrice
    ): array;
}
