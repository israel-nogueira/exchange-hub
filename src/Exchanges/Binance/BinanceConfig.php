<?php

namespace IsraelNogueira\ExchangeHub\Exchanges\Binance;

class BinanceConfig
{
    const BASE_URL    = 'https://api.binance.com';
    const TESTNET_URL = 'https://testnet.binance.vision';
    const API_V3      = '/api/v3';
    const SAPI_V1     = '/sapi/v1';

    const PING              = self::API_V3 . '/ping';
    const TIME              = self::API_V3 . '/time';
    const EXCHANGE_INFO     = self::API_V3 . '/exchangeInfo';
    const TICKER_24H        = self::API_V3 . '/ticker/24hr';
    const DEPTH             = self::API_V3 . '/depth';
    const TRADES            = self::API_V3 . '/trades';
    const HISTORICAL_TRADES = self::API_V3 . '/historicalTrades';
    const KLINES            = self::API_V3 . '/klines';
    const AVG_PRICE         = self::API_V3 . '/avgPrice';
    const ACCOUNT           = self::API_V3 . '/account';
    const MY_TRADES         = self::API_V3 . '/myTrades';
    const ORDER             = self::API_V3 . '/order';
    const ORDER_OCO         = self::API_V3 . '/order/oco';
    const OPEN_ORDERS       = self::API_V3 . '/openOrders';
    const ALL_ORDERS        = self::API_V3 . '/allOrders';

    const DEPOSIT_ADDRESS  = self::SAPI_V1 . '/capital/deposit/address';
    const DEPOSIT_HISTORY  = self::SAPI_V1 . '/capital/deposit/hisrec';
    const WITHDRAW         = self::SAPI_V1 . '/capital/withdraw/apply';
    const WITHDRAW_HISTORY = self::SAPI_V1 . '/capital/withdraw/history';
    const TRADE_FEE        = self::SAPI_V1 . '/asset/tradeFee';
    const DUST_LOG         = self::SAPI_V1 . '/asset/dribblet';
    const DUST_TRANSFER    = self::SAPI_V1 . '/asset/dust';

    const STAKING_PRODUCT_LIST = self::SAPI_V1 . '/staking/productList';
    const STAKING_PURCHASE     = self::SAPI_V1 . '/staking/purchase';
    const STAKING_REDEEM       = self::SAPI_V1 . '/staking/redeem';
    const STAKING_POSITION     = self::SAPI_V1 . '/staking/position';
}
