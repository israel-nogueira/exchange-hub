<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Bitget;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};
class BitgetNormalizer
{
    public function ticker(array $d): TickerDTO
    {
        return new TickerDTO(
            symbol:         $d['symbol'] ?? '',
            price:          (float)($d['lastPr'] ?? $d['close'] ?? 0),
            bid:            (float)($d['bidPr'] ?? 0),
            ask:            (float)($d['askPr'] ?? 0),
            open24h:        (float)($d['open24h'] ?? 0),
            high24h:        (float)($d['high24h'] ?? 0),
            low24h:         (float)($d['low24h'] ?? 0),
            volume24h:      (float)($d['baseVolume'] ?? 0),
            quoteVolume24h: (float)($d['quoteVolume'] ?? 0),
            change24h:      (float)($d['change24h'] ?? 0),
            changePct24h:   (float)(($d['change24h'] ?? 0) * 100),
            timestamp:      (int)($d['ts'] ?? time() * 1000),
            exchange:       'bitget',
        );
    }
    public function orderBook(array $d, string $symbol): OrderBookDTO
    {
        $data = $d['data'] ?? $d;
        return new OrderBookDTO(
            symbol:    $symbol,
            bids:      array_map(fn($b) => [(float)$b[0], (float)$b[1]], $data['bids'] ?? []),
            asks:      array_map(fn($a) => [(float)$a[0], (float)$a[1]], $data['asks'] ?? []),
            timestamp: (int)($data['ts'] ?? time() * 1000),
            exchange:  'bitget',
        );
    }
    public function order(array $d): OrderDTO
    {
        $sm = ['live'=>OrderDTO::STATUS_OPEN,'new'=>OrderDTO::STATUS_OPEN,'partially_fill'=>OrderDTO::STATUS_PARTIAL,'full_fill'=>OrderDTO::STATUS_FILLED,'filled'=>OrderDTO::STATUS_FILLED,'cancelled'=>OrderDTO::STATUS_CANCELLED,'cancel'=>OrderDTO::STATUS_CANCELLED];
        return new OrderDTO(
            orderId:       $d['orderId'] ?? '',
            clientOrderId: $d['clientOid'] ?? '',
            symbol:        $d['symbol'] ?? '',
            side:          strtoupper($d['side'] ?? ''),
            type:          strtoupper($d['orderType'] ?? 'LIMIT'),
            status:        $sm[strtolower($d['status'] ?? '')] ?? OrderDTO::STATUS_OPEN,
            quantity:      (float)($d['size'] ?? $d['origSize'] ?? 0),
            executedQty:   (float)($d['fillSize'] ?? $d['baseVolume'] ?? 0),
            price:         (float)($d['price'] ?? 0),
            avgPrice:      (float)($d['fillPrice'] ?? $d['priceAvg'] ?? 0),
            stopPrice:     0,
            timeInForce:   strtoupper($d['timeInForceValue'] ?? 'GTC'),
            fee:           (float)($d['feeDetail']['totalFee'] ?? $d['totalFee'] ?? 0),
            feeAsset:      $d['feeDetail']['feeCoin'] ?? '',
            createdAt:     (int)($d['cTime'] ?? $d['createTime'] ?? time() * 1000),
            updatedAt:     (int)($d['uTime'] ?? $d['updateTime'] ?? time() * 1000),
            exchange:      'bitget',
        );
    }
    public function trade(array $d): TradeDTO
    {
        return new TradeDTO(
            tradeId:   $d['tradeId'] ?? '',
            orderId:   $d['orderId'] ?? '',
            symbol:    $d['symbol'] ?? '',
            side:      strtoupper($d['side'] ?? ''),
            price:     (float)($d['priceAvg'] ?? $d['price'] ?? 0),
            quantity:  (float)($d['size'] ?? $d['fillSize'] ?? 0),
            quoteQty:  (float)($d['amount'] ?? 0),
            fee:       (float)($d['feeDetail']['totalFee'] ?? $d['fee'] ?? 0),
            feeAsset:  $d['feeCurrency'] ?? '',
            isMaker:   ($d['tradeScope'] ?? '') === 'maker',
            timestamp: (int)($d['cTime'] ?? time() * 1000),
            exchange:  'bitget',
        );
    }
    public function balance(string $asset, array $d): BalanceDTO
    {
        return new BalanceDTO(asset: strtoupper($asset), free: (float)($d['available'] ?? 0), locked: (float)($d['frozen'] ?? $d['lock'] ?? 0), staked: 0, exchange: 'bitget');
    }
    public function candle(string $symbol, string $interval, array $d): CandleDTO
    {
        return new CandleDTO(symbol: $symbol, interval: $interval, openTime: (int)($d[0] ?? 0), open: (float)($d[1] ?? 0), high: (float)($d[2] ?? 0), low: (float)($d[3] ?? 0), close: (float)($d[4] ?? 0), volume: (float)($d[5] ?? 0), quoteVolume: (float)($d[6] ?? 0), trades: 0, closeTime: (int)($d[0] ?? 0) + 59999, exchange: 'bitget');
    }
    public function depositAddress(array $d): DepositDTO
    {
        return new DepositDTO(asset: $d['coin'] ?? '', address: $d['address'] ?? '', memo: $d['tag'] ?: null, network: $d['chain'] ?? '', depositId: null, amount: null, txId: null, status: DepositDTO::STATUS_CONFIRMED, timestamp: null, exchange: 'bitget');
    }
    public function deposit(array $d): DepositDTO
    {
        $sm = ['success'=>DepositDTO::STATUS_CONFIRMED,'pending'=>DepositDTO::STATUS_PENDING,'fail'=>DepositDTO::STATUS_FAILED];
        return new DepositDTO(asset: $d['coin'] ?? '', address: $d['toAddress'] ?? '', memo: null, network: $d['chain'] ?? '', depositId: $d['orderId'] ?? null, amount: (float)($d['amount'] ?? 0), txId: $d['tradeId'] ?? null, status: $sm[$d['status'] ?? ''] ?? DepositDTO::STATUS_PENDING, timestamp: isset($d['cTime']) ? (int)$d['cTime'] : null, exchange: 'bitget');
    }
    public function withdraw(array $d): WithdrawDTO
    {
        $sm = ['success'=>WithdrawDTO::STATUS_CONFIRMED,'pending'=>WithdrawDTO::STATUS_PENDING,'fail'=>WithdrawDTO::STATUS_FAILED,'cancel'=>WithdrawDTO::STATUS_CANCELLED,'processing'=>WithdrawDTO::STATUS_PROCESSING];
        $amount = (float)($d['amount'] ?? 0);
        $fee    = (float)($d['fee'] ?? 0);
        return new WithdrawDTO(withdrawId: $d['orderId'] ?? '', asset: $d['coin'] ?? '', address: $d['toAddress'] ?? '', memo: null, network: $d['chain'] ?? '', amount: $amount, fee: $fee, netAmount: $amount - $fee, txId: $d['tradeId'] ?? null, status: $sm[strtolower($d['status'] ?? '')] ?? WithdrawDTO::STATUS_PENDING, timestamp: isset($d['cTime']) ? (int)$d['cTime'] : time() * 1000, exchange: 'bitget');
    }
}
