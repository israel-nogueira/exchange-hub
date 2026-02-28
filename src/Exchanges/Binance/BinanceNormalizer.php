<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Binance;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};

class BinanceNormalizer
{
    public function ticker(array $d): TickerDTO
    {
        return new TickerDTO(
            symbol:         $d['symbol'],
            price:          (float)($d['lastPrice'] ?? $d['price'] ?? 0),
            bid:            (float)($d['bidPrice'] ?? 0),
            ask:            (float)($d['askPrice'] ?? 0),
            open24h:        (float)($d['openPrice'] ?? 0),
            high24h:        (float)($d['highPrice'] ?? 0),
            low24h:         (float)($d['lowPrice'] ?? 0),
            volume24h:      (float)($d['volume'] ?? 0),
            quoteVolume24h: (float)($d['quoteVolume'] ?? 0),
            change24h:      (float)($d['priceChange'] ?? 0),
            changePct24h:   (float)($d['priceChangePercent'] ?? 0),
            timestamp:      (int)($d['closeTime'] ?? time() * 1000),
            exchange:       'binance',
        );
    }

    public function orderBook(array $d, string $symbol): OrderBookDTO
    {
        return new OrderBookDTO(
            symbol:    $symbol,
            bids:      array_map(fn($b) => [(float)$b[0], (float)$b[1]], $d['bids']),
            asks:      array_map(fn($a) => [(float)$a[0], (float)$a[1]], $d['asks']),
            timestamp: time() * 1000,
            exchange:  'binance',
        );
    }

    public function order(array $d): OrderDTO
    {
        $statusMap = [
            'NEW'              => OrderDTO::STATUS_OPEN,
            'PARTIALLY_FILLED' => OrderDTO::STATUS_PARTIAL,
            'FILLED'           => OrderDTO::STATUS_FILLED,
            'CANCELED'         => OrderDTO::STATUS_CANCELLED,
            'REJECTED'         => OrderDTO::STATUS_REJECTED,
            'EXPIRED'          => OrderDTO::STATUS_EXPIRED,
        ];
        $execQty  = (float)($d['executedQty'] ?? 0);
        $cumQuote = (float)($d['cummulativeQuoteQty'] ?? 0);
        return new OrderDTO(
            orderId:       (string)($d['orderId'] ?? ''),
            clientOrderId: $d['clientOrderId'] ?? '',
            symbol:        $d['symbol'],
            side:          $d['side'],
            type:          $d['type'],
            status:        $statusMap[$d['status']] ?? $d['status'],
            quantity:      (float)($d['origQty'] ?? 0),
            executedQty:   $execQty,
            price:         (float)($d['price'] ?? 0),
            avgPrice:      ($execQty > 0 && $cumQuote > 0) ? $cumQuote / $execQty : (float)($d['avgPrice'] ?? 0),
            stopPrice:     (float)($d['stopPrice'] ?? 0),
            timeInForce:   $d['timeInForce'] ?? 'GTC',
            fee:           0,
            feeAsset:      '',
            createdAt:     (int)($d['time'] ?? $d['transactTime'] ?? time() * 1000),
            updatedAt:     (int)($d['updateTime'] ?? time() * 1000),
            exchange:      'binance',
        );
    }

    public function trade(array $d, string $symbol = ''): TradeDTO
    {
        return new TradeDTO(
            tradeId:   (string)$d['id'],
            orderId:   (string)($d['orderId'] ?? ''),
            symbol:    $d['symbol'] ?? $symbol,
            side:      isset($d['isBuyer']) ? ($d['isBuyer'] ? 'BUY' : 'SELL') : ($d['side'] ?? ''),
            price:     (float)$d['price'],
            quantity:  (float)($d['qty'] ?? $d['quantity'] ?? 0),
            quoteQty:  (float)($d['quoteQty'] ?? 0),
            fee:       (float)($d['commission'] ?? 0),
            feeAsset:  $d['commissionAsset'] ?? '',
            isMaker:   (bool)($d['isMaker'] ?? false),
            timestamp: (int)($d['time'] ?? time() * 1000),
            exchange:  'binance',
        );
    }

    public function balance(string $asset, array $d): BalanceDTO
    {
        return new BalanceDTO(
            asset:    $asset,
            free:     (float)$d['free'],
            locked:   (float)$d['locked'],
            staked:   0,
            exchange: 'binance',
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
            quoteVolume: (float)$d[7],
            trades:      (int)$d[8],
            closeTime:   (int)$d[6],
            exchange:    'binance',
        );
    }

    public function depositAddress(array $d): DepositDTO
    {
        return new DepositDTO(
            asset:     $d['coin'],
            address:   $d['address'],
            memo:      $d['tag'] ?: null,
            network:   $d['network'] ?? '',
            depositId: null,
            amount:    null,
            txId:      null,
            status:    DepositDTO::STATUS_CONFIRMED,
            timestamp: null,
            exchange:  'binance',
        );
    }

    public function deposit(array $d): DepositDTO
    {
        $statusMap = [0 => DepositDTO::STATUS_PENDING, 1 => DepositDTO::STATUS_CONFIRMED, 6 => DepositDTO::STATUS_CREDITED];
        return new DepositDTO(
            asset:     $d['coin'],
            address:   $d['address'],
            memo:      $d['addressTag'] ?: null,
            network:   $d['network'] ?? '',
            depositId: $d['id'] ?? null,
            amount:    (float)($d['amount'] ?? 0),
            txId:      $d['txId'] ?? null,
            status:    $statusMap[$d['status'] ?? 1] ?? DepositDTO::STATUS_PENDING,
            timestamp: isset($d['insertTime']) ? (int)$d['insertTime'] : null,
            exchange:  'binance',
        );
    }

    public function withdraw(array $d): WithdrawDTO
    {
        $statusMap = [0=>WithdrawDTO::STATUS_PENDING,1=>WithdrawDTO::STATUS_CANCELLED,2=>WithdrawDTO::STATUS_PENDING,3=>WithdrawDTO::STATUS_PENDING,4=>WithdrawDTO::STATUS_PROCESSING,5=>WithdrawDTO::STATUS_FAILED,6=>WithdrawDTO::STATUS_CONFIRMED];
        $amount = (float)($d['amount'] ?? 0);
        $fee    = (float)($d['transactionFee'] ?? 0);
        return new WithdrawDTO(
            withdrawId: $d['id'],
            asset:      $d['coin'],
            address:    $d['address'],
            memo:       $d['addressTag'] ?: null,
            network:    $d['network'] ?? '',
            amount:     $amount,
            fee:        $fee,
            netAmount:  $amount - $fee,
            txId:       $d['txId'] ?? null,
            status:     $statusMap[$d['status'] ?? 0] ?? WithdrawDTO::STATUS_PENDING,
            timestamp:  isset($d['applyTime']) ? strtotime($d['applyTime']) * 1000 : time() * 1000,
            exchange:   'binance',
        );
    }

    public function exchangeInfo(array $d): ExchangeInfoDTO
    {
        $symbols = array_map(
            fn($s) => $s['symbol'],
            array_filter($d['symbols'] ?? [], fn($s) => $s['status'] === 'TRADING')
        );
        return new ExchangeInfoDTO(
            exchangeName: 'Binance',
            status:       'ONLINE',
            symbols:      array_values($symbols),
            makerFee:     0.001,
            takerFee:     0.001,
            rateLimits:   $d['rateLimits'] ?? [],
            networks:     [],
            timestamp:    (int)($d['serverTime'] ?? time() * 1000),
        );
    }
}
