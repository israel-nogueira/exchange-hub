<?php
namespace Exchanges\Exchanges\MercadoBitcoin;
class MercadoBitcoinConfig
{
    const BASE_URL   = 'https://api.mercadobitcoin.net';
    const AUTH_URL   = 'https://api.mercadobitcoin.net/auth/token';
    const API        = '/api/v4';
    const TICKERS    = self::API.'/tickers';
    const TICKER     = self::API.'/:symbol/ticker';
    const ORDERBOOK  = self::API.'/:symbol/orderbook';
    const TRADES     = self::API.'/:symbol/trades';
    const CANDLES    = self::API.'/:symbol/candles';
    const ACCOUNTS   = self::API.'/accounts';
    const ORDERS     = self::API.'/:symbol/orders';
    const ORDER      = self::API.'/:symbol/orders/:orderId';
    const POSITION   = self::API.'/accounts/:accountId/portfolio';
}
