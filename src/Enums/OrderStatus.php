<?php

namespace IsraelNogueira\ExchangeHub\Enums;

enum OrderStatus: string
{
    case OPEN             = 'OPEN';
    case FILLED           = 'FILLED';
    case PARTIALLY_FILLED = 'PARTIALLY_FILLED';
    case CANCELLED        = 'CANCELLED';
    case REJECTED         = 'REJECTED';
    case EXPIRED          = 'EXPIRED';

    public function isActive(): bool
    {
        return match($this) {
            self::OPEN, self::PARTIALLY_FILLED => true,
            default                            => false,
        };
    }

    public function isFinal(): bool
    {
        return !$this->isActive();
    }
}
