<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Bitfinex;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};
class BitfinexNormalizer
{
    /** Bitfinex tickers: [symbol, bid, bidSize, ask, askSize, dailyChange, dailyChangePct, lastPrice, volume, high, low] */
    public function ticker(string $symbol, array $d): TickerDTO
    {
        return new TickerDTO(
            symbol:         $symbol,
            price:          (float)($d[7] ?? 0),
            bid:            (float)($d[1] ?? 0),
            ask:            (float)($d[3] ?? 0),
            open24h:        (float)(($d[7] ?? 0) - ($d[5] ?? 0)),
            high24h:        (float)($d[9] ?? 0),
            low24h:         (float)($d[10] ?? 0),
            volume24h:      (float)($d[8] ?? 0),
            quoteVolume24h: 0,
            change24h:      (float)($d[5] ?? 0),
            changePct24h:   (float)(($d[6] ?? 0) * 100),
            timestamp:      time() * 1000,
            exchange:       'bitfinex',
        );
    }
    /** Bitfinex orderbook entry: [price, count, amount] */
    public function orderBook(string $symbol, array $entries): OrderBookDTO
    {
        $bids = $asks = [];
        foreach ($entries as $e) {
            if (($e[2] ?? 0) > 0) $bids[] = [(float)$e[0], abs((float)$e[2])];
            else                   $asks[] = [(float)$e[0], abs((float)$e[2])];
        }
        return new OrderBookDTO(symbol: $symbol, bids: $bids, asks: $asks, timestamp: time() * 1000, exchange: 'bitfinex');
    }
    /** Bitfinex order: [id, gid, cid, symbol, mtsCreate, mtsUpdate, amount, amountOrig, type, typePrev, flags, status, _, _, price, priceAvg, ...] */
    public function order(string $symbol, array $d): OrderDTO
    {
        $statusRaw = strtolower($d[13] ?? $d[11] ?? '');
        $sm = ['active'=>OrderDTO::STATUS_OPEN,'executed'=>OrderDTO::STATUS_FILLED,'partially filled'=>OrderDTO::STATUS_PARTIAL,'canceled'=>OrderDTO::STATUS_CANCELLED,'insufficient margin'=>OrderDTO::STATUS_REJECTED];
        $status = OrderDTO::STATUS_OPEN;
        foreach ($sm as $k => $v) { if (str_contains($statusRaw, $k)) { $status = $v; break; } }
        $origAmt = abs((float)($d[7] ?? 0));
        $curAmt  = abs((float)($d[6] ?? 0));
        return new OrderDTO(
            orderId:       (string)($d[0] ?? ''),
            clientOrderId: (string)($d[2] ?? ''),
            symbol:        $symbol,
            side:          ($d[6] ?? 0) >= 0 ? 'BUY' : 'SELL',
            type:          strtoupper($d[8] ?? 'LIMIT'),
            status:        $status,
            quantity:      $origAmt,
            executedQty:   max(0, $origAmt - $curAmt),
            price:         (float)($d[16] ?? $d[14] ?? 0),
            avgPrice:      (float)($d[17] ?? $d[15] ?? 0),
            stopPrice:     0,
            timeInForce:   'GTC',
            fee:           0,
            feeAsset:      '',
            createdAt:     (int)($d[4] ?? time() * 1000),
            updatedAt:     (int)($d[5] ?? time() * 1000),
            exchange:      'bitfinex',
        );
    }
    /** Bitfinex trade: [id, mts, amountExec, priceExec, ..., fee, feeCurrency] */
    public function trade(string $symbol, array $d): TradeDTO
    {
        return new TradeDTO(
            tradeId:   (string)($d[0] ?? ''),
            orderId:   (string)($d[3] ?? ''),
            symbol:    $symbol,
            side:      (($d[4] ?? 0) >= 0) ? 'BUY' : 'SELL',
            price:     (float)($d[5] ?? 0),
            quantity:  abs((float)($d[4] ?? 0)),
            quoteQty:  abs((float)($d[4] ?? 0)) * (float)($d[5] ?? 0),
            fee:       abs((float)($d[9] ?? 0)),
            feeAsset:  $d[10] ?? '',
            isMaker:   ($d[8] ?? 1) > 0,
            timestamp: (int)($d[2] ?? time() * 1000),
            exchange:  'bitfinex',
        );
    }
    /** Bitfinex wallet: [walletType, currency, balance, unsettledInterest, availableBalance] */
    public function balance(array $d): BalanceDTO
    {
        $avail = (float)($d[4] ?? $d[2] ?? 0);
        $total = (float)($d[2] ?? 0);
        return new BalanceDTO(asset: strtoupper($d[1] ?? ''), free: $avail, locked: max(0, $total - $avail), staked: 0, exchange: 'bitfinex');
    }
    public function candle(string $symbol, string $interval, array $d): CandleDTO
    {
        // [mts, open, close, high, low, volume]
        return new CandleDTO(symbol: $symbol, interval: $interval, openTime: (int)($d[0] ?? 0), open: (float)($d[1] ?? 0), high: (float)($d[3] ?? 0), low: (float)($d[4] ?? 0), close: (float)($d[2] ?? 0), volume: (float)($d[5] ?? 0), quoteVolume: 0, trades: 0, closeTime: (int)($d[0] ?? 0) + 59999, exchange: 'bitfinex');
    }
}
