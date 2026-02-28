<?php
namespace Exchanges\Exchanges\Coinbase;
class CoinbaseConfig
{
    const BASE_URL='https://api.coinbase.com';
    const API='/api/v3/brokerage';
    const PRODUCTS=self::API.'/products';
    const ORDERS=self::API.'/orders';
    const ORDERS_BATCH=self::API.'/orders/batch_cancel';
    const FILLS=self::API.'/orders/historical/fills';
    const BEST_BID_ASK=self::API.'/best_bid_ask';
    const ACCOUNT=self::API.'/accounts';
    const PORTFOLIOS=self::API.'/portfolios';
    const TRANSACTION_SUMMARY=self::API.'/transaction_summary';
    const CONVERT=self::API.'/convert/quote';
    const COMMIT_CONVERT=self::API.'/convert/trade';
}
