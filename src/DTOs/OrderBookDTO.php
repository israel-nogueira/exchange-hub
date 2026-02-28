<?php
namespace IsraelNogueira\ExchangeHub\DTOs;
class OrderBookDTO {
    public function __construct(
        public readonly string $symbol, public readonly array $bids,
        public readonly array $asks, public readonly int $timestamp,
        public readonly string $exchange = '',
    ) {}
    public function bestBid(): float { return (float)($this->bids[0][0] ?? 0); }
    public function bestAsk(): float { return (float)($this->asks[0][0] ?? 0); }
    public function midPrice(): float { return ($this->bestBid() + $this->bestAsk()) / 2; }
    public function toArray(): array { return ['symbol'=>$this->symbol,'bids'=>$this->bids,'asks'=>$this->asks,'timestamp'=>$this->timestamp,'exchange'=>$this->exchange]; }
}
