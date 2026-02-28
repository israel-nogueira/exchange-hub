<?php

namespace IsraelNogueira\ExchangeHub\Enums;

enum CandleInterval: string
{
    case M1   = '1m';
    case M3   = '3m';
    case M5   = '5m';
    case M15  = '15m';
    case M30  = '30m';
    case H1   = '1h';
    case H2   = '2h';
    case H4   = '4h';
    case H6   = '6h';
    case H8   = '8h';
    case H12  = '12h';
    case D1   = '1d';
    case D3   = '3d';
    case W1   = '1w';
    case MON1 = '1M';

    /** Retorna duração em segundos */
    public function seconds(): int
    {
        return match($this) {
            self::M1   => 60,
            self::M3   => 180,
            self::M5   => 300,
            self::M15  => 900,
            self::M30  => 1800,
            self::H1   => 3600,
            self::H2   => 7200,
            self::H4   => 14400,
            self::H6   => 21600,
            self::H8   => 28800,
            self::H12  => 43200,
            self::D1   => 86400,
            self::D3   => 259200,
            self::W1   => 604800,
            self::MON1 => 2592000,
        };
    }
}
