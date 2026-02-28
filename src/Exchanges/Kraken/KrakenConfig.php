<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Kraken;
class KrakenConfig {
    const BASE_URL    = 'https://api.kraken.com';
    const API_PUBLIC  = '/0/public';
    const API_PRIVATE = '/0/private';
    const PING        = self::API_PUBLIC  . '/SystemStatus';
    const TIME        = self::API_PUBLIC  . '/Time';
    const ASSET_PAIRS = self::API_PUBLIC  . '/AssetPairs';
    const ASSETS      = self::API_PUBLIC  . '/Assets';
    const TICKER      = self::API_PUBLIC  . '/Ticker';
    const OHLC        = self::API_PUBLIC  . '/OHLC';
    const DEPTH       = self::API_PUBLIC  . '/Depth';
    const TRADES      = self::API_PUBLIC  . '/Trades';
    const BALANCE     = self::API_PRIVATE . '/Balance';
    const TRADE_BALANCE= self::API_PRIVATE . '/TradeBalance';
    const OPEN_ORDERS = self::API_PRIVATE . '/OpenOrders';
    const CLOSED_ORDERS= self::API_PRIVATE . '/ClosedOrders';
    const QUERY_ORDERS= self::API_PRIVATE . '/QueryOrders';
    const TRADES_HISTORY= self::API_PRIVATE . '/TradesHistory';
    const TRADE_VOLUME= self::API_PRIVATE . '/TradeVolume';
    const ADD_ORDER   = self::API_PRIVATE . '/AddOrder';
    const EDIT_ORDER  = self::API_PRIVATE . '/EditOrder';
    const CANCEL_ORDER= self::API_PRIVATE . '/CancelOrder';
    const CANCEL_ALL  = self::API_PRIVATE . '/CancelAll';
    const DEPOSIT_METHODS= self::API_PRIVATE . '/DepositMethods';
    const DEPOSIT_ADDR= self::API_PRIVATE . '/DepositAddresses';
    const DEPOSIT_STATUS= self::API_PRIVATE . '/DepositStatus';
    const WITHDRAW    = self::API_PRIVATE . '/Withdraw';
    const WITHDRAW_STATUS= self::API_PRIVATE . '/WithdrawStatus';
    const STAKE_ASSET = self::API_PRIVATE . '/Stake';
    const UNSTAKE_ASSET= self::API_PRIVATE . '/Unstake';
    const STAKING_TXNS= self::API_PRIVATE . '/Staking/Transactions';
}
