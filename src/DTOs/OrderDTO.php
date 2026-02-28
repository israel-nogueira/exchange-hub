<?php

namespace Exchanges\DTOs;

class OrderDTO
{
    // Status possíveis
    const STATUS_OPEN      = 'OPEN';
    const STATUS_FILLED    = 'FILLED';
    const STATUS_PARTIAL   = 'PARTIALLY_FILLED';
    const STATUS_CANCELLED = 'CANCELLED';
    const STATUS_REJECTED  = 'REJECTED';
    const STATUS_EXPIRED   = 'EXPIRED';

    // Lados
    const SIDE_BUY  = 'BUY';
    const SIDE_SELL = 'SELL';

    // Tipos
    const TYPE_MARKET     = 'MARKET';
    const TYPE_LIMIT      = 'LIMIT';
    const TYPE_STOP_LIMIT = 'STOP_LIMIT';
    const TYPE_STOP_MARKET= 'STOP_MARKET';
    const TYPE_OCO        = 'OCO';

    public function __construct(
        public readonly string  $orderId,
        public readonly string  $clientOrderId,
        public readonly string  $symbol,
        public readonly string  $side,            // BUY | SELL
        public readonly string  $type,            // MARKET | LIMIT | ...
        public readonly string  $status,          // OPEN | FILLED | ...
        public readonly float   $quantity,
        public readonly float   $executedQty,
        public readonly float   $price,
        public readonly float   $avgPrice,
        public readonly float   $stopPrice,
        public readonly string  $timeInForce,     // GTC | IOC | FOK
        public readonly float   $fee,
        public readonly string  $feeAsset,
        public readonly int     $createdAt,
        public readonly int     $updatedAt,
        public readonly string  $exchange = '',
    ) {}

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isFilled(): bool
    {
        return $this->status === self::STATUS_FILLED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function remainingQty(): float
    {
        return $this->quantity - $this->executedQty;
    }

    public function toArray(): array
    {
        return [
            'order_id'        => $this->orderId,
            'client_order_id' => $this->clientOrderId,
            'symbol'          => $this->symbol,
            'side'            => $this->side,
            'type'            => $this->type,
            'status'          => $this->status,
            'quantity'        => $this->quantity,
            'executed_qty'    => $this->executedQty,
            'remaining_qty'   => $this->remainingQty(),
            'price'           => $this->price,
            'avg_price'       => $this->avgPrice,
            'stop_price'      => $this->stopPrice,
            'time_in_force'   => $this->timeInForce,
            'fee'             => $this->fee,
            'fee_asset'       => $this->feeAsset,
            'created_at'      => $this->createdAt,
            'updated_at'      => $this->updatedAt,
            'exchange'        => $this->exchange,
        ];
    }
}
