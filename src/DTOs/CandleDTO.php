<?php

namespace Exchanges\DTOs;

class CandleDTO
{
    public function __construct(
        public readonly string $symbol,
        public readonly string $interval,
        public readonly int    $openTime,
        public readonly float  $open,
        public readonly float  $high,
        public readonly float  $low,
        public readonly float  $close,
        public readonly float  $volume,
        public readonly float  $quoteVolume,
        public readonly int    $trades,
        public readonly int    $closeTime,
        public readonly string $exchange = '',
    ) {}

    public function isBullish(): bool
    {
        return $this->close >= $this->open;
    }

    public function bodySize(): float
    {
        return abs($this->close - $this->open);
    }

    public function range(): float
    {
        return $this->high - $this->low;
    }

    public function toArray(): array
    {
        return [
            'symbol'       => $this->symbol,
            'interval'     => $this->interval,
            'open_time'    => $this->openTime,
            'open'         => $this->open,
            'high'         => $this->high,
            'low'          => $this->low,
            'close'        => $this->close,
            'volume'       => $this->volume,
            'quote_volume' => $this->quoteVolume,
            'trades'       => $this->trades,
            'close_time'   => $this->closeTime,
            'is_bullish'   => $this->isBullish(),
            'exchange'     => $this->exchange,
        ];
    }
}
