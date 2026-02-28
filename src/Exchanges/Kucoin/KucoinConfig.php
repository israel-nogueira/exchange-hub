<?php
namespace Exchanges\Exchanges\Kucoin;
class KucoinConfig
{
    const BASE_URL='https://api.kucoin.com';
    const API1='/api/v1'; const API2='/api/v2'; const API3='/api/v3';
    const TICKER=self::API1.'/market/orderbook/level1';
    const TICKER_ALL=self::API1.'/market/allTickers';
    const ORDERBOOK=self::API1.'/market/orderbook/level2_20';
    const TRADES=self::API1.'/market/histories';
    const KLINES=self::API1.'/market/candles';
    const SYMBOLS=self::API1.'/symbols';
    const TIME=self::API1.'/timestamp';
    const ACCOUNTS=self::API1.'/accounts';
    const DEPOSIT_ADDR=self::API2.'/deposit-addresses';
    const DEPOSIT_HIST=self::API1.'/deposits';
    const WITHDRAW=self::API1.'/withdrawals';
    const WITHDRAW_HIST=self::API1.'/withdrawals';
    const ORDERS=self::API1.'/orders';
    const ORDER_HISTORY=self::API1.'/hist-orders';
    const MY_TRADES=self::API1.'/fills';
    const INNER_TRANSFER=self::API2.'/accounts/inner-transfer';
}
