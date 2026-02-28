<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Bybit;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};

class BybitNormalizer
{
    public function ticker(array $d): TickerDTO
    {
        $last  = (float)$d['lastPrice'];
        $open  = (float)($d['prevPrice24h'] ?? 0);
        $pct   = (float)($d['price24hPcnt'] ?? 0);
        return new TickerDTO(
            symbol:         $d['symbol'],
            price:          $last,
            bid:            (float)($d['bid1Price'] ?? 0),
            ask:            (float)($d['ask1Price'] ?? 0),
            open24h:        $open,
            high24h:        (float)($d['highPrice24h'] ?? 0),
            low24h:         (float)($d['lowPrice24h'] ?? 0),
            volume24h:      (float)($d['volume24h'] ?? 0),
            quoteVolume24h: (float)($d['turnover24h'] ?? 0),
            change24h:      $pct * $last,
            changePct24h:   $pct * 100,
            timestamp:      time() * 1000,
            exchange:       'bybit',
        );
    }

    public function orderBook(string $symbol, array $d): OrderBookDTO
    {
        $b = $d['result'] ?? [];
        return new OrderBookDTO(
            symbol:    $symbol,
            bids:      array_map(fn($x) => [(float)$x[0], (float)$x[1]], $b['b'] ?? []),
            asks:      array_map(fn($x) => [(float)$x[0], (float)$x[1]], $b['a'] ?? []),
            timestamp: (int)($b['ts'] ?? time() * 1000),
            exchange:  'bybit',
        );
    }

    public function order(array $d): OrderDTO
    {
        $sm = [
            'New'             => OrderDTO::STATUS_OPEN,
            'PartiallyFilled' => OrderDTO::STATUS_PARTIAL,
            'Filled'          => OrderDTO::STATUS_FILLED,
            'Cancelled'       => OrderDTO::STATUS_CANCELLED,
            'Rejected'        => OrderDTO::STATUS_REJECTED,
        ];
        return new OrderDTO(
            orderId:       $d['orderId'],
            clientOrderId: $d['orderLinkId'] ?? '',
            symbol:        $d['symbol'],
            side:          strtoupper($d['side']),
            type:          strtoupper($d['orderType']),
            status:        $sm[$d['orderStatus']] ?? OrderDTO::STATUS_OPEN,
            quantity:      (float)$d['qty'],
            executedQty:   (float)($d['cumExecQty'] ?? 0),
            price:         (float)$d['price'],
            avgPrice:      (float)($d['avgPrice'] ?? 0),
            stopPrice:     (float)($d['triggerPrice'] ?? 0),
            timeInForce:   $d['timeInForce'] ?? 'GTC',
            fee:           (float)($d['cumExecFee'] ?? 0),
            feeAsset:      $d['feeCurrency'] ?? '',
            createdAt:     (int)$d['createdTime'],
            updatedAt:     (int)($d['updatedTime'] ?? $d['createdTime']),
            exchange:      'bybit',
        );
    }

    public function balance(string $asset, array $d): BalanceDTO
    {
        return new BalanceDTO(
            asset:    $asset,
            free:     (float)($d['availableToWithdraw'] ?? $d['free'] ?? 0),
            locked:   (float)($d['locked'] ?? 0),
            staked:   0,
            exchange: 'bybit',
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
            quoteVolume: (float)($d[6] ?? 0),
            trades:      0,
            closeTime:   (int)$d[0] + 3600000,
            exchange:    'bybit',
        );
    }

    public function depositAddress(string $asset, array $d): DepositDTO
    {
        return new DepositDTO(
            asset:     $asset,
            address:   $d['address'] ?? '',
            memo:      $d['tag'] ?? null,
            network:   $d['chain'] ?? '',
            depositId: null,
            amount:    null,
            txId:      null,
            status:    DepositDTO::STATUS_CONFIRMED,
            timestamp: null,
            exchange:  'bybit',
        );
    }

    public function withdraw(array $d): WithdrawDTO
    {
        $amount = (float)($d['amount'] ?? 0);
        $fee    = (float)($d['withdrawFee'] ?? 0);
        return new WithdrawDTO(
            withdrawId: $d['withdrawId'] ?? '',
            asset:      $d['coin'] ?? '',
            address:    $d['address'] ?? '',
            memo:       $d['tag'] ?? null,
            network:    $d['chain'] ?? '',
            amount:     $amount,
            fee:        $fee,
            netAmount:  $amount - $fee,
            txId:       $d['txID'] ?? null,
            status:     WithdrawDTO::STATUS_PENDING,
            timestamp:  (int)($d['createTime'] ?? time() * 1000),
            exchange:   'bybit',
        );
    }
}
