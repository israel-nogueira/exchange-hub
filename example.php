<?php

/**
 * ══════════════════════════════════════════════════════
 *  EXEMPLO DE USO — Exchange Hub PHP
 * ══════════════════════════════════════════════════════
 */

require_once __DIR__ . '/vendor/autoload.php';

use IsraelNogueira\ExchangeHub\Core\ExchangeManager;

// ─── 1. Instancia a FakeExchange (sem precisar de API) ───────────────────────
$exchange = ExchangeManager::make('fake', [
    'data_path' => __DIR__ . '/src/Exchanges/Fake/data',
]);

// ─── 2. Ping ─────────────────────────────────────────────────────────────────
echo "Ping: " . ($exchange->ping() ? "✅ Online" : "❌ Offline") . "\n\n";

// ─── 3. Ticker ───────────────────────────────────────────────────────────────
$ticker = $exchange->getTicker('BTCUSDT');
echo "📊 Ticker BTCUSDT\n";
echo "   Preço:     $ " . number_format($ticker->price, 2) . "\n";
echo "   Bid:       $ " . number_format($ticker->bid, 2) . "\n";
echo "   Ask:       $ " . number_format($ticker->ask, 2) . "\n";
echo "   Var 24h:   " . $ticker->changePct24h . "%\n\n";

// ─── 4. OrderBook ────────────────────────────────────────────────────────────
$book = $exchange->getOrderBook('BTCUSDT', 5);
echo "📖 OrderBook BTCUSDT (top 5)\n";
echo "   Best Bid: $ " . $book->bestBid() . "\n";
echo "   Best Ask: $ " . $book->bestAsk() . "\n";
echo "   Spread:   $ " . number_format($book->spread(), 2) . "\n\n";

// ─── 5. Saldos ───────────────────────────────────────────────────────────────
$balances = $exchange->getBalances();
echo "💰 Saldos\n";
foreach ($balances as $asset => $balance) {
    echo "   {$asset}: {$balance->free} (livre) | {$balance->locked} (bloqueado)\n";
}
echo "\n";

// ─── 6. Cria ordem MARKET ────────────────────────────────────────────────────
echo "📦 Criando ordem MARKET BUY 0.001 BTC...\n";
$order = $exchange->createOrder(
    symbol:   'BTCUSDT',
    side:     'BUY',
    type:     'MARKET',
    quantity: 0.001,
);
echo "   ID:     {$order->orderId}\n";
echo "   Status: {$order->status}\n";
echo "   Preço:  $ " . number_format($order->avgPrice, 2) . "\n\n";

// ─── 7. Cria ordem LIMIT ─────────────────────────────────────────────────────
$limitPrice = $ticker->price * 0.98; // 2% abaixo do mercado
echo "📦 Criando ordem LIMIT BUY 0.01 BTC @ $" . number_format($limitPrice, 2) . "...\n";
$limitOrder = $exchange->createOrder(
    symbol:   'BTCUSDT',
    side:     'BUY',
    type:     'LIMIT',
    quantity: 0.01,
    price:    $limitPrice,
);
echo "   ID:     {$limitOrder->orderId}\n";
echo "   Status: {$limitOrder->status}\n\n";

// ─── 8. Ordens abertas ───────────────────────────────────────────────────────
$openOrders = $exchange->getOpenOrders('BTCUSDT');
echo "📋 Ordens abertas BTCUSDT: " . count($openOrders) . "\n";
foreach ($openOrders as $o) {
    echo "   [{$o->orderId}] {$o->side} {$o->quantity} @ $ " . number_format($o->price, 2) . " [{$o->status}]\n";
}
echo "\n";

// ─── 9. Cancela ordem limit ──────────────────────────────────────────────────
if (!empty($openOrders)) {
    $cancelled = $exchange->cancelOrder('BTCUSDT', $openOrders[0]->orderId);
    echo "🚫 Ordem cancelada: {$cancelled->orderId} [{$cancelled->status}]\n\n";
}

// ─── 10. OCO ─────────────────────────────────────────────────────────────────
echo "📦 Criando ordem OCO SELL (take profit + stop loss)...\n";
$oco = $exchange->createOCOOrder(
    symbol:         'BTCUSDT',
    side:           'SELL',
    quantity:       0.001,
    price:          $ticker->price * 1.05,  // take profit +5%
    stopPrice:      $ticker->price * 0.95,  // stop trigger -5%
    stopLimitPrice: $ticker->price * 0.94,  // stop limit -6%
);
echo "   Grupo OCO: {$oco['oco_group_id']}\n";
echo "   Limit:     $ " . number_format($oco['limit_order']->price, 2) . "\n";
echo "   Stop:      $ " . number_format($oco['stop_order']->stopPrice, 2) . "\n\n";

// ─── 11. Candles ─────────────────────────────────────────────────────────────
$candles = $exchange->getCandles('BTCUSDT', '1h', 5);
echo "🕯️  Últimas 5 velas 1h BTCUSDT\n";
foreach ($candles as $c) {
    $dir = $c->isBullish() ? '🟢' : '🔴';
    echo "   {$dir} O:{$c->open} H:{$c->high} L:{$c->low} C:{$c->close} V:{$c->volume}\n";
}
echo "\n";

// ─── 12. Endereço de depósito ────────────────────────────────────────────────
$deposit = $exchange->getDepositAddress('BTC');
echo "📥 Endereço de depósito BTC\n";
echo "   Rede:     {$deposit->network}\n";
echo "   Endereço: {$deposit->address}\n\n";

// ─── 13. Saque ───────────────────────────────────────────────────────────────
$withdraw = $exchange->withdraw('USDT', '0xEnderecoExterno123', 100.0, 'ERC20');
echo "📤 Saque realizado\n";
echo "   ID:         {$withdraw->withdrawId}\n";
echo "   Valor:      {$withdraw->amount} USDT\n";
echo "   Taxa:       {$withdraw->fee} USDT\n";
echo "   Líquido:    {$withdraw->netAmount} USDT\n";
echo "   Status:     {$withdraw->status}\n\n";

// ─── 14. Staking ─────────────────────────────────────────────────────────────
$staked = $exchange->stakeAsset('ETH', 1.0);
echo "🏦 Staking\n";
echo "   Ativo:  {$staked['asset']}\n";
echo "   Valor:  {$staked['staked']}\n";
echo "   APY:    {$staked['apy']}\n\n";

// ─── 15. Saldo final ─────────────────────────────────────────────────────────
echo "💰 Saldo final\n";
foreach ($exchange->getBalances() as $asset => $b) {
    echo "   {$asset}: livre={$b->free} | bloqueado={$b->locked} | staked={$b->staked} | total={$b->total()}\n";
}
echo "\n";

echo "✅ Tudo funcionando com FakeExchange — nenhuma API necessária!\n";

// ─── Exemplos com exchanges reais (descomentar quando tiver credenciais) ──────

/*
// Binance
$binance = ExchangeManager::make('binance', [
    'api_key'    => 'SUA_API_KEY',
    'api_secret' => 'SUA_API_SECRET',
]);
$ticker = $binance->getTicker('BTCUSDT'); // mesma interface!
echo $ticker->price;

// Binance Testnet
$binanceTest = ExchangeManager::make('binance', [
    'api_key'    => 'SUA_API_KEY_TESTNET',
    'api_secret' => 'SUA_API_SECRET_TESTNET',
    'testnet'    => true,
]);

// OKX (requer passphrase)
$okx = ExchangeManager::make('okx', [
    'api_key'    => 'SUA_API_KEY',
    'api_secret' => 'SUA_API_SECRET',
    'passphrase' => 'SUA_PASSPHRASE',
]);

// OKX Demo Trading
$okxDemo = ExchangeManager::make('okx', [
    'api_key'    => 'SUA_API_KEY',
    'api_secret' => 'SUA_API_SECRET',
    'passphrase' => 'SUA_PASSPHRASE',
    'demo'       => true,
]);

// KuCoin (requer passphrase)
$kucoin = ExchangeManager::make('kucoin', [
    'api_key'    => 'SUA_API_KEY',
    'api_secret' => 'SUA_API_SECRET',
    'passphrase' => 'SUA_PASSPHRASE',
]);

// Mercado Bitcoin (OAuth2 — só informar api_key e api_secret)
$mb = ExchangeManager::make('mercadobitcoin', [
    'api_key'    => 'SUA_API_KEY',
    'api_secret' => 'SUA_API_SECRET',
]);

// Bybit Testnet
$bybitTest = ExchangeManager::make('bybit', [
    'api_key'    => 'SUA_API_KEY',
    'api_secret' => 'SUA_API_SECRET',
    'testnet'    => true,
]);

// Listar todas as exchanges disponíveis
$available = ExchangeManager::available();
// ['fake', 'binance', 'coinbase', 'okx', 'bybit', 'kraken', 'kucoin',
//  'gateio', 'bitfinex', 'mercadobitcoin', 'mexc', 'bitget', 'gemini', 'bitstamp']
*/
