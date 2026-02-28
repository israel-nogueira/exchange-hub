<?php

namespace Exchanges\DTOs;

class OrderBookDTO
{
    public function __construct(
        public readonly string $symbol,
        public readonly array  $bids,      // [ [price, quantity], ... ] ordenado maior→menor
        public readonly array  $asks,      // [ [price, quantity], ... ] ordenado menor→maior
        public readonly int    $timestamp,
        public readonly string $exchange = '',
    ) {}

    public function bestBid(): float
    {
        return (float) ($this->bids[0][0] ?? 0);
    }

    public function bestAsk(): float
    {
        return (float) ($this->asks[0][0] ?? 0);
    }

    public function spread(): float
    {
        return $this->bestAsk() - $this->bestBid();
    }

    public function spreadPct(): float
    {
        $mid = ($this->bestBid() + $this->bestAsk()) / 2;
        return $mid > 0 ? ($this->spread() / $mid) * 100 : 0;
    }

    public function toArray(): array
    {
        return [
            'symbol'    => $this->symbol,
            'bids'      => $this->bids,
            'asks'      => $this->asks,
            'timestamp' => $this->timestamp,
            'exchange'  => $this->exchange,
            'best_bid'  => $this->bestBid(),
            'best_ask'  => $this->bestAsk(),
            'spread'    => $this->spread(),
        ];
    }
}
