<?php

namespace IsraelNogueira\ExchangeHub\Contracts;

use IsraelNogueira\ExchangeHub\DTOs\BalanceDTO;
use IsraelNogueira\ExchangeHub\DTOs\DepositDTO;
use IsraelNogueira\ExchangeHub\DTOs\WithdrawDTO;

interface AccountInterface
{
    public function getAccountInfo(): array;

    public function getBalances(): array;

    public function getBalance(string $asset): BalanceDTO;

    public function getCommissionRates(): array;

    public function getDepositAddress(string $asset, ?string $network = null): DepositDTO;

    public function getDepositHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array;

    public function getWithdrawHistory(?string $asset = null, ?int $startTime = null, ?int $endTime = null): array;

    public function withdraw(string $asset, string $address, float $amount, ?string $network = null, ?string $memo = null): WithdrawDTO;

    public function stakeAsset(string $asset, float $amount): array;

    public function unstakeAsset(string $asset, float $amount): array;

    public function getStakingPositions(): array;
}
