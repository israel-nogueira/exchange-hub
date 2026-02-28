<?php
namespace IsraelNogueira\ExchangeHub\DTOs;
class BalanceDTO {
    public function __construct(
        public readonly string $asset, public readonly float $free,
        public readonly float $locked, public readonly float $staked,
        public readonly string $exchange = '',
    ) {}
    public function total(): float { return $this->free + $this->locked + $this->staked; }
    public function toArray(): array { return ['asset'=>$this->asset,'free'=>$this->free,'locked'=>$this->locked,'staked'=>$this->staked,'total'=>$this->total(),'exchange'=>$this->exchange]; }
}
