<?php

namespace Exchanges\DTOs;

class ExchangeInfoDTO
{
    public function __construct(
        public readonly string $exchangeName,
        public readonly string $status,          // ONLINE | MAINTENANCE | OFFLINE
        public readonly array  $symbols,         // lista de pares disponíveis
        public readonly float  $makerFee,
        public readonly float  $takerFee,
        public readonly array  $rateLimits,      // [ ['type' => 'REQUESTS', 'limit' => 1200, 'interval' => '1m'] ]
        public readonly array  $networks,        // redes suportadas por ativo
        public readonly int    $timestamp,
    ) {}

    public function hasSymbol(string $symbol): bool
    {
        return in_array(strtoupper($symbol), array_map('strtoupper', $this->symbols));
    }

    public function isOnline(): bool
    {
        return $this->status === 'ONLINE';
    }

    public function toArray(): array
    {
        return [
            'exchange'    => $this->exchangeName,
            'status'      => $this->status,
            'symbols'     => $this->symbols,
            'maker_fee'   => $this->makerFee,
            'taker_fee'   => $this->takerFee,
            'rate_limits' => $this->rateLimits,
            'networks'    => $this->networks,
            'timestamp'   => $this->timestamp,
        ];
    }
}
