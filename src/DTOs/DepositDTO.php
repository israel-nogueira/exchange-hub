<?php
namespace IsraelNogueira\ExchangeHub\DTOs;
class DepositDTO {
    const STATUS_PENDING='PENDING'; const STATUS_CONFIRMED='CONFIRMED';
    const STATUS_CREDITED='CREDITED'; const STATUS_FAILED='FAILED';
    public function __construct(
        public readonly string $asset, public readonly string $address,
        public readonly ?string $memo, public readonly string $network,
        public readonly ?string $depositId, public readonly ?float $amount,
        public readonly ?string $txId, public readonly string $status,
        public readonly ?int $timestamp, public readonly string $exchange = '',
    ) {}
    public function toArray(): array { return ['asset'=>$this->asset,'address'=>$this->address,'memo'=>$this->memo,'network'=>$this->network,'deposit_id'=>$this->depositId,'amount'=>$this->amount,'tx_id'=>$this->txId,'status'=>$this->status,'timestamp'=>$this->timestamp,'exchange'=>$this->exchange]; }
}
