<?php

namespace IsraelNogueira\ExchangeHub\Traits;

/**
 * Cache em memória para candles já buscados na sessão.
 * Evita requests duplicados para o mesmo símbolo/intervalo.
 */
trait HasCandleCache
{
    private array $candleCache = [];

    protected function getCachedCandles(string $symbol, string $interval, int $limit): ?array
    {
        $key = "{$symbol}_{$interval}_{$limit}";
        $entry = $this->candleCache[$key] ?? null;

        if ($entry === null) {
            return null;
        }

        // Cache válido por 30 segundos para evitar dados muito desatualizados
        if ((time() - $entry['ts']) > 30) {
            unset($this->candleCache[$key]);
            return null;
        }

        return $entry['data'];
    }

    protected function setCachedCandles(string $symbol, string $interval, int $limit, array $candles): void
    {
        $key = "{$symbol}_{$interval}_{$limit}";
        $this->candleCache[$key] = [
            'data' => $candles,
            'ts'   => time(),
        ];
    }

    protected function clearCandleCache(?string $symbol = null): void
    {
        if ($symbol === null) {
            $this->candleCache = [];
            return;
        }

        foreach (array_keys($this->candleCache) as $key) {
            if (str_starts_with($key, $symbol . '_')) {
                unset($this->candleCache[$key]);
            }
        }
    }
}
