<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Gateio;

use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};

class GateioNormalizer
{
    public function ticker(array $d): TickerDTO
    {
        return new TickerDTO(
            symbol:         $d['currency_pair'] ?? '',
            price:          (float)($d['last'] ?? 0),
            bid:            (float)($d['highest_bid'] ?? 0),
            ask:            (float)($d['lowest_ask'] ?? 0),
            open24h:        (float)($d['open_24h'] ?? 0),
            high24h:        (float)($d['high_24h'] ?? 0),
            low24h:         (float)($d['low_24h'] ?? 0),
            volume24h:      (float)($d['base_volume'] ?? 0),
            quoteVolume24h: (float)($d['quote_volume'] ?? 0),
            change24h:      0,
            changePct24h:   (float)($d['change_percentage'] ?? 0),
            timestamp:      time() * 1000,
            exchange:       'gateio',
        );
    }

    public function orderBook(array $d, string $symbol): OrderBookDTO
    {
        return new OrderBookDTO(
            symbol:    $symbol,
            bids:      array_map(fn($b) => [(float)$b[0], (float)$b[1]], $d['bids'] ?? []),
            asks:      array_map(fn($a) => [(float)$a[0], (float)$a[1]], $d['asks'] ?? []),
            timestamp: (int)($d['current'] ?? time()) * 1000,
            exchange:  'gateio',
        );
    }

    public function order(array $d): OrderDTO
    {
        $statusMap = [
            'open'      => OrderDTO::STATUS_OPEN,
            'closed'    => OrderDTO::STATUS_FILLED,
            'cancelled' => OrderDTO::STATUS_CANCELLED,
        ];
        $qty     = (float)($d['amount'] ?? 0);
        $filled  = (float)($d['filled_amount'] ?? 0);
        $status  = $statusMap[$d['status'] ?? ''] ?? OrderDTO::STATUS_OPEN;
        if ($status === OrderDTO::STATUS_FILLED && $filled < $qty) {
            $status = OrderDTO::STATUS_PARTIAL;
        }
        return new OrderDTO(
            orderId:       (string)($d['id'] ?? ''),
            clientOrderId: $d['text'] ?? '',
            symbol:        $d['currency_pair'] ?? '',
            side:          strtoupper($d['side'] ?? ''),
            type:          strtoupper($d['type'] ?? 'LIMIT'),
            status:        $status,
            quantity:      $qty,
            executedQty:   $filled,
            price:         (float)($d['price'] ?? 0),
            avgPrice:      (float)($d['avg_deal_price'] ?? 0),
            stopPrice:     0,
            timeInForce:   strtoupper($d['time_in_force'] ?? 'GTC'),
            fee:           (float)($d['fee'] ?? 0),
            feeAsset:      $d['fee_currency'] ?? '',
            createdAt:     (int)(((float)($d['create_time_ms'] ?? $d['create_time'] ?? 0)) * (str_contains((string)($d['create_time_ms'] ?? ''), '.') ? 1000 : 1)),
            updatedAt:     (int)(((float)($d['update_time_ms'] ?? $d['update_time'] ?? 0)) * (str_contains((string)($d['update_time_ms'] ?? ''), '.') ? 1000 : 1)),
            exchange:      'gateio',
        );
    }

    public function trade(array $d): TradeDTO
    {
        return new TradeDTO(
            tradeId:   (string)($d['id'] ?? ''),
            orderId:   (string)($d['order_id'] ?? ''),
            symbol:    $d['currency_pair'] ?? '',
            side:      strtoupper($d['side'] ?? ''),
            price:     (float)($d['price'] ?? 0),
            quantity:  (float)($d['amount'] ?? 0),
            quoteQty:  (float)($d['price'] ?? 0) * (float)($d['amount'] ?? 0),
            fee:       (float)($d['fee'] ?? 0),
            feeAsset:  $d['fee_currency'] ?? '',
            isMaker:   ($d['role'] ?? '') === 'maker',
            timestamp: (int)(((float)($d['create_time_ms'] ?? $d['create_time'] ?? 0)) * 1),
            exchange:  'gateio',
        );
    }

    public function balance(string $asset, array $d): BalanceDTO
    {
        return new BalanceDTO(
            asset:    strtoupper($asset),
            free:     (float)($d['available'] ?? 0),
            locked:   (float)($d['locked'] ?? 0),
            staked:   0,
            exchange: 'gateio',
        );
    }

    public function candle(string $symbol, string $interval, array $d): CandleDTO
    {
        // Gate.io: [timestamp, volume, close, high, low, open, ...]
        return new CandleDTO(
            symbol:      $symbol,
            interval:    $interval,
            openTime:    (int)($d[0] ?? 0) * 1000,
            open:        (float)($d[5] ?? 0),
            high:        (float)($d[3] ?? 0),
            low:         (float)($d[4] ?? 0),
            close:       (float)($d[2] ?? 0),
            volume:      (float)($d[1] ?? 0),
            quoteVolume: (float)($d[6] ?? 0),
            trades:      0,
            closeTime:   (int)($d[0] ?? 0) * 1000 + 59999,
            exchange:    'gateio',
        );
    }

    public function depositAddress(array $d): DepositDTO
    {
        return new DepositDTO(
            asset:     $d['currency'] ?? '',
            address:   $d['address'] ?? '',
            memo:      $d['payment_id'] ?: null,
            network:   $d['chain'] ?? '',
            depositId: null,
            amount:    null,
            txId:      null,
            status:    DepositDTO::STATUS_CONFIRMED,
            timestamp: null,
            exchange:  'gateio',
        );
    }

    public function deposit(array $d): DepositDTO
    {
        $statusMap = ['done'=>DepositDTO::STATUS_CONFIRMED,'cancel'=>DepositDTO::STATUS_FAILED,'request'=>DepositDTO::STATUS_PENDING,'manual'=>DepositDTO::STATUS_PENDING,'bcode'=>DepositDTO::STATUS_PENDING,'extpend'=>DepositDTO::STATUS_PENDING,'fail'=>DepositDTO::STATUS_FAILED,'invalid'=>DepositDTO::STATUS_FAILED,'verify'=>DepositDTO::STATUS_PENDING,'proces'=>DepositDTO::STATUS_PROCESSING,'pend'=>DepositDTO::STATUS_PENDING,'dmove'=>DepositDTO::STATUS_PENDING,'credit'=>DepositDTO::STATUS_CREDITED];
        return new DepositDTO(
            asset:     $d['currency'] ?? '',
            address:   $d['address'] ?? '',
            memo:      $d['memo'] ?: null,
            network:   $d['chain'] ?? '',
            depositId: $d['id'] ?? null,
            amount:    (float)($d['amount'] ?? 0),
            txId:      $d['txid'] ?? null,
            status:    $statusMap[$d['status'] ?? ''] ?? DepositDTO::STATUS_PENDING,
            timestamp: isset($d['timestamp']) ? (int)$d['timestamp'] * 1000 : null,
            exchange:  'gateio',
        );
    }

    public function withdraw(array $d): WithdrawDTO
    {
        $statusMap = ['done'=>WithdrawDTO::STATUS_CONFIRMED,'cancel'=>WithdrawDTO::STATUS_CANCELLED,'request'=>WithdrawDTO::STATUS_PENDING,'manual'=>WithdrawDTO::STATUS_PENDING,'bcode'=>WithdrawDTO::STATUS_PENDING,'extpend'=>WithdrawDTO::STATUS_PENDING,'fail'=>WithdrawDTO::STATUS_FAILED,'invalid'=>WithdrawDTO::STATUS_FAILED,'verify'=>WithdrawDTO::STATUS_PENDING,'proces'=>WithdrawDTO::STATUS_PROCESSING,'pend'=>WithdrawDTO::STATUS_PENDING,'dmove'=>WithdrawDTO::STATUS_PENDING];
        $amount = (float)($d['amount'] ?? 0);
        $fee    = (float)($d['fee'] ?? 0);
        return new WithdrawDTO(
            withdrawId: (string)($d['id'] ?? ''),
            asset:      $d['currency'] ?? '',
            address:    $d['address'] ?? '',
            memo:       $d['memo'] ?: null,
            network:    $d['chain'] ?? '',
            amount:     $amount,
            fee:        $fee,
            netAmount:  $amount - $fee,
            txId:       $d['txid'] ?? null,
            status:     $statusMap[$d['status'] ?? ''] ?? WithdrawDTO::STATUS_PENDING,
            timestamp:  isset($d['timestamp']) ? (int)$d['timestamp'] * 1000 : time() * 1000,
            exchange:   'gateio',
        );
    }
}
