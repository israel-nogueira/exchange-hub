<?php

namespace IsraelNogueira\ExchangeHub\Contracts;

use IsraelNogueira\ExchangeHub\DTOs\TickerDTO;
use IsraelNogueira\ExchangeHub\DTOs\OrderBookDTO;
use IsraelNogueira\ExchangeHub\DTOs\CandleDTO;
use IsraelNogueira\ExchangeHub\DTOs\TradeDTO;
use IsraelNogueira\ExchangeHub\DTOs\ExchangeInfoDTO;

interface MarketDataInterface
{
    public function ping(): bool;

    public function getServerTime(): int;

    public function getExchangeInfo(): ExchangeInfoDTO;

    public function getSymbols(): array;

    public function getTicker(string $symbol): TickerDTO;

    public function getTicker24h(string $symbol): TickerDTO;

    public function getAllTickers(): array;

    public function getOrderBook(string $symbol, int $limit = 20): OrderBookDTO;

    public function getRecentTrades(string $symbol, int $limit = 50): array;

    public function getHistoricalTrades(string $symbol, int $limit = 100, ?int $fromId = null): array;

    public function getCandles(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): array;

    public function getAvgPrice(string $symbol): float;
}
