<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Okx;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};

class OkxNormalizer
{
    public function ticker(array $d): TickerDTO
    {
        $last    = (float)$d['last'];
        $open24h = (float)$d['open24h'];
        return new TickerDTO(
            symbol:         $d['instId'],
            price:          $last,
            bid:            (float)$d['bidPx'],
            ask:            (float)$d['askPx'],
            open24h:        $open24h,
            high24h:        (float)$d['high24h'],
            low24h:         (float)$d['low24h'],
            volume24h:      (float)$d['vol24h'],
            quoteVolume24h: (float)$d['volCcy24h'],
            change24h:      $last - $open24h,
            changePct24h:   $open24h > 0 ? round(($last - $open24h) / $open24h * 100, 4) : 0,
            timestamp:      (int)$d['ts'],
            exchange:       'okx',
        );
    }

    public function orderBook(array $d, string $symbol): OrderBookDTO
    {
        $b = $d['data'][0] ?? [];
        return new OrderBookDTO(
            symbol:    $symbol,
            bids:      array_map(fn($x) => [(float)$x[0], (float)$x[1]], $b['bids'] ?? []),
            asks:      array_map(fn($x) => [(float)$x[0], (float)$x[1]], $b['asks'] ?? []),
            timestamp: (int)($b['ts'] ?? time() * 1000),
            exchange:  'okx',
        );
    }

    public function order(array $d): OrderDTO
    {
        $statusMap = ['live' => OrderDTO::STATUS_OPEN, 'partially_filled' => OrderDTO::STATUS_PARTIAL, 'filled' => OrderDTO::STATUS_FILLED, 'canceled' => OrderDTO::STATUS_CANCELLED];
        return new OrderDTO(
            orderId:       $d['ordId'],
            clientOrderId: $d['clOrdId'] ?? '',
            symbol:        $d['instId'],
            side:          strtoupper($d['side']),
            type:          strtoupper($d['ordType']),
            status:        $statusMap[$d['state']] ?? OrderDTO::STATUS_OPEN,
            quantity:      (float)$d['sz'],
            executedQty:   (float)($d['fillSz'] ?? 0),
            price:         (float)$d['px'],
            avgPrice:      (float)($d['avgPx'] ?? 0),
            stopPrice:     0,
            timeInForce:   'GTC',
            fee:           (float)($d['fee'] ?? 0),
            feeAsset:      $d['feeCcy'] ?? '',
            createdAt:     (int)$d['cTime'],
            updatedAt:     (int)($d['uTime'] ?? $d['cTime']),
            exchange:      'okx',
        );
    }

    public function trade(array $d): TradeDTO
    {
        return new TradeDTO(
            tradeId:   $d['tradeId'],
            orderId:   $d['ordId'] ?? '',
            symbol:    $d['instId'],
            side:      strtoupper($d['side']),
            price:     (float)$d['fillPx'],
            quantity:  (float)$d['fillSz'],
            quoteQty:  (float)$d['fillPx'] * (float)$d['fillSz'],
            fee:       (float)($d['fee'] ?? 0),
            feeAsset:  $d['feeCcy'] ?? '',
            isMaker:   ($d['execType'] ?? '') === 'M',
            timestamp: (int)$d['ts'],
            exchange:  'okx',
        );
    }

    public function balance(string $asset, array $d): BalanceDTO
    {
        return new BalanceDTO(
            asset:    $asset,
            free:     (float)($d['availBal'] ?? 0),
            locked:   (float)($d['frozenBal'] ?? 0),
            staked:   0,
            exchange: 'okx',
        );
    }

    public function candle(string $symbol, string $interval, array $d): CandleDTO
    {
        return new CandleDTO(
            symbol:      $symbol,
            interval:    $interval,
            openTime:    (int)$d[0],
            open:        (float)$d[1],
            high:        (float)$d[2],
            low:         (float)$d[3],
            close:       (float)$d[4],
            volume:      (float)$d[5],
            quoteVolume: (float)$d[7],
            trades:      0,
            closeTime:   (int)$d[0] + 3600000,
            exchange:    'okx',
        );
    }

    public function depositAddress(string $asset, array $d): DepositDTO
    {
        return new DepositDTO(
            asset:     $asset,
            address:   $d['addr'] ?? '',
            memo:      $d['memo'] ?? null,
            network:   $d['chain'] ?? '',
            depositId: null,
            amount:    null,
            txId:      null,
            status:    DepositDTO::STATUS_CONFIRMED,
            timestamp: null,
            exchange:  'okx',
        );
    }

    public function withdraw(array $d): WithdrawDTO
    {
        $amount = (float)($d['amt'] ?? 0);
        $fee    = (float)($d['fee'] ?? 0);
        $sm     = ['pending' => WithdrawDTO::STATUS_PROCESSING, 'success' => WithdrawDTO::STATUS_CONFIRMED, 'failed' => WithdrawDTO::STATUS_FAILED];
        return new WithdrawDTO(
            withdrawId: $d['wdId'] ?? '',
            asset:      $d['ccy'] ?? '',
            address:    $d['toAddr'] ?? '',
            memo:       $d['tag'] ?? null,
            network:    $d['chain'] ?? '',
            amount:     $amount,
            fee:        $fee,
            netAmount:  $amount - $fee,
            txId:       $d['txId'] ?? null,
            status:     $sm[$d['state'] ?? ''] ?? WithdrawDTO::STATUS_PENDING,
            timestamp:  (int)($d['ts'] ?? time() * 1000),
            exchange:   'okx',
        );
    }
}
