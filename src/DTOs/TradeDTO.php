<?php
namespace IsraelNogueira\ExchangeHub\DTOs;
class TradeDTO {
    public function __construct(
        public readonly string $tradeId, public readonly string $orderId,
        public readonly string $symbol, public readonly string $side,
        public readonly float $price, public readonly float $quantity,
        public readonly float $quoteQty, public readonly float $fee,
        public readonly string $feeAsset, public readonly bool $isMaker,
        public readonly int $timestamp, public readonly string $exchange = '',
    ) {}
    public function toArray(): array { return ['trade_id'=>$this->tradeId,'order_id'=>$this->orderId,'symbol'=>$this->symbol,'side'=>$this->side,'price'=>$this->price,'quantity'=>$this->quantity,'quote_qty'=>$this->quoteQty,'fee'=>$this->fee,'fee_asset'=>$this->feeAsset,'is_maker'=>$this->isMaker,'timestamp'=>$this->timestamp,'exchange'=>$this->exchange]; }
}
