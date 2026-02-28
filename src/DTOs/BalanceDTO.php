<?php

namespace Exchanges\DTOs;

class BalanceDTO
{
    public function __construct(
        public readonly string $asset,
        public readonly float  $free,      // disponível para negociação
        public readonly float  $locked,    // reservado em ordens abertas
        public readonly float  $staked,    // em staking
        public readonly string $exchange = '',
    ) {}

    public function total(): float
    {
        return $this->free + $this->locked + $this->staked;
    }

    public function toArray(): array
    {
        return [
            'asset'    => $this->asset,
            'free'     => $this->free,
            'locked'   => $this->locked,
            'staked'   => $this->staked,
            'total'    => $this->total(),
            'exchange' => $this->exchange,
        ];
    }
}
