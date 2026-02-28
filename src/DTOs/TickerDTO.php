<?php
namespace IsraelNogueira\ExchangeHub\DTOs;
class TickerDTO {
    public function __construct(
        public readonly string $symbol, public readonly float $price,
        public readonly float $bid, public readonly float $ask,
        public readonly float $open24h, public readonly float $high24h,
        public readonly float $low24h, public readonly float $volume24h,
        public readonly float $quoteVolume24h, public readonly float $change24h,
        public readonly float $changePct24h, public readonly int $timestamp,
        public readonly string $exchange = '',
    ) {}
    public function spread(): float { return $this->ask - $this->bid; }
    public function toArray(): array { return ['symbol'=>$this->symbol,'price'=>$this->price,'bid'=>$this->bid,'ask'=>$this->ask,'open_24h'=>$this->open24h,'high_24h'=>$this->high24h,'low_24h'=>$this->low24h,'volume_24h'=>$this->volume24h,'quote_vol_24h'=>$this->quoteVolume24h,'change_24h'=>$this->change24h,'change_pct_24h'=>$this->changePct24h,'timestamp'=>$this->timestamp,'exchange'=>$this->exchange]; }
}
