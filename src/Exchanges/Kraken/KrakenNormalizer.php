<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Kraken;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};

class KrakenNormalizer
{
    public function ticker(string $symbol, array $d): TickerDTO
    {
        $t    = $d[$symbol] ?? array_values($d)[0] ?? [];
        $last = (float)($t['c'][0] ?? 0);
        $open = (float)($t['o'] ?? 0);
        return new TickerDTO(
            symbol:         $symbol,
            price:          $last,
            bid:            (float)($t['b'][0] ?? 0),
            ask:            (float)($t['a'][0] ?? 0),
            open24h:        $open,
            high24h:        (float)($t['h'][1] ?? 0),
            low24h:         (float)($t['l'][1] ?? 0),
            volume24h:      (float)($t['v'][1] ?? 0),
            quoteVolume24h: 0,
            change24h:      $last - $open,
            changePct24h:   $open > 0 ? round(($last - $open) / $open * 100, 4) : 0,
            timestamp:      time() * 1000,
            exchange:       'kraken',
        );
    }

    public function orderBook(string $symbol, array $d): OrderBookDTO
    {
        $book = array_values($d)[0] ?? [];
        return new OrderBookDTO(
            symbol:    $symbol,
            bids:      array_map(fn($b) => [(float)$b[0], (float)$b[1]], $book['bids'] ?? []),
            asks:      array_map(fn($a) => [(float)$a[0], (float)$a[1]], $book['asks'] ?? []),
            timestamp: time() * 1000,
            exchange:  'kraken',
        );
    }

    public function order(string $orderId, array $d): OrderDTO
    {
        $desc = $d['descr'] ?? [];
        $sm   = ['open'=>OrderDTO::STATUS_OPEN,'pending'=>OrderDTO::STATUS_OPEN,'closed'=>OrderDTO::STATUS_FILLED,'canceled'=>OrderDTO::STATUS_CANCELLED,'expired'=>OrderDTO::STATUS_EXPIRED];
        return new OrderDTO(
            orderId:       $orderId,
            clientOrderId: $d['userref'] ?? '',
            symbol:        $desc['pair'] ?? '',
            side:          strtoupper($desc['type'] ?? ''),
            type:          strtoupper($desc['ordertype'] ?? ''),
            status:        $sm[$d['status']] ?? OrderDTO::STATUS_OPEN,
            quantity:      (float)($d['vol'] ?? 0),
            executedQty:   (float)($d['vol_exec'] ?? 0),
            price:         (float)($desc['price'] ?? 0),
            avgPrice:      (float)($d['price'] ?? 0),
            stopPrice:     (float)($desc['price2'] ?? 0),
            timeInForce:   'GTC',
            fee:           (float)($d['fee'] ?? 0),
            feeAsset:      '',
            createdAt:     (int)(($d['opentm'] ?? 0) * 1000),
            updatedAt:     (int)(($d['closetm'] ?? $d['opentm'] ?? 0) * 1000),
            exchange:      'kraken',
        );
    }

    public function balance(string $asset, float $amount): BalanceDTO
    {
        return new BalanceDTO(asset: $asset, free: $amount, locked: 0, staked: 0, exchange: 'kraken');
    }

    public function candle(string $symbol, string $interval, array $d): CandleDTO
    {
        return new CandleDTO(
            symbol:      $symbol,
            interval:    $interval,
            openTime:    (int)($d[0] * 1000),
            open:        (float)$d[1],
            high:        (float)$d[2],
            low:         (float)$d[3],
            close:       (float)$d[4],
            volume:      (float)$d[6],
            quoteVolume: 0,
            trades:      (int)$d[7],
            closeTime:   (int)($d[0] * 1000) + 3600000,
            exchange:    'kraken',
        );
    }

    public function depositAddress(string $asset, array $d): DepositDTO
    {
        return new DepositDTO(
            asset:     $asset,
            address:   $d['address'] ?? '',
            memo:      $d['tag'] ?? null,
            network:   '',
            depositId: null,
            amount:    null,
            txId:      null,
            status:    DepositDTO::STATUS_CONFIRMED,
            timestamp: null,
            exchange:  'kraken',
        );
    }

    public function withdraw(array $d): WithdrawDTO
    {
        $amount = (float)($d['amount'] ?? 0);
        $fee    = (float)($d['fee'] ?? 0);
        return new WithdrawDTO(
            withdrawId: $d['refid'] ?? '',
            asset:      $d['asset'] ?? '',
            address:    $d['info'] ?? '',
            memo:       null,
            network:    '',
            amount:     $amount,
            fee:        $fee,
            netAmount:  $amount - $fee,
            txId:       $d['txid'] ?? null,
            status:     WithdrawDTO::STATUS_PENDING,
            timestamp:  (int)(($d['time'] ?? 0) * 1000),
            exchange:   'kraken',
        );
    }
}
