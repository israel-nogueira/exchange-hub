<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Bitget;
class BitgetConfig
{
    const BASE_URL = 'https://api.bitget.com';
    const API_V2   = '/api/v2';
    // Market
    const SYMBOLS      = self::API_V2 . '/spot/public/symbols';
    const TICKER       = self::API_V2 . '/spot/market/tickers';
    const DEPTH        = self::API_V2 . '/spot/market/orderbook';
    const TRADES       = self::API_V2 . '/spot/market/fills';
    const CANDLES      = self::API_V2 . '/spot/market/candles';
    // Account
    const ACCOUNT      = self::API_V2 . '/spot/account/assets';
    const DEPOSIT_ADDR = self::API_V2 . '/spot/wallet/deposit-address';
    const DEPOSIT_HIST = self::API_V2 . '/spot/wallet/deposit-records';
    const WITHDRAW     = self::API_V2 . '/spot/wallet/withdrawal';
    const WITHDRAW_HIST= self::API_V2 . '/spot/wallet/withdrawal-records';
    const FEE_RATE     = self::API_V2 . '/spot/account/trade-fee';
    // Orders
    const ORDER        = self::API_V2 . '/spot/trade/orders';
    const ORDER_DETAIL = self::API_V2 . '/spot/trade/orderInfo';
    const CANCEL_ORDER = self::API_V2 . '/spot/trade/cancel-order';
    const CANCEL_ALL   = self::API_V2 . '/spot/trade/cancel-symbol-order';
    const OPEN_ORDERS  = self::API_V2 . '/spot/trade/unfilled-orders';
    const ORDER_HIST   = self::API_V2 . '/spot/trade/history-orders';
    const MY_TRADES    = self::API_V2 . '/spot/trade/fills';
    const EDIT_ORDER   = self::API_V2 . '/spot/trade/modify-order';
    const INTERVAL_MAP = ['1m'=>'1min','5m'=>'5min','15m'=>'15min','30m'=>'30min','1h'=>'1h','4h'=>'4h','1d'=>'1day','1w'=>'1week'];
}
