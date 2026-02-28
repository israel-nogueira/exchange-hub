<?php

namespace IsraelNogueira\ExchangeHub\Enums;

enum OrderType: string
{
    case MARKET           = 'MARKET';
    case LIMIT            = 'LIMIT';
    case STOP             = 'STOP';
    case STOP_LIMIT       = 'STOP_LIMIT';
    case STOP_MARKET      = 'STOP_MARKET';
    case TAKE_PROFIT      = 'TAKE_PROFIT';
    case TAKE_PROFIT_LIMIT = 'TAKE_PROFIT_LIMIT';
    case OCO              = 'OCO';

    public function requiresPrice(): bool
    {
        return in_array($this, [self::LIMIT, self::STOP_LIMIT, self::TAKE_PROFIT_LIMIT]);
    }

    public function requiresStopPrice(): bool
    {
        return in_array($this, [self::STOP, self::STOP_LIMIT, self::STOP_MARKET, self::TAKE_PROFIT, self::TAKE_PROFIT_LIMIT]);
    }
}
