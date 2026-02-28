<?php

namespace IsraelNogueira\ExchangeHub\Traits;

/**
 * Helpers para exchanges que suportam ambiente testnet/sandbox.
 */
trait HasTestnet
{
    protected bool $testnet = false;

    /**
     * Resolve a URL correta conforme mainnet ou testnet.
     */
    protected function resolveBaseUrl(string $mainnetUrl, string $testnetUrl): string
    {
        return $this->testnet ? $testnetUrl : $mainnetUrl;
    }

    public function isTestnet(): bool
    {
        return $this->testnet;
    }

    public function switchToTestnet(): static
    {
        $this->testnet = true;
        $this->configure();
        return $this;
    }

    public function switchToMainnet(): static
    {
        $this->testnet = false;
        $this->configure();
        return $this;
    }
}
