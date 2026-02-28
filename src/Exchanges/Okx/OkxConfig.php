<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Okx;
class OkxConfig {
    const BASE_URL='/api/v5'; const HOST='https://www.okx.com';
    // Market
    const TICKER='/market/ticker'; const TICKERS='/market/tickers';
    const INSTRUMENTS='/public/instruments'; const BOOKS='/market/books';
    const TRADES='/market/trades'; const CANDLES='/market/candles';
    // Account
    const ACCOUNT_BALANCE='/account/balance'; const ACCOUNT_INFO='/account/config';
    const DEPOSIT_ADDRESS='/asset/deposit-address';
    const DEPOSIT_HISTORY='/asset/deposit-history';
    const WITHDRAWAL='/asset/withdrawal'; const WITHDRAWAL_HISTORY='/asset/withdrawal-history';
    // Trading
    const ORDER='/trade/order'; const CANCEL_ORDER='/trade/cancel-order';
    const CANCEL_BATCH='/trade/cancel-batch-orders'; const ORDERS_PENDING='/trade/orders-pending';
    const ORDERS_HISTORY='/trade/orders-history'; const FILLS='/trade/fills';
    const AMEND_ORDER='/trade/amend-order';
    // Earn
    const EARN_OFFERS='/finance/savings/lending-rate-summary';
    const EARN_PURCHASE='/finance/savings/purchase-redempt';
    const EARN_ACTIVE='/finance/savings/lending-history';
    const EARN_REDEEM='/finance/savings/purchase-redempt';
    const INTERVAL_MAP=['1m'=>'1m','5m'=>'5m','15m'=>'15m','30m'=>'30m','1h'=>'1H','4h'=>'4H','1d'=>'1D','1w'=>'1W','1M'=>'1M'];
}
