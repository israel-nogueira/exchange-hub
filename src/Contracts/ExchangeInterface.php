<?php

namespace Exchanges\Contracts;

use Exchanges\DTOs\TickerDTO;
use Exchanges\DTOs\OrderBookDTO;
use Exchanges\DTOs\OrderDTO;
use Exchanges\DTOs\TradeDTO;
use Exchanges\DTOs\BalanceDTO;
use Exchanges\DTOs\CandleDTO;
use Exchanges\DTOs\DepositDTO;
use Exchanges\DTOs\WithdrawDTO;
use Exchanges\DTOs\ExchangeInfoDTO;

interface ExchangeInterface
{
    // ─────────────────────────────────────────────
    // MARKET DATA — Públicos
    // ─────────────────────────────────────────────

    /** Testa conectividade com a exchange */
    public function ping(): bool;

    /** Retorna timestamp do servidor da exchange */
    public function getServerTime(): int;

    /** Informações gerais da exchange (pares, limites, taxas) */
    public function getExchangeInfo(): ExchangeInfoDTO;

    /** Lista todos os pares/símbolos disponíveis */
    public function getSymbols(): array;

    /** Preço atual + variação 24h de um par */
    public function getTicker(string $symbol): TickerDTO;

    /** Snapshot completo de 24h de um par */
    public function getTicker24h(string $symbol): TickerDTO;

    /** Todos os tickers de uma vez */
    public function getAllTickers(): array;

    /** Livro de ofertas (bids/asks) */
    public function getOrderBook(string $symbol, int $limit = 20): OrderBookDTO;

    /** Últimas negociações do mercado */
    public function getRecentTrades(string $symbol, int $limit = 50): array;

    /** Histórico de trades passados */
    public function getHistoricalTrades(string $symbol, int $limit = 100, ?int $fromId = null): array;

    /** Velas OHLCV */
    public function getCandles(string $symbol, string $interval = '1h', int $limit = 100, ?int $startTime = null, ?int $endTime = null): array;

    /** Preço médio ponderado */
    public function getAvgPrice(string $symbol): float;

    // ─────────────────────────────────────────────
    // ACCOUNT — Privados
    // ─────────────────────────────────────────────

    /** Dados gerais da conta */
    public function getAccountInfo(): array;

    /** Saldos disponíveis e bloqueados */
    public function getBalances(): array;

    /** Saldo de um ativo específico */
    public function getBalance(string $asset): BalanceDTO;

    /** Taxas de maker/taker da conta */
    public function getCommissionRates(): array;

    /** Endereço de depósito de um ativo */
    public function getDepositAddress(string $asset, ?string $network = null): DepositDTO;

    /** Histórico de depósitos */
    public function getDepositHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array;

    /** Histórico de saques */
    public function getWithdrawHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array;

    /** Solicita saque */
    public function withdraw(string $asset, string $address, float $amount, ?string $network = null, ?string $memo = null): WithdrawDTO;

    // ─────────────────────────────────────────────
    // TRADING — Privados
    // ─────────────────────────────────────────────

    /** Cria nova ordem */
    public function createOrder(
        string $symbol,
        string $side,        // BUY | SELL
        string $type,        // MARKET | LIMIT | STOP_LIMIT | STOP_MARKET
        float  $quantity,
        ?float $price = null,
        ?float $stopPrice = null,
        ?string $timeInForce = 'GTC',
        ?string $clientOrderId = null
    ): OrderDTO;

    /** Cancela ordem pelo ID */
    public function cancelOrder(string $symbol, string $orderId): OrderDTO;

    /** Cancela todas as ordens abertas de um par */
    public function cancelAllOrders(string $symbol): array;

    /** Consulta uma ordem específica */
    public function getOrder(string $symbol, string $orderId): OrderDTO;

    /** Lista ordens abertas */
    public function getOpenOrders(?string $symbol = null): array;

    /** Histórico de ordens */
    public function getOrderHistory(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array;

    /** Trades executados pela conta */
    public function getMyTrades(string $symbol, int $limit = 100, ?int $startTime = null, ?int $endTime = null): array;

    /** Edita preço/quantidade de ordem ativa */
    public function editOrder(string $symbol, string $orderId, ?float $price = null, ?float $quantity = null): OrderDTO;

    /** Cria par de ordens OCO (One Cancels Other) */
    public function createOCOOrder(
        string $symbol,
        string $side,
        float  $quantity,
        float  $price,
        float  $stopPrice,
        float  $stopLimitPrice
    ): array;
}
