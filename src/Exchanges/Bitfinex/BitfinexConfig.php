<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Bitfinex;
class BitfinexConfig
{
    const BASE_URL  = 'https://api.bitfinex.com';
    const PUB_V2    = '/v2';
    const PRIV_V2   = '/v2/auth';
    // Public
    const SYMBOLS       = self::PUB_V2 . '/conf/pub:list:pair:exchange';
    const TICKER        = self::PUB_V2 . '/ticker/{symbol}';
    const TICKERS       = self::PUB_V2 . '/tickers';
    const ORDER_BOOK    = self::PUB_V2 . '/book/{symbol}/{precision}';
    const TRADES        = self::PUB_V2 . '/trades/{symbol}/hist';
    const CANDLES       = self::PUB_V2 . '/candles/trade:{interval}:{symbol}/hist';
    // Private
    const WALLETS       = self::PRIV_V2 . '/r/wallets';
    const ORDERS        = self::PRIV_V2 . '/r/orders';
    const ORDER_NEW     = self::PRIV_V2 . '/w/order/submit';
    const ORDER_UPDATE  = self::PRIV_V2 . '/w/order/update';
    const ORDER_CANCEL  = self::PRIV_V2 . '/w/order/cancel';
    const ORDER_CANCEL_MULTI = self::PRIV_V2 . '/w/order/cancel/multi';
    const ORDERS_HIST   = self::PRIV_V2 . '/r/orders/{symbol}/hist';
    const MY_TRADES     = self::PRIV_V2 . '/r/trades/{symbol}/hist';
    const DEPOSIT_ADDR  = self::PRIV_V2 . '/w/deposit/address';
    const MOVEMENTS     = self::PRIV_V2 . '/r/movements/{currency}/hist';
    const WITHDRAW      = self::PRIV_V2 . '/w/transfer';
    const INTERVAL_MAP  = ['1m'=>'1m','5m'=>'5m','15m'=>'15m','30m'=>'30m','1h'=>'1h','3h'=>'3h','6h'=>'6h','12h'=>'12h','1d'=>'1D','1w'=>'7D'];
}
