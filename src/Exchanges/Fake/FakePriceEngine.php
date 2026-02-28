<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Fake;

use IsraelNogueira\ExchangeHub\Storage\JsonStorage;

class FakePriceEngine
{
    public function __construct(
        private JsonStorage $storage,
        private FakeConfig  $config,
    ) {}

    public function getPrice(string $symbol): float
    {
        $tickers = $this->storage->read('market/tickers') ?? [];
        if (!isset($tickers[$symbol])) {
            $basePrice         = $this->config->basePrices[$symbol] ?? 1.0;
            $tickers[$symbol]  = $this->buildInitialTicker($symbol, $basePrice);
            $this->storage->write('market/tickers', $tickers);
        }

        $ticker     = $tickers[$symbol];
        $volatility = $this->config->priceVolatility;
        $change     = $ticker['price'] * (mt_rand(-1000, 1000) / 1000) * $volatility;
        $newPrice   = max(0.00000001, $ticker['price'] + $change);

        $ticker['price']      = round($newPrice, $this->decimals($newPrice));
        $ticker['high_24h']   = max($ticker['high_24h'], $newPrice);
        $ticker['low_24h']    = min($ticker['low_24h'], $newPrice);
        $ticker['volume_24h'] += round(mt_rand(1, 500) / 100, 4);
        $ticker['timestamp']  = time() * 1000;

        $spreadPct      = mt_rand(5, 15) / 10000;
        $ticker['bid']  = round($newPrice * (1 - $spreadPct), $this->decimals($newPrice));
        $ticker['ask']  = round($newPrice * (1 + $spreadPct), $this->decimals($newPrice));

        $ticker['change_24h']     = round($newPrice - $ticker['open_24h'], $this->decimals($newPrice));
        $ticker['change_pct_24h'] = $ticker['open_24h'] > 0
            ? round((($newPrice - $ticker['open_24h']) / $ticker['open_24h']) * 100, 4)
            : 0;

        $tickers[$symbol] = $ticker;
        $this->storage->write('market/tickers', $tickers);

        return (float)$ticker['price'];
    }

    public function getTicker(string $symbol): array
    {
        $this->getPrice($symbol);
        $tickers = $this->storage->read('market/tickers') ?? [];
        return $tickers[$symbol] ?? [];
    }

    public function getOrderBook(string $symbol, int $depth = 20): array
    {
        $price = $this->getPrice($symbol);
        $bids  = [];
        $asks  = [];
        for ($i = 1; $i <= $depth; $i++) {
            $spread      = $price * (mt_rand(1, 100) / 10000) * $i;
            $bidVol      = round(mt_rand(10, 5000) / 1000, 6);
            $askVol      = round(mt_rand(10, 5000) / 1000, 6);
            $bids[]      = [round($price - $spread, $this->decimals($price)), $bidVol];
            $asks[]      = [round($price + $spread, $this->decimals($price)), $askVol];
        }
        return ['symbol' => $symbol, 'bids' => $bids, 'asks' => $asks, 'timestamp' => time() * 1000];
    }

    private function buildInitialTicker(string $symbol, float $basePrice): array
    {
        $spread = $basePrice * 0.001;
        return [
            'symbol'          => $symbol,
            'price'           => $basePrice,
            'bid'             => round($basePrice - $spread, $this->decimals($basePrice)),
            'ask'             => round($basePrice + $spread, $this->decimals($basePrice)),
            'open_24h'        => $basePrice,
            'high_24h'        => $basePrice,
            'low_24h'         => $basePrice,
            'volume_24h'      => 0,
            'quote_volume_24h'=> 0,
            'change_24h'      => 0,
            'change_pct_24h'  => 0,
            'timestamp'       => time() * 1000,
        ];
    }

    private function decimals(float $price): int
    {
        if ($price >= 1000)   return 2;
        if ($price >= 1)      return 4;
        if ($price >= 0.0001) return 6;
        return 8;
    }

    public function generateCandles(string $symbol, string $interval, int $count): array
    {
        $im       = ['1m'=>60,'5m'=>300,'15m'=>900,'30m'=>1800,'1h'=>3600,'4h'=>14400,'1d'=>86400,'1w'=>604800];
        $secs     = $im[$interval] ?? 3600;
        $candles  = [];
        $price    = $this->config->basePrices[$symbol] ?? 1.0;
        $now      = time();

        for ($i = $count; $i >= 0; $i--) {
            $openTime = ($now - ($i * $secs)) * 1000;
            $change   = $price * (mt_rand(-20, 20) / 1000);
            $open     = $price;
            $close    = max(0.00000001, $price + $change);
            $high     = max($open, $close) * (1 + mt_rand(1, 10) / 1000);
            $low      = min($open, $close) * (1 - mt_rand(1, 10) / 1000);
            $volume   = round(mt_rand(100, 5000) / 100, 4);
            $price    = $close;
            $candles[] = [
                'open_time'    => $openTime,
                'open'         => round($open, $this->decimals($open)),
                'high'         => round($high, $this->decimals($high)),
                'low'          => round($low, $this->decimals($low)),
                'close'        => round($close, $this->decimals($close)),
                'volume'       => $volume,
                'quote_volume' => round($volume * $price, 2),
                'trades'       => mt_rand(10, 500),
                'close_time'   => $openTime + ($secs * 1000) - 1,
            ];
        }
        return $candles;
    }
}
