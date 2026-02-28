<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Coinbase;
class CoinbaseConfig {
    const BASE_URL = 'https://api.coinbase.com/api/v3/brokerage';
    const PRODUCTS           = '/products';
    const BEST_BID_ASK       = '/best_bid_ask';
    const ACCOUNT            = '/accounts';
    const ORDERS             = '/orders';
    const ORDERS_BATCH       = '/orders/batch_cancel';
    const FILLS              = '/orders/historical/fills';
    const TRANSACTION_SUMMARY= '/transaction_summary';
}
