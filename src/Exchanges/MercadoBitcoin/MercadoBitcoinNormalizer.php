<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\MercadoBitcoin;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};

class MercadoBitcoinNormalizer
{
    public function ticker(string $symbol, array $d): TickerDTO
    {
        $t    = $d[0] ?? $d;
        $last = (float)($t['last'] ?? 0);
        $open = (float)($t['open'] ?? 0);
        return new TickerDTO(
            symbol:         $symbol,
            price:          $last,
            bid:            (float)($t['buy'] ?? 0),
            ask:            (float)($t['sell'] ?? 0),
            open24h:        $open,
            high24h:        (float)($t['high'] ?? 0),
            low24h:         (float)($t['low'] ?? 0),
            volume24h:      (float)($t['vol'] ?? 0),
            quoteVolume24h: 0,
            change24h:      $last - $open,
            changePct24h:   $open > 0 ? round(($last - $open) / $open * 100, 4) : 0,
            timestamp:      (int)($t['date'] ?? time()) * 1000,
            exchange:       'mercadobitcoin',
        );
    }

    public function orderBook(string $symbol, array $d): OrderBookDTO
    {
        return new OrderBookDTO(
            symbol:    $symbol,
            bids:      array_map(fn($b) => [(float)$b[0], (float)$b[1]], $d['bids'] ?? []),
            asks:      array_map(fn($a) => [(float)$a[0], (float)$a[1]], $d['asks'] ?? []),
            timestamp: time() * 1000,
            exchange:  'mercadobitcoin',
        );
    }

    public function order(array $d): OrderDTO
    {
        $sm = ['open' => OrderDTO::STATUS_OPEN, 'filled' => OrderDTO::STATUS_FILLED, 'cancelled' => OrderDTO::STATUS_CANCELLED];
        return new OrderDTO(
            orderId:       $d['id'] ?? '',
            clientOrderId: '',
            symbol:        $d['instrument'] ?? '',
            side:          strtoupper($d['side'] ?? 'BUY'),
            type:          strtoupper($d['type'] ?? 'LIMIT'),
            status:        $sm[$d['status'] ?? 'open'] ?? OrderDTO::STATUS_OPEN,
            quantity:      (float)($d['qty'] ?? 0),
            executedQty:   (float)($d['execQty'] ?? 0),
            price:         (float)($d['limitPrice'] ?? 0),
            avgPrice:      (float)($d['avgPrice'] ?? 0),
            stopPrice:     0,
            timeInForce:   'GTC',
            fee:           (float)($d['fee'] ?? 0),
            feeAsset:      'BRL',
            createdAt:     isset($d['created_at']) ? strtotime($d['created_at']) * 1000 : time() * 1000,
            updatedAt:     time() * 1000,
            exchange:      'mercadobitcoin',
        );
    }

    public function balance(string $asset, array $d): BalanceDTO
    {
        return new BalanceDTO(
            asset:    $asset,
            free:     (float)($d['available'] ?? 0),
            locked:   (float)($d['on_hold'] ?? 0),
            staked:   0,
            exchange: 'mercadobitcoin',
        );
    }

    public function candle(string $symbol, string $interval, array $d): CandleDTO
    {
        return new CandleDTO(
            symbol:      $symbol,
            interval:    $interval,
            openTime:    (int)($d['timestamp'] ?? 0) * 1000,
            open:        (float)($d['open'] ?? 0),
            high:        (float)($d['high'] ?? 0),
            low:         (float)($d['low'] ?? 0),
            close:       (float)($d['close'] ?? 0),
            volume:      (float)($d['volume'] ?? 0),
            quoteVolume: 0,
            trades:      0,
            closeTime:   (int)($d['timestamp'] ?? 0) * 1000 + 3600000,
            exchange:    'mercadobitcoin',
        );
    }
}
