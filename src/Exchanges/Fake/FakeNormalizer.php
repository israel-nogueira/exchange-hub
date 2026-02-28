<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Fake;

use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};

class FakeNormalizer
{
    public function ticker(array $d): TickerDTO
    {
        return new TickerDTO(
            symbol:         $d['symbol'],
            price:          (float)$d['price'],
            bid:            (float)$d['bid'],
            ask:            (float)$d['ask'],
            open24h:        (float)$d['open_24h'],
            high24h:        (float)$d['high_24h'],
            low24h:         (float)$d['low_24h'],
            volume24h:      (float)$d['volume_24h'],
            quoteVolume24h: (float)($d['quote_volume_24h'] ?? 0),
            change24h:      (float)$d['change_24h'],
            changePct24h:   (float)$d['change_pct_24h'],
            timestamp:      (int)$d['timestamp'],
            exchange:       'fake',
        );
    }

    public function orderBook(array $d): OrderBookDTO
    {
        return new OrderBookDTO(
            symbol:    $d['symbol'],
            bids:      $d['bids'],
            asks:      $d['asks'],
            timestamp: (int)$d['timestamp'],
            exchange:  'fake',
        );
    }

    public function order(array $d): OrderDTO
    {
        return new OrderDTO(
            orderId:       $d['id'],
            clientOrderId: $d['client_order_id'] ?? '',
            symbol:        $d['symbol'],
            side:          $d['side'],
            type:          $d['type'],
            status:        $d['status'],
            quantity:      (float)$d['quantity'],
            executedQty:   (float)($d['executed_qty'] ?? 0),
            price:         (float)($d['price'] ?? 0),
            avgPrice:      (float)($d['avg_price'] ?? 0),
            stopPrice:     (float)($d['stop_price'] ?? 0),
            timeInForce:   $d['time_in_force'] ?? 'GTC',
            fee:           (float)($d['fee'] ?? 0),
            feeAsset:      $d['fee_asset'] ?? 'USDT',
            createdAt:     (int)$d['created_at'],
            updatedAt:     (int)($d['updated_at'] ?? $d['created_at']),
            exchange:      'fake',
        );
    }

    public function trade(array $d): TradeDTO
    {
        return new TradeDTO(
            tradeId:   $d['id'],
            orderId:   $d['order_id'] ?? '',
            symbol:    $d['symbol'],
            side:      $d['side'],
            price:     (float)$d['price'],
            quantity:  (float)$d['quantity'],
            quoteQty:  (float)$d['quote_qty'],
            fee:       (float)$d['fee'],
            feeAsset:  $d['fee_asset'],
            isMaker:   (bool)$d['is_maker'],
            timestamp: (int)$d['timestamp'],
            exchange:  'fake',
        );
    }

    public function balance(string $asset, array $d): BalanceDTO
    {
        return new BalanceDTO(
            asset:    $asset,
            free:     (float)($d['free']   ?? 0),
            locked:   (float)($d['locked'] ?? 0),
            staked:   (float)($d['staked'] ?? 0),
            exchange: 'fake',
        );
    }

    public function candle(string $symbol, string $interval, array $d): CandleDTO
    {
        return new CandleDTO(
            symbol:      $symbol,
            interval:    $interval,
            openTime:    (int)$d['open_time'],
            open:        (float)$d['open'],
            high:        (float)$d['high'],
            low:         (float)$d['low'],
            close:       (float)$d['close'],
            volume:      (float)$d['volume'],
            quoteVolume: (float)($d['quote_volume'] ?? 0),
            trades:      (int)($d['trades'] ?? 0),
            closeTime:   (int)$d['close_time'],
            exchange:    'fake',
        );
    }

    public function deposit(string $asset, string $address, string $network, ?array $history = null): DepositDTO
    {
        return new DepositDTO(
            asset:     $asset,
            address:   $address,
            memo:      null,
            network:   $network,
            depositId: $history['id']        ?? null,
            amount:    $history['amount']    ?? null,
            txId:      $history['tx_id']     ?? null,
            status:    $history['status']    ?? DepositDTO::STATUS_CONFIRMED,
            timestamp: $history['timestamp'] ?? null,
            exchange:  'fake',
        );
    }

    public function withdraw(array $d): WithdrawDTO
    {
        return new WithdrawDTO(
            withdrawId: $d['id'],
            asset:      $d['asset'],
            address:    $d['address'],
            memo:       $d['memo'] ?? null,
            network:    $d['network'],
            amount:     (float)$d['amount'],
            fee:        (float)$d['fee'],
            netAmount:  (float)$d['net_amount'],
            txId:       $d['tx_id'] ?? null,
            status:     $d['status'],
            timestamp:  (int)$d['timestamp'],
            exchange:   'fake',
        );
    }
}
