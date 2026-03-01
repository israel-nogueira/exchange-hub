<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Gemini;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};
class GeminiNormalizer
{
    public function ticker(string $symbol, array $d): TickerDTO
    {
        // v2 ticker: {symbol, open, high, low, close, changes[], bid, ask}
        return new TickerDTO(
            symbol:         strtoupper($symbol),
            price:          (float)($d['close'] ?? $d['last'] ?? 0),
            bid:            (float)($d['bid'] ?? 0),
            ask:            (float)($d['ask'] ?? 0),
            open24h:        (float)($d['open'] ?? 0),
            high24h:        (float)($d['high'] ?? 0),
            low24h:         (float)($d['low'] ?? 0),
            volume24h:      (float)($d['volume'][strtolower($symbol) . '/base'] ?? $d['volume']['BTC'] ?? 0),
            quoteVolume24h: (float)($d['volume'][strtolower($symbol) . '/quote'] ?? $d['volume']['USD'] ?? 0),
            change24h:      0,
            changePct24h:   0,
            timestamp:      (int)($d['volume']['timestamp'] ?? time() * 1000),
            exchange:       'gemini',
        );
    }
    public function orderBook(string $symbol, array $d): OrderBookDTO
    {
        return new OrderBookDTO(
            symbol:    strtoupper($symbol),
            bids:      array_map(fn($b) => [(float)$b['price'], (float)$b['amount']], $d['bids'] ?? []),
            asks:      array_map(fn($a) => [(float)$a['price'], (float)$a['amount']], $d['asks'] ?? []),
            timestamp: time() * 1000,
            exchange:  'gemini',
        );
    }
    public function order(array $d): OrderDTO
    {
        $sm = ['live'=>OrderDTO::STATUS_OPEN,'accepted'=>OrderDTO::STATUS_OPEN,'pending'=>OrderDTO::STATUS_OPEN,'partially filled'=>OrderDTO::STATUS_PARTIAL,'filled'=>OrderDTO::STATUS_FILLED,'cancelled'=>OrderDTO::STATUS_CANCELLED,'cancel pending'=>OrderDTO::STATUS_CANCELLED,'closed'=>OrderDTO::STATUS_FILLED,'expired'=>OrderDTO::STATUS_EXPIRED,'bounced'=>OrderDTO::STATUS_REJECTED];
        $qty = (float)($d['original_amount'] ?? 0);
        $rem = (float)($d['remaining_amount'] ?? 0);
        return new OrderDTO(
            orderId:       (string)($d['order_id'] ?? ''),
            clientOrderId: $d['client_order_id'] ?? '',
            symbol:        strtoupper($d['symbol'] ?? ''),
            side:          strtoupper($d['side'] ?? ''),
            type:          strtoupper($d['type'] ?? 'LIMIT'),
            status:        $sm[strtolower($d['is_live'] ? 'live' : ($d['is_cancelled'] ? 'cancelled' : 'filled'))] ?? OrderDTO::STATUS_OPEN,
            quantity:      $qty,
            executedQty:   max(0, $qty - $rem),
            price:         (float)($d['price'] ?? 0),
            avgPrice:      (float)($d['avg_execution_price'] ?? 0),
            stopPrice:     0,
            timeInForce:   'GTC',
            fee:           0,
            feeAsset:      '',
            createdAt:     (int)($d['timestampms'] ?? time() * 1000),
            updatedAt:     (int)($d['timestampms'] ?? time() * 1000),
            exchange:      'gemini',
        );
    }
    public function trade(array $d): TradeDTO
    {
        return new TradeDTO(
            tradeId:   (string)($d['tid'] ?? ''),
            orderId:   (string)($d['order_id'] ?? ''),
            symbol:    strtoupper($d['symbol'] ?? ''),
            side:      strtoupper($d['type'] ?? ''),
            price:     (float)($d['price'] ?? 0),
            quantity:  (float)($d['amount'] ?? 0),
            quoteQty:  (float)($d['price'] ?? 0) * (float)($d['amount'] ?? 0),
            fee:       (float)($d['fee_amount'] ?? 0),
            feeAsset:  $d['fee_currency'] ?? '',
            isMaker:   false,
            timestamp: (int)($d['timestampms'] ?? time() * 1000),
            exchange:  'gemini',
        );
    }
    public function balance(array $d): BalanceDTO
    {
        return new BalanceDTO(asset: $d['currency'] ?? '', free: (float)($d['available'] ?? 0), locked: (float)(($d['amount'] ?? 0) - ($d['available'] ?? 0)), staked: 0, exchange: 'gemini');
    }
    public function candle(string $symbol, string $interval, array $d): CandleDTO
    {
        // [timestamp, open, high, low, close, volume]
        return new CandleDTO(symbol: $symbol, interval: $interval, openTime: (int)($d[0] ?? 0), open: (float)($d[1] ?? 0), high: (float)($d[2] ?? 0), low: (float)($d[3] ?? 0), close: (float)($d[4] ?? 0), volume: (float)($d[5] ?? 0), quoteVolume: 0, trades: 0, closeTime: (int)($d[0] ?? 0) + 59999, exchange: 'gemini');
    }
}
