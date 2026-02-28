<?php

namespace IsraelNogueira\ExchangeHub\Traits;

use IsraelNogueira\ExchangeHub\Exceptions\RateLimitException;

/**
 * Controle local de rate limit por exchange.
 * Registra timestamps de cada request e bloqueia se ultrapassar o limite.
 */
trait HasRateLimit
{
    /** @var array<string, array<int>> timestamps por categoria */
    private array $rateLimitBuckets = [];

    /**
     * Verifica e registra um request no bucket especificado.
     *
     * @param string $bucket     ex: 'public', 'private', 'order'
     * @param int    $maxPerMin  máximo de requests por minuto
     * @throws RateLimitException se o limite for ultrapassado
     */
    protected function checkRateLimit(string $bucket, int $maxPerMin): void
    {
        $now    = time();
        $window = $now - 60;

        // Remove timestamps fora da janela de 1 minuto
        $this->rateLimitBuckets[$bucket] = array_values(
            array_filter($this->rateLimitBuckets[$bucket] ?? [], fn($ts) => $ts > $window)
        );

        $count = count($this->rateLimitBuckets[$bucket]);

        if ($count >= $maxPerMin) {
            $oldestInWindow = $this->rateLimitBuckets[$bucket][0];
            $retryAfter     = (int)($oldestInWindow + 60 - $now);
            throw new RateLimitException($this->name ?? '', max(1, $retryAfter));
        }

        $this->rateLimitBuckets[$bucket][] = $now;
    }

    /**
     * Retorna quantos requests restam no bucket na janela atual.
     */
    protected function rateLimitRemaining(string $bucket, int $maxPerMin): int
    {
        $window = time() - 60;
        $used   = count(array_filter($this->rateLimitBuckets[$bucket] ?? [], fn($ts) => $ts > $window));
        return max(0, $maxPerMin - $used);
    }

    protected function clearRateLimitBuckets(): void
    {
        $this->rateLimitBuckets = [];
    }
}
