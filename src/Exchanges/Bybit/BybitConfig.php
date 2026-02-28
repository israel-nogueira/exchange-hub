<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Bybit;
class BybitConfig {
    const BASE_URL    = 'https://api.bybit.com';
    const TESTNET_URL = 'https://api-testnet.bybit.com';
    const V5          = '/v5';
    const TICKER       = self::V5.'/market/tickers';
    const INSTRUMENTS  = self::V5.'/market/instruments-info';
    const ORDERBOOK    = self::V5.'/market/orderbook';
    const RECENT_TRADES= self::V5.'/market/recent-trade';
    const KLINE        = self::V5.'/market/kline';
    const ORDER        = self::V5.'/order/create';
    const CANCEL_ORDER = self::V5.'/order/cancel';
    const CANCEL_ALL   = self::V5.'/order/cancel-all';
    const AMEND_ORDER  = self::V5.'/order/amend';
    const ORDER_REALTIME= self::V5.'/order/realtime';
    const ORDER_HISTORY= self::V5.'/order/history';
    const MY_TRADES    = self::V5.'/execution/list';
    const WALLET_BALANCE= self::V5.'/account/wallet-balance';
    const DEPOSIT_ADDR = self::V5.'/asset/deposit/query-address';
    const DEPOSIT_RECORDS= self::V5.'/asset/deposit/query-record';
    const WITHDRAW_RECORDS= self::V5.'/asset/withdraw/query-record';
    const WITHDRAW     = self::V5.'/asset/withdraw/create';
}
