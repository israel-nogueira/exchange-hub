<?php

namespace IsraelNogueira\ExchangeHub\Enums;

enum OrderSide: string
{
    case BUY  = 'BUY';
    case SELL = 'SELL';

    public function opposite(): self
    {
        return match($this) {
            self::BUY  => self::SELL,
            self::SELL => self::BUY,
        };
    }
}
