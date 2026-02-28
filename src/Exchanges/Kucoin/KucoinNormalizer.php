<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Kucoin;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};

class KucoinNormalizer
{
    public function ticker(array $d): TickerDTO
    {
        $t = $d['ticker'] ?? $d;
        return new TickerDTO(
            symbol:         $d['symbol'] ?? $t['symbol'] ?? '',
            price:          (float)($t['last'] ?? $t['price'] ?? 0),
            bid:            (float)($t['bestBid'] ?? $t['buy'] ?? 0),
            ask:            (float)($t['bestAsk'] ?? $t['sell'] ?? 0),
            open24h:        0,
            high24h:        (float)($t['high'] ?? 0),
            low24h:         (float)($t['low'] ?? 0),
            volume24h:      (float)($t['vol'] ?? 0),
            quoteVolume24h: (float)($t['volValue'] ?? 0),
            change24h:      (float)($t['changePrice'] ?? 0),
            changePct24h:   (float)($t['changeRate'] ?? 0) * 100,
            timestamp:      time() * 1000,
            exchange:       'kucoin',
        );
    }

    public function orderBook(string $symbol, array $d): OrderBookDTO
    {
        $b = $d['data'] ?? $d;
        return new OrderBookDTO(
            symbol:    $symbol,
            bids:      array_map(fn($x) => [(float)$x[0], (float)$x[1]], $b['bids'] ?? []),
            asks:      array_map(fn($x) => [(float)$x[0], (float)$x[1]], $b['asks'] ?? []),
            timestamp: (int)($b['time'] ?? time() * 1000),
            exchange:  'kucoin',
        );
    }

    public function order(array $d): OrderDTO
    {
        $active = $d['isActive'] ?? true;
        $cancel = $d['cancelExist'] ?? false;
        $status = $active ? OrderDTO::STATUS_OPEN : ($cancel ? OrderDTO::STATUS_CANCELLED : OrderDTO::STATUS_FILLED);
        $qty    = (float)($d['dealSize'] ?? 0);
        $funds  = (float)($d['dealFunds'] ?? 0);
        return new OrderDTO(
            orderId:       $d['id'],
            clientOrderId: $d['clientOid'] ?? '',
            symbol:        $d['symbol'],
            side:          strtoupper($d['side']),
            type:          strtoupper($d['type']),
            status:        $status,
            quantity:      (float)$d['size'],
            executedQty:   $qty,
            price:         (float)$d['price'],
            avgPrice:      ($qty > 0 && $funds > 0) ? $funds / $qty : (float)$d['price'],
            stopPrice:     0,
            timeInForce:   $d['timeInForce'] ?? 'GTC',
            fee:           (float)($d['fee'] ?? 0),
            feeAsset:      $d['feeCurrency'] ?? '',
            createdAt:     (int)($d['createdAt'] ?? time() * 1000),
            updatedAt:     time() * 1000,
            exchange:      'kucoin',
        );
    }

    public function balance(string $asset, array $d): BalanceDTO
    {
        return new BalanceDTO(
            asset:    $asset,
            free:     (float)($d['available'] ?? 0),
            locked:   (float)($d['holds'] ?? 0),
            staked:   0,
            exchange: 'kucoin',
        );
    }

    public function candle(string $symbol, string $interval, array $d): CandleDTO
    {
        return new CandleDTO(
            symbol:      $symbol,
            interval:    $interval,
            openTime:    (int)$d[0] * 1000,
            open:        (float)$d[1],
            close:       (float)$d[2],
            high:        (float)$d[3],
            low:         (float)$d[4],
            volume:      (float)$d[5],
            quoteVolume: (float)$d[6],
            trades:      0,
            closeTime:   (int)$d[0] * 1000 + 3600000,
            exchange:    'kucoin',
        );
    }

    public function depositAddress(string $asset, array $d): DepositDTO
    {
        return new DepositDTO(
            asset:     $asset,
            address:   $d['address'] ?? '',
            memo:      $d['memo'] ?? null,
            network:   $d['chain'] ?? '',
            depositId: null, amount: null, txId: null,
            status:    DepositDTO::STATUS_CONFIRMED,
            timestamp: null, exchange: 'kucoin',
        );
    }

    public function withdraw(array $d): WithdrawDTO
    {
        $sm = ['PROCESSING'=>WithdrawDTO::STATUS_PROCESSING,'WALLET_PROCESSING'=>WithdrawDTO::STATUS_PROCESSING,'SUCCESS'=>WithdrawDTO::STATUS_CONFIRMED,'FAILURE'=>WithdrawDTO::STATUS_FAILED];
        $a  = (float)($d['amount'] ?? 0);
        $f  = (float)($d['fee'] ?? 0);
        return new WithdrawDTO(
            withdrawId: $d['id'] ?? '',
            asset:      $d['currency'] ?? '',
            address:    $d['address'] ?? '',
            memo:       $d['memo'] ?? null,
            network:    $d['chain'] ?? '',
            amount:     $a, fee: $f, netAmount: $a - $f,
            txId:       $d['walletTxId'] ?? null,
            status:     $sm[$d['status'] ?? ''] ?? WithdrawDTO::STATUS_PENDING,
            timestamp:  (int)($d['createdAt'] ?? time() * 1000),
            exchange:   'kucoin',
        );
    }
}
