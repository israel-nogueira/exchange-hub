<?php

namespace IsraelNogueira\ExchangeHub\Contracts;

/**
 * Contrato principal de uma exchange.
 * Herda MarketDataInterface, TradingInterface e AccountInterface.
 *
 * Para exchanges que suportam apenas dados de mercado (sem trading privado),
 * implemente somente MarketDataInterface.
 */
interface ExchangeInterface extends MarketDataInterface, TradingInterface, AccountInterface
{
    public function getName(): string;

    public function isTestnet(): bool;
}
