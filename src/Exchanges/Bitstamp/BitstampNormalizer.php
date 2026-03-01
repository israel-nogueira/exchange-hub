<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Bitstamp;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};
class BitstampNormalizer
{
    public function ticker(string $symbol, array $d): TickerDTO
    {
        return new TickerDTO(
            symbol:         strtoupper($symbol),
            price:          (float)($d['last'] ?? 0),
            bid:            (float)($d['bid'] ?? 0),
            ask:            (float)($d['ask'] ?? 0),
            open24h:        (float)($d['open'] ?? 0),
            high24h:        (float)($d['high'] ?? 0),
            low24h:         (float)($d['low'] ?? 0),
            volume24h:      (float)($d['volume'] ?? 0),
            quoteVolume24h: (float)($d['vwap'] ?? 0) * (float)($d['volume'] ?? 0),
            change24h:      0,
            changePct24h:   0,
            timestamp:      (int)($d['timestamp'] ?? time()) * 1000,
            exchange:       'bitstamp',
        );
    }
    public function orderBook(string $symbol, array $d): OrderBookDTO
    {
        return new OrderBookDTO(
            symbol:    strtoupper($symbol),
            bids:      array_map(fn($b) => [(float)$b[0], (float)$b[1]], $d['bids'] ?? []),
            asks:      array_map(fn($a) => [(float)$a[0], (float)$a[1]], $d['asks'] ?? []),
            timestamp: (int)($d['timestamp'] ?? time()) * 1000,
            exchange:  'bitstamp',
        );
    }
    public function order(string $symbol, array $d): OrderDTO
    {
        $sm = [0=>OrderDTO::STATUS_OPEN,1=>OrderDTO::STATUS_OPEN,2=>OrderDTO::STATUS_FILLED];
        return new OrderDTO(
            orderId:       (string)($d['id'] ?? ''),
            clientOrderId: $d['client_order_id'] ?? '',
            symbol:        strtoupper($symbol),
            side:          isset($d['type']) ? ($d['type'] == 0 ? 'BUY' : 'SELL') : '',
            type:          'LIMIT',
            status:        $sm[$d['status'] ?? 0] ?? OrderDTO::STATUS_OPEN,
            quantity:      (float)($d['amount'] ?? $d['remaining'] ?? 0),
            executedQty:   (float)($d['amount_remaining'] ?? 0) > 0 ? (float)$d['amount'] - (float)$d['amount_remaining'] : (float)($d['amount'] ?? 0),
            price:         (float)($d['price'] ?? $d['limit_price'] ?? 0),
            avgPrice:      (float)($d['avg_price'] ?? 0),
            stopPrice:     0,
            timeInForce:   'GTC',
            fee:           (float)($d['fee'] ?? 0),
            feeAsset:      '',
            createdAt:     isset($d['datetime']) ? strtotime($d['datetime']) * 1000 : time() * 1000,
            updatedAt:     isset($d['datetime']) ? strtotime($d['datetime']) * 1000 : time() * 1000,
            exchange:      'bitstamp',
        );
    }
    public function trade(array $d): TradeDTO
    {
        return new TradeDTO(
            tradeId:   (string)($d['tid'] ?? $d['id'] ?? ''),
            orderId:   (string)($d['order_id'] ?? ''),
            symbol:    '',
            side:      isset($d['type']) ? ($d['type'] == 0 ? 'BUY' : 'SELL') : '',
            price:     (float)($d['price'] ?? 0),
            quantity:  (float)($d['amount'] ?? 0),
            quoteQty:  (float)($d['usd'] ?? $d['eur'] ?? 0),
            fee:       (float)($d['fee'] ?? 0),
            feeAsset:  'USD',
            isMaker:   false,
            timestamp: (int)($d['datetime'] ?? time()) * 1000,
            exchange:  'bitstamp',
        );
    }
    public function balance(string $asset, float $avail, float $reserved): BalanceDTO
    {
        return new BalanceDTO(asset: strtoupper($asset), free: $avail, locked: $reserved, staked: 0, exchange: 'bitstamp');
    }
    public function candle(string $symbol, string $interval, array $d): CandleDTO
    {
        return new CandleDTO(symbol: $symbol, interval: $interval, openTime: (int)($d['timestamp'] ?? 0) * 1000, open: (float)($d['open'] ?? 0), high: (float)($d['high'] ?? 0), low: (float)($d['low'] ?? 0), close: (float)($d['close'] ?? 0), volume: (float)($d['volume'] ?? 0), quoteVolume: 0, trades: 0, closeTime: (int)($d['timestamp'] ?? 0) * 1000 + 59999, exchange: 'bitstamp');
    }
}
