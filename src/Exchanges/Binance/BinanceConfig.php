<?php

namespace Exchanges\Exchanges\Binance;

class BinanceConfig
{
    const BASE_URL         = 'https://api.binance.com';
    const TESTNET_URL      = 'https://testnet.binance.vision';
    const BASE_URL_ALT     = 'https://api-gcp.binance.com';

    const API_V1           = '/api/v1';
    const API_V3           = '/api/v3';
    const SAPI_V1          = '/sapi/v1';
    const SAPI_V3          = '/sapi/v3';

    // Market Data
    const PING             = self::API_V3 . '/ping';
    const TIME             = self::API_V3 . '/time';
    const EXCHANGE_INFO    = self::API_V3 . '/exchangeInfo';
    const TICKER_PRICE     = self::API_V3 . '/ticker/price';
    const TICKER_24H       = self::API_V3 . '/ticker/24hr';
    const TICKER_BOOK      = self::API_V3 . '/ticker/bookTicker';
    const DEPTH            = self::API_V3 . '/depth';
    const TRADES           = self::API_V3 . '/trades';
    const HISTORICAL_TRADES= self::API_V3 . '/historicalTrades';
    const AGG_TRADES       = self::API_V3 . '/aggTrades';
    const KLINES           = self::API_V3 . '/klines';
    const AVG_PRICE        = self::API_V3 . '/avgPrice';

    // Account
    const ACCOUNT          = self::API_V3 . '/account';
    const MY_TRADES        = self::API_V3 . '/myTrades';
    const RATE_LIMIT_ORDER = self::API_V3 . '/rateLimit/order';

    // Orders
    const ORDER            = self::API_V3 . '/order';
    const ORDER_TEST       = self::API_V3 . '/order/test';
    const ORDER_OCO        = self::API_V3 . '/order/oco';
    const ORDER_LIST       = self::API_V3 . '/orderList';
    const OPEN_ORDERS      = self::API_V3 . '/openOrders';
    const ALL_ORDERS       = self::API_V3 . '/allOrders';

    // Wallet / SAPI
    const DEPOSIT_ADDRESS  = self::SAPI_V1 . '/capital/deposit/address';
    const DEPOSIT_HISTORY  = self::SAPI_V1 . '/capital/deposit/hisrec';
    const WITHDRAW         = self::SAPI_V1 . '/capital/withdraw/apply';
    const WITHDRAW_HISTORY = self::SAPI_V1 . '/capital/withdraw/history';
    const ASSET_DETAIL     = self::SAPI_V1 . '/asset/assetDetail';
    const TRADE_FEE        = self::SAPI_V1 . '/asset/tradeFee';
    const DUST_LOG         = self::SAPI_V1 . '/asset/dribblet';
    const DUST_TRANSFER    = self::SAPI_V1 . '/asset/dust';
    const ASSET_DIVIDEND   = self::SAPI_V1 . '/asset/assetDividend';
    const GET_ASSET        = self::SAPI_V1 . '/asset/getUserAsset';

    // Staking
    const STAKING_PRODUCT_LIST  = self::SAPI_V1 . '/staking/productList';
    const STAKING_PURCHASE      = self::SAPI_V1 . '/staking/purchase';
    const STAKING_REDEEM        = self::SAPI_V1 . '/staking/redeem';
    const STAKING_POSITION      = self::SAPI_V1 . '/staking/position';
    const STAKING_HISTORY       = self::SAPI_V1 . '/staking/stakingRecord';

    // Rate limits (requests per minute)
    const RATE_LIMIT_WEIGHT     = 6000;
    const RATE_LIMIT_ORDERS_SEC = 100;
    const RATE_LIMIT_ORDERS_DAY = 200000;

    // Intervalos de kline válidos
    const KLINE_INTERVALS = ['1s','1m','3m','5m','15m','30m','1h','2h','4h','6h','8h','12h','1d','3d','1w','1M'];
}
