<?php

namespace Exchanges\Exchanges\Fake;

use Exchanges\Storage\JsonStorage;

class FakePriceEngine
{
    public function __construct(
        private JsonStorage $storage,
        private FakeConfig  $config,
    ) {}

    /**
     * Retorna preço atual para o símbolo.
     * A cada chamada aplica variação aleatória dentro da volatilidade configurada.
     */
    public function getPrice(string $symbol): float
    {
        $tickers = $this->storage->read('market/tickers') ?? [];

        // Se não existe ainda, semeia com preço base
        if (!isset($tickers[$symbol])) {
            $basePrice = $this->config->basePrices[$symbol] ?? 1.0;
            $tickers[$symbol] = $this->buildInitialTicker($symbol, $basePrice);
            $this->storage->write('market/tickers', $tickers);
        }

        // Aplica variação aleatória
        $ticker  = $tickers[$symbol];
        $volatility = $this->config->priceVolatility;
        $change  = $ticker['price'] * (mt_rand(-1000, 1000) / 1000) * $volatility;
        $newPrice = max(0.00000001, $ticker['price'] + $change);

        // Atualiza high/low do dia
        $ticker['price']       = round($newPrice, $this->decimals($newPrice));
        $ticker['high_24h']    = max($ticker['high_24h'], $newPrice);
        $ticker['low_24h']     = min($ticker['low_24h'], $newPrice);
        $ticker['volume_24h'] += round(mt_rand(1, 500) / 100, 4);
        $ticker['timestamp']   = time() * 1000;

        // Spread simulado (0.05% a 0.15%)
        $spreadPct           = mt_rand(5, 15) / 10000;
        $ticker['bid']       = round($newPrice * (1 - $spreadPct), $this->decimals($newPrice));
        $ticker['ask']       = round($newPrice * (1 + $spreadPct), $this->decimals($newPrice));

        // Variação 24h
        $ticker['change_24h']     = round($newPrice - $ticker['open_24h'], $this->decimals($newPrice));
        $ticker['change_pct_24h'] = $ticker['open_24h'] > 0
            ? round((($newPrice - $ticker['open_24h']) / $ticker['open_24h']) * 100, 4)
            : 0;

        $tickers[$symbol] = $ticker;
        $this->storage->write('market/tickers', $tickers);

        return (float) $ticker['price'];
    }

    /**
     * Retorna ticker completo com todos os campos.
     */
    public function getTicker(string $symbol): array
    {
        $this->getPrice($symbol); // garante atualização
        $tickers = $this->storage->read('market/tickers') ?? [];
        return $tickers[$symbol] ?? [];
    }

    /**
     * Gera orderbook simulado a partir do preço atual.
     */
    public function getOrderBook(string $symbol, int $depth = 20): array
    {
        $price   = $this->getPrice($symbol);
        $bids    = [];
        $asks    = [];
        $step    = $price * 0.0001; // passo de 0.01%

        for ($i = 1; $i <= $depth; $i++) {
            $bidPrice = round($price - ($step * $i), $this->decimals($price));
            $askPrice = round($price + ($step * $i), $this->decimals($price));
            $qty      = round(mt_rand(10, 5000) / 1000, 4);
            $bids[]   = [$bidPrice, $qty];
            $asks[]   = [$askPrice, $qty];
        }

        return [
            'symbol'    => $symbol,
            'bids'      => $bids,
            'asks'      => $asks,
            'timestamp' => time() * 1000,
        ];
    }

    /**
     * Gera candle OHLCV sintético para um intervalo.
     */
    public function generateCandle(string $symbol, string $interval, int $openTime): array
    {
        $price  = $this->getPrice($symbol);
        $vol    = $this->config->priceVolatility * 2;
        $open   = $price * (1 + (mt_rand(-500, 500) / 100000));
        $close  = $price;
        $high   = max($open, $close) * (1 + abs(mt_rand(1, 100) / 100000));
        $low    = min($open, $close) * (1 - abs(mt_rand(1, 100) / 100000));
        $volume = round(mt_rand(100, 100000) / 100, 4);

        return [
            'open_time'    => $openTime,
            'open'         => round($open, $this->decimals($open)),
            'high'         => round($high, $this->decimals($high)),
            'low'          => round($low, $this->decimals($low)),
            'close'        => round($close, $this->decimals($close)),
            'volume'       => $volume,
            'quote_volume' => round($volume * $close, 2),
            'trades'       => mt_rand(50, 5000),
            'close_time'   => $openTime + $this->intervalToSeconds($interval) - 1,
        ];
    }

    /**
     * Retorna candles históricos — lê do storage ou gera sintéticos.
     */
    public function getCandles(string $symbol, string $interval, int $limit = 100): array
    {
        $key      = "market/candles/{$symbol}_{$interval}";
        $candles  = $this->storage->read($key) ?? [];

        // Gera candles históricos se não existirem
        if (empty($candles)) {
            $seconds = $this->intervalToSeconds($interval);
            $now     = time();
            for ($i = $limit; $i >= 0; $i--) {
                $openTime  = ($now - ($seconds * $i)) * 1000;
                $candles[] = $this->generateCandle($symbol, $interval, $openTime);
            }
            $this->storage->write($key, $candles);
        } else {
            // Adiciona candle atual se necessário
            $last    = end($candles);
            $seconds = $this->intervalToSeconds($interval);
            if (time() * 1000 > ($last['close_time'] ?? 0)) {
                $openTime  = ($last['close_time'] + 1);
                $candles[] = $this->generateCandle($symbol, $interval, $openTime);
                if (count($candles) > 500) {
                    array_shift($candles); // mantém no máximo 500
                }
                $this->storage->write($key, $candles);
            }
        }

        return array_slice($candles, -$limit);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function buildInitialTicker(string $symbol, float $price): array
    {
        $spread = $price * 0.001;
        return [
            'symbol'          => $symbol,
            'price'           => $price,
            'bid'             => round($price - $spread, $this->decimals($price)),
            'ask'             => round($price + $spread, $this->decimals($price)),
            'open_24h'        => $price,
            'high_24h'        => $price * 1.02,
            'low_24h'         => $price * 0.98,
            'volume_24h'      => round(mt_rand(1000, 50000) / 10, 2),
            'quote_volume_24h'=> 0,
            'change_24h'      => 0,
            'change_pct_24h'  => 0,
            'timestamp'       => time() * 1000,
        ];
    }

    private function decimals(float $price): int
    {
        return match(true) {
            $price >= 10000 => 2,
            $price >= 1000  => 2,
            $price >= 1     => 4,
            $price >= 0.01  => 5,
            default         => 8,
        };
    }

    private function intervalToSeconds(string $interval): int
    {
        $map = [
            '1m' => 60, '3m' => 180, '5m' => 300, '15m' => 900,
            '30m' => 1800, '1h' => 3600, '2h' => 7200, '4h' => 14400,
            '6h' => 21600, '8h' => 28800, '12h' => 43200, '1d' => 86400,
            '3d' => 259200, '1w' => 604800, '1M' => 2592000,
        ];
        return $map[$interval] ?? 3600;
    }
}
