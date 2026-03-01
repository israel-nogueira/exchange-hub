<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Gemini;
class GeminiConfig
{
    const BASE_URL = 'https://api.gemini.com';
    const V1 = '/v1';
    const V2 = '/v2';
    // Public
    const SYMBOLS       = self::V1 . '/symbols';
    const SYMBOL_DETAIL = self::V1 . '/symbols/details/{symbol}';
    const TICKER        = self::V1 . '/pubticker/{symbol}';
    const TICKER_V2     = self::V2 . '/ticker/{symbol}';
    const ORDER_BOOK    = self::V1 . '/book/{symbol}';
    const TRADES        = self::V1 . '/trades/{symbol}';
    const CANDLES       = self::V2 . '/candles/{symbol}/{interval}';
    // Private
    const BALANCES      = self::V1 . '/balances';
    const NEW_ORDER     = self::V1 . '/order/new';
    const CANCEL_ORDER  = self::V1 . '/order/cancel';
    const CANCEL_ALL    = self::V1 . '/order/cancel/all';
    const ORDER_STATUS  = self::V1 . '/order/status';
    const ACTIVE_ORDERS = self::V1 . '/orders';
    const PAST_TRADES   = self::V1 . '/mytrades';
    const DEPOSIT_ADDR  = self::V1 . '/deposit/{currency}/newAddress';
    const TRANSFERS     = self::V1 . '/transfers';
    const WITHDRAW      = self::V1 . '/withdraw/{currency}';
    const INTERVAL_MAP  = ['1m'=>'1m','5m'=>'5m','15m'=>'15m','30m'=>'30m','1h'=>'1hr','6h'=>'6hr','1d'=>'1day'];
}
