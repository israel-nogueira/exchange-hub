<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Bitstamp;
class BitstampConfig
{
    const BASE_URL = 'https://www.bitstamp.net/api/v2';
    const TICKER       = '/ticker/{pair}/';
    const TICKERS      = '/ticker/';
    const ORDER_BOOK   = '/order_book/{pair}/';
    const TRANSACTIONS = '/transactions/{pair}/';
    const OHLC         = '/ohlc/{pair}/';
    const TRADING_PAIRS= '/trading-pairs-info/';
    const BALANCE      = '/balance/';
    const USER_TXNS    = '/user_transactions/';
    const OPEN_ORDERS  = '/open_orders/{pair}/';
    const ALL_ORDERS   = '/open_orders/all/';
    const ORDER_STATUS = '/order_status/';
    const BUY          = '/buy/{pair}/';
    const BUY_MARKET   = '/buy/market/{pair}/';
    const SELL         = '/sell/{pair}/';
    const SELL_MARKET  = '/sell/market/{pair}/';
    const CANCEL_ORDER = '/cancel_order/';
    const CANCEL_ALL   = '/cancel_all_orders/';
    const DEPOSIT_ADDR = '/crypto-deposit-address/';
    const WITHDRAWAL_REQUESTS = '/withdrawal-requests/';
    const CRYPTO_WITHDRAW     = '/crypto-withdrawal/';
    const INTERVAL_MAP = ['1m'=>'60','3m'=>'180','5m'=>'300','15m'=>'900','30m'=>'1800','1h'=>'3600','4h'=>'14400','1d'=>'86400'];
}
