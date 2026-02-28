<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Gemini;
use IsraelNogueira\ExchangeHub\DTOs\{TickerDTO,OrderBookDTO,OrderDTO,TradeDTO,BalanceDTO,CandleDTO,DepositDTO,WithdrawDTO,ExchangeInfoDTO};

class GeminiNormalizer
{
    public function ticker(array $d): TickerDTO
    {
        return new TickerDTO(
            symbol:         $d['symbol'] ?? $d['currency_pair'] ?? '',
            price:          (float)($d['last'] ?? $d['close'] ?? 0),
            bid:            (float)($d['bid'] ?? $d['highest_bid'] ?? 0),
            ask:            (float)($d['ask'] ?? $d['lowest_ask'] ?? 0),
            open24h:        0,
            high24h:        (float)($d['high'] ?? $d['high_24h'] ?? 0),
            low24h:         (float)($d['low'] ?? $d['low_24h'] ?? 0),
            volume24h:      (float)($d['base_volume'] ?? $d['volume'] ?? 0),
            quoteVolume24h: (float)($d['quote_volume'] ?? 0),
            change24h:      (float)($d['change' ] ?? 0),
            changePct24h:   (float)($d['change_percentage'] ?? 0),
            timestamp:      time() * 1000,
            exchange:       'gemini',
        );
    }

    public function balance(string $asset, array $d): BalanceDTO
    {
        return new BalanceDTO(
            asset:    $asset,
            free:     (float)($d['available'] ?? $d['free'] ?? $d['balance'] ?? 0),
            locked:   (float)($d['locked'] ?? $d['freeze'] ?? 0),
            staked:   0,
            exchange: 'gemini',
        );
    }
}
