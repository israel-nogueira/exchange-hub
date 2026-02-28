<?php

namespace Exchanges\Exchanges\Fake;

use Exchanges\DTOs\{TickerDTO, OrderBookDTO, OrderDTO, TradeDTO, BalanceDTO, CandleDTO, DepositDTO, WithdrawDTO, ExchangeInfoDTO};

class FakeNormalizer
{
    public function ticker(array $data): TickerDTO
    {
        return new TickerDTO(
            symbol:         $data['symbol'],
            price:          (float) $data['price'],
            bid:            (float) $data['bid'],
            ask:            (float) $data['ask'],
            open24h:        (float) $data['open_24h'],
            high24h:        (float) $data['high_24h'],
            low24h:         (float) $data['low_24h'],
            volume24h:      (float) $data['volume_24h'],
            quoteVolume24h: (float) ($data['quote_volume_24h'] ?? 0),
            change24h:      (float) $data['change_24h'],
            changePct24h:   (float) $data['change_pct_24h'],
            timestamp:      (int)   $data['timestamp'],
            exchange:       'fake',
        );
    }

    public function orderBook(array $data): OrderBookDTO
    {
        return new OrderBookDTO(
            symbol:    $data['symbol'],
            bids:      $data['bids'],
            asks:      $data['asks'],
            timestamp: (int) $data['timestamp'],
            exchange:  'fake',
        );
    }

    public function order(array $data): OrderDTO
    {
        return new OrderDTO(
            orderId:       $data['id'],
            clientOrderId: $data['client_order_id'] ?? '',
            symbol:        $data['symbol'],
            side:          $data['side'],
            type:          $data['type'],
            status:        $data['status'],
            quantity:      (float) $data['quantity'],
            executedQty:   (float) ($data['executed_qty'] ?? 0),
            price:         (float) ($data['price'] ?? 0),
            avgPrice:      (float) ($data['avg_price'] ?? 0),
            stopPrice:     (float) ($data['stop_price'] ?? 0),
            timeInForce:   $data['time_in_force'] ?? 'GTC',
            fee:           (float) ($data['fee'] ?? 0),
            feeAsset:      $data['fee_asset'] ?? 'USDT',
            createdAt:     (int) $data['created_at'],
            updatedAt:     (int) ($data['updated_at'] ?? $data['created_at']),
            exchange:      'fake',
        );
    }

    public function trade(array $data): TradeDTO
    {
        return new TradeDTO(
            tradeId:   $data['id'],
            orderId:   $data['order_id'] ?? '',
            symbol:    $data['symbol'],
            side:      $data['side'],
            price:     (float) $data['price'],
            quantity:  (float) $data['quantity'],
            quoteQty:  (float) $data['quote_qty'],
            fee:       (float) $data['fee'],
            feeAsset:  $data['fee_asset'],
            isMaker:   (bool)  $data['is_maker'],
            timestamp: (int)   $data['timestamp'],
            exchange:  'fake',
        );
    }

    public function balance(string $asset, array $data): BalanceDTO
    {
        return new BalanceDTO(
            asset:    $asset,
            free:     (float) ($data['free']   ?? 0),
            locked:   (float) ($data['locked'] ?? 0),
            staked:   (float) ($data['staked'] ?? 0),
            exchange: 'fake',
        );
    }

    public function candle(string $symbol, string $interval, array $data): CandleDTO
    {
        return new CandleDTO(
            symbol:      $symbol,
            interval:    $interval,
            openTime:    (int)   $data['open_time'],
            open:        (float) $data['open'],
            high:        (float) $data['high'],
            low:         (float) $data['low'],
            close:       (float) $data['close'],
            volume:      (float) $data['volume'],
            quoteVolume: (float) ($data['quote_volume'] ?? 0),
            trades:      (int)   ($data['trades'] ?? 0),
            closeTime:   (int)   $data['close_time'],
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
            depositId: $history['id']     ?? null,
            amount:    $history['amount'] ?? null,
            txId:      $history['tx_id']  ?? null,
            status:    $history['status'] ?? DepositDTO::STATUS_CONFIRMED,
            timestamp: $history['timestamp'] ?? null,
            exchange:  'fake',
        );
    }

    public function withdraw(array $data): WithdrawDTO
    {
        return new WithdrawDTO(
            withdrawId: $data['id'],
            asset:      $data['asset'],
            address:    $data['address'],
            memo:       $data['memo'] ?? null,
            network:    $data['network'],
            amount:     (float) $data['amount'],
            fee:        (float) $data['fee'],
            netAmount:  (float) $data['net_amount'],
            txId:       $data['tx_id'] ?? null,
            status:     $data['status'],
            timestamp:  (int) $data['timestamp'],
            exchange:   'fake',
        );
    }
}
