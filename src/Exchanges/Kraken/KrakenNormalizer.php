<?php
namespace Exchanges\Exchanges\Kraken;
use Exchanges\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};

class KrakenNormalizer
{
    public function ticker(string $symbol, array $d): TickerDTO
    {
        // Kraken retorna {"XXBTZUSD": {...}}
        $t = is_array(reset($d)) ? reset($d) : $d;
        return new TickerDTO(
            symbol: $symbol, price: (float)$t['c'][0], bid: (float)$t['b'][0], ask: (float)$t['a'][0],
            open24h: (float)$t['o'], high24h: (float)$t['h'][1], low24h: (float)$t['l'][1],
            volume24h: (float)$t['v'][1], quoteVolume24h: 0,
            change24h: (float)$t['c'][0] - (float)$t['o'],
            changePct24h: $t['o'] > 0 ? round(((float)$t['c'][0] - (float)$t['o']) / (float)$t['o'] * 100, 4) : 0,
            timestamp: time() * 1000, exchange: 'kraken',
        );
    }

    public function orderBook(string $symbol, array $d): OrderBookDTO
    {
        $book = reset($d);
        return new OrderBookDTO(
            symbol: $symbol,
            bids: array_map(fn($b) => [(float)$b[0], (float)$b[1]], $book['bids'] ?? []),
            asks: array_map(fn($a) => [(float)$a[0], (float)$a[1]], $book['asks'] ?? []),
            timestamp: time() * 1000, exchange: 'kraken',
        );
    }

    public function order(string $id, array $d): OrderDTO
    {
        $statusMap = ['pending'=>OrderDTO::STATUS_OPEN,'open'=>OrderDTO::STATUS_OPEN,'closed'=>OrderDTO::STATUS_FILLED,'canceled'=>OrderDTO::STATUS_CANCELLED,'expired'=>OrderDTO::STATUS_EXPIRED];
        $desc = $d['descr'] ?? [];
        return new OrderDTO(
            orderId: $id, clientOrderId: $d['userref'] ?? '', symbol: $desc['pair'] ?? '',
            side: strtoupper($desc['type'] ?? 'BUY'), type: strtoupper($desc['ordertype'] ?? 'LIMIT'),
            status: $statusMap[$d['status'] ?? 'open'] ?? OrderDTO::STATUS_OPEN,
            quantity: (float)($d['vol'] ?? 0), executedQty: (float)($d['vol_exec'] ?? 0),
            price: (float)($desc['price'] ?? 0), avgPrice: (float)($d['price'] ?? 0), stopPrice: (float)($desc['price2'] ?? 0),
            timeInForce: 'GTC', fee: (float)($d['fee'] ?? 0), feeAsset: '',
            createdAt: isset($d['opentm']) ? (int)($d['opentm'] * 1000) : time()*1000,
            updatedAt: isset($d['closetm']) ? (int)($d['closetm'] * 1000) : time()*1000,
            exchange: 'kraken',
        );
    }

    public function balance(string $asset, float $amount): BalanceDTO
    {
        return new BalanceDTO(asset: $asset, free: $amount, locked: 0, staked: 0, exchange: 'kraken');
    }

    public function candle(string $symbol, string $interval, array $d): CandleDTO
    {
        $sec = $this->intervalToSeconds($interval);
        return new CandleDTO(
            symbol: $symbol, interval: $interval, openTime: (int)$d[0]*1000,
            open: (float)$d[1], high: (float)$d[2], low: (float)$d[3], close: (float)$d[4],
            volume: (float)$d[6], quoteVolume: 0, trades: (int)$d[7],
            closeTime: ((int)$d[0] + $sec) * 1000, exchange: 'kraken',
        );
    }

    public function depositAddress(string $asset, array $d): DepositDTO
    {
        return new DepositDTO(asset: $asset, address: $d['address'], memo: $d['tag'] ?? null,
            network: $d['method'] ?? '', depositId: null, amount: null, txId: null,
            status: DepositDTO::STATUS_CONFIRMED, timestamp: null, exchange: 'kraken');
    }

    public function withdraw(array $d): WithdrawDTO
    {
        return new WithdrawDTO(
            withdrawId: $d['refid'] ?? '', asset: '', address: '', memo: null, network: '',
            amount: (float)($d['amount'] ?? 0), fee: (float)($d['fee'] ?? 0),
            netAmount: (float)($d['amount'] ?? 0) - (float)($d['fee'] ?? 0),
            txId: $d['txid'] ?? null,
            status: $this->mapWithdrawStatus($d['status'] ?? ''),
            timestamp: isset($d['time']) ? (int)($d['time'] * 1000) : time()*1000,
            exchange: 'kraken',
        );
    }

    private function mapWithdrawStatus(string $s): string
    {
        return match($s) {
            'Pending'   => WithdrawDTO::STATUS_PENDING,
            'Settled','Refunded' => WithdrawDTO::STATUS_CONFIRMED,
            'Failure'   => WithdrawDTO::STATUS_FAILED,
            default     => WithdrawDTO::STATUS_PROCESSING,
        };
    }

    private function intervalToSeconds(string $i): int
    {
        return match($i) { '1m'=>60,'5m'=>300,'15m'=>900,'30m'=>1800,'1h'=>3600,'4h'=>14400,'1d'=>86400,'1w'=>604800, default=>3600 };
    }
}
