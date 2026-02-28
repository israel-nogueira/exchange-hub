<?php

namespace Exchanges\DTOs;

class WithdrawDTO
{
    const STATUS_PENDING    = 'PENDING';
    const STATUS_PROCESSING = 'PROCESSING';
    const STATUS_SENT       = 'SENT';
    const STATUS_CONFIRMED  = 'CONFIRMED';
    const STATUS_FAILED     = 'FAILED';
    const STATUS_CANCELLED  = 'CANCELLED';

    public function __construct(
        public readonly string  $withdrawId,
        public readonly string  $asset,
        public readonly string  $address,
        public readonly ?string $memo,
        public readonly string  $network,
        public readonly float   $amount,
        public readonly float   $fee,
        public readonly float   $netAmount,    // amount - fee
        public readonly ?string $txId,
        public readonly string  $status,
        public readonly int     $timestamp,
        public readonly string  $exchange = '',
    ) {}

    public function toArray(): array
    {
        return [
            'withdraw_id' => $this->withdrawId,
            'asset'       => $this->asset,
            'address'     => $this->address,
            'memo'        => $this->memo,
            'network'     => $this->network,
            'amount'      => $this->amount,
            'fee'         => $this->fee,
            'net_amount'  => $this->netAmount,
            'tx_id'       => $this->txId,
            'status'      => $this->status,
            'timestamp'   => $this->timestamp,
            'exchange'    => $this->exchange,
        ];
    }
}
