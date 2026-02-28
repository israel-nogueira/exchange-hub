<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Coinbase;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};

class CoinbaseNormalizer
{
    public function ticker(array $d): TickerDTO
    {
        $last = (float)($d['price'] ?? $d['close'] ?? 0);
        return new TickerDTO(
            symbol:         $d['product_id'] ?? $d['symbol'] ?? '',
            price:          $last,
            bid:            (float)($d['best_bid'] ?? 0),
            ask:            (float)($d['best_ask'] ?? 0),
            open24h:        (float)($d['open_24h'] ?? 0),
            high24h:        (float)($d['high_24h'] ?? 0),
            low24h:         (float)($d['low_24h'] ?? 0),
            volume24h:      (float)($d['volume_24h'] ?? 0),
            quoteVolume24h: 0,
            change24h:      (float)($d['price_percentage_change_24h'] ?? 0) * $last / 100,
            changePct24h:   (float)($d['price_percentage_change_24h'] ?? 0),
            timestamp:      time() * 1000,
            exchange:       'coinbase',
        );
    }

    public function orderBook(string $symbol, array $d): OrderBookDTO
    {
        return new OrderBookDTO(
            symbol:    $symbol,
            bids:      array_map(fn($b) => [(float)$b['price'], (float)$b['size']], $d['bids'] ?? []),
            asks:      array_map(fn($a) => [(float)$a['price'], (float)$a['size']], $d['asks'] ?? []),
            timestamp: time() * 1000,
            exchange:  'coinbase',
        );
    }

    public function order(array $r): OrderDTO
    {
        $d  = $r['order'] ?? $r['success_response'] ?? $r;
        $sm = ['OPEN'=>OrderDTO::STATUS_OPEN,'FILLED'=>OrderDTO::STATUS_FILLED,'CANCELLED'=>OrderDTO::STATUS_CANCELLED,'EXPIRED'=>OrderDTO::STATUS_EXPIRED,'FAILED'=>OrderDTO::STATUS_REJECTED];
        $oc = $d['order_configuration'] ?? [];
        $cfg= $oc['limit_limit_gtc'] ?? $oc['market_market_ioc'] ?? [];
        return new OrderDTO(
            orderId:       $d['order_id'] ?? '',
            clientOrderId: $d['client_order_id'] ?? '',
            symbol:        $d['product_id'] ?? '',
            side:          $d['side'] ?? '',
            type:          isset($oc['limit_limit_gtc']) ? 'LIMIT' : 'MARKET',
            status:        $sm[$d['status'] ?? 'OPEN'] ?? OrderDTO::STATUS_OPEN,
            quantity:      (float)($cfg['base_size'] ?? 0),
            executedQty:   (float)($d['filled_size'] ?? 0),
            price:         (float)($cfg['limit_price'] ?? 0),
            avgPrice:      (float)($d['average_filled_price'] ?? 0),
            stopPrice:     0,
            timeInForce:   'GTC',
            fee:           (float)($d['total_fees'] ?? 0),
            feeAsset:      '',
            createdAt:     isset($d['created_time']) ? strtotime($d['created_time']) * 1000 : time() * 1000,
            updatedAt:     time() * 1000,
            exchange:      'coinbase',
        );
    }

    public function balance(string $asset, array $d): BalanceDTO
    {
        $available = $d['available_balance']['value'] ?? $d['available'] ?? 0;
        $hold      = $d['hold']['value'] ?? $d['hold'] ?? 0;
        return new BalanceDTO(
            asset:    $asset,
            free:     (float)$available,
            locked:   (float)$hold,
            staked:   0,
            exchange: 'coinbase',
        );
    }

    public function candle(string $symbol, string $interval, array $d): CandleDTO
    {
        return new CandleDTO(
            symbol:      $symbol,
            interval:    $interval,
            openTime:    (int)($d['start'] ?? $d[0] ?? 0) * 1000,
            open:        (float)($d['open'] ?? $d[3] ?? 0),
            high:        (float)($d['high'] ?? $d[1] ?? 0),
            low:         (float)($d['low'] ?? $d[2] ?? 0),
            close:       (float)($d['close'] ?? $d[4] ?? 0),
            volume:      (float)($d['volume'] ?? $d[5] ?? 0),
            quoteVolume: 0,
            trades:      0,
            closeTime:   (int)($d['start'] ?? $d[0] ?? 0) * 1000 + 3600000,
            exchange:    'coinbase',
        );
    }
}
