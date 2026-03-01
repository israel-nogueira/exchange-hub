<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Gateio;

class GateioConfig
{
    const BASE_URL = 'https://api.gateio.ws/api/v4';

    // Market
    const CURRENCY_PAIRS  = '/spot/currency_pairs';
    const TICKERS         = '/spot/tickers';
    const ORDER_BOOK      = '/spot/order_book';
    const TRADES          = '/spot/trades';
    const CANDLES         = '/spot/candlesticks';

    // Account
    const ACCOUNTS        = '/spot/accounts';
    const DEPOSIT_ADDRESS = '/wallet/deposit_address';
    const DEPOSITS        = '/wallet/deposits';
    const WITHDRAWALS     = '/wallet/withdrawals';
    const WITHDRAW        = '/wallet/withdrawals';

    // Trading
    const ORDERS          = '/spot/orders';
    const ORDER           = '/spot/orders/{order_id}';
    const CANCEL_ALL      = '/spot/cancel_batch_orders';
    const MY_TRADES       = '/spot/my_trades';

    // Earn
    const EARN_PRODUCTS   = '/earn/uni/currencies';
    const EARN_LEND       = '/earn/uni/lends';
    const EARN_POSITIONS  = '/earn/uni/lends';

    const INTERVAL_MAP = [
        '1m'=>'1m','5m'=>'5m','15m'=>'15m','30m'=>'30m',
        '1h'=>'1h','4h'=>'4h','8h'=>'8h','1d'=>'1d','1w'=>'7d',
    ];
}
