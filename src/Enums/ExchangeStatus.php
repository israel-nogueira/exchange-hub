<?php

namespace IsraelNogueira\ExchangeHub\Enums;

enum ExchangeStatus: string
{
    case ONLINE      = 'ONLINE';
    case MAINTENANCE = 'MAINTENANCE';
    case OFFLINE     = 'OFFLINE';

    public function isOperational(): bool
    {
        return $this === self::ONLINE;
    }

    public function label(): string
    {
        return match($this) {
            self::ONLINE      => 'Online',
            self::MAINTENANCE => 'Em Manutenção',
            self::OFFLINE     => 'Offline',
        };
    }
}
