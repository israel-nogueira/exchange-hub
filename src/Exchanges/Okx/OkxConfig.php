<?php
namespace Exchanges\Exchanges\Okx;
class OkxConfig
{
    const BASE_URL  = 'https://www.okx.com';
    const DEMO_URL  = 'https://www.okx.com'; // usa header x-simulated-trading: 1
    const V5        = '/api/v5';
    // Market
    const INSTRUMENTS  = self::V5 . '/public/instruments';
    const TICKER       = self::V5 . '/market/ticker';
    const TICKERS      = self::V5 . '/market/tickers';
    const BOOKS        = self::V5 . '/market/books';
    const TRADES       = self::V5 . '/market/trades';
    const CANDLES      = self::V5 . '/market/candles';
    const HISTORY_CANDLES = self::V5 . '/market/history-candles';
    const INDEX_TICKERS= self::V5 . '/market/index-tickers';
    const MARK_PRICE   = self::V5 . '/public/mark-price';
    const FUNDING_RATE = self::V5 . '/public/funding-rate';
    const OPEN_INTEREST= self::V5 . '/public/open-interest';
    // Account
    const ACCOUNT      = self::V5 . '/account/balance';
    const POSITIONS    = self::V5 . '/account/positions';
    const ACCOUNT_CONFIG = self::V5 . '/account/config';
    const SET_LEVERAGE = self::V5 . '/account/set-leverage';
    const GET_LEVERAGE = self::V5 . '/account/leverage-info';
    const SET_MARGIN_MODE = self::V5 . '/account/set-position-mode';
    // Trade
    const ORDER        = self::V5 . '/trade/order';
    const BATCH_ORDERS = self::V5 . '/trade/batch-orders';
    const CANCEL_ORDER = self::V5 . '/trade/cancel-order';
    const CANCEL_BATCH = self::V5 . '/trade/cancel-batch-orders';
    const AMEND_ORDER  = self::V5 . '/trade/amend-order';
    const FILLS        = self::V5 . '/trade/fills';
    const ORDERS_HISTORY = self::V5 . '/trade/orders-history';
    const ORDERS_PENDING = self::V5 . '/trade/orders-pending';
    const CLOSE_POSITION = self::V5 . '/trade/close-position';
    // Asset
    const DEPOSIT_ADDRESS = self::V5 . '/asset/deposit-address';
    const DEPOSIT_HISTORY = self::V5 . '/asset/deposit-history';
    const WITHDRAWAL      = self::V5 . '/asset/withdrawal';
    const WITHDRAWAL_HISTORY = self::V5 . '/asset/withdrawal-history';
    const ASSET_BALANCES  = self::V5 . '/asset/balances';
    const TRANSFER        = self::V5 . '/asset/transfer';
    const CURRENCIES      = self::V5 . '/asset/currencies';
    // Earn
    const EARN_OFFERS     = self::V5 . '/finance/staking-defi/offers';
    const EARN_PURCHASE   = self::V5 . '/finance/staking-defi/purchase';
    const EARN_REDEEM     = self::V5 . '/finance/staking-defi/redeem';
    const EARN_ACTIVE     = self::V5 . '/finance/staking-defi/orders-active';

    const INTERVAL_MAP = ['1m'=>'1m','3m'=>'3m','5m'=>'5m','15m'=>'15m','30m'=>'30m','1h'=>'1H','2h'=>'2H','4h'=>'4H','6h'=>'6H','12h'=>'12H','1d'=>'1D','1w'=>'1W','1M'=>'1M'];
}
