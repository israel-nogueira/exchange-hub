<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Mexc;

class MexcConfig
{
    const BASE_URL  = 'https://api.mexc.com';
    const API_V3    = '/api/v3';

    // Market
    const PING          = self::API_V3 . '/ping';
    const TIME          = self::API_V3 . '/time';
    const EXCHANGE_INFO = self::API_V3 . '/exchangeInfo';
    const TICKER_24H    = self::API_V3 . '/ticker/24hr';
    const TICKER_PRICE  = self::API_V3 . '/ticker/price';
    const DEPTH         = self::API_V3 . '/depth';
    const TRADES        = self::API_V3 . '/trades';
    const KLINES        = self::API_V3 . '/klines';
    const AVG_PRICE     = self::API_V3 . '/avgPrice';

    // Account
    const ACCOUNT         = self::API_V3 . '/account';
    const DEPOSIT_ADDRESS = self::API_V3 . '/capital/deposit/address';
    const DEPOSIT_HISTORY = self::API_V3 . '/capital/deposit/hisrec';
    const WITHDRAW        = self::API_V3 . '/capital/withdraw/apply';
    const WITHDRAW_HISTORY= self::API_V3 . '/capital/withdraw/history';

    // Orders
    const ORDER        = self::API_V3 . '/order';
    const OPEN_ORDERS  = self::API_V3 . '/openOrders';
    const ALL_ORDERS   = self::API_V3 . '/allOrders';
    const MY_TRADES    = self::API_V3 . '/myTrades';

    const INTERVAL_MAP = [
        '1m'=>'1m','5m'=>'5m','15m'=>'15m','30m'=>'30m',
        '1h'=>'60m','4h'=>'4h','1d'=>'1d','1w'=>'1W','1M'=>'1M',
    ];
}
