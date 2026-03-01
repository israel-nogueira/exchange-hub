<?php

/**
 * ══════════════════════════════════════════════════════════════════
 *  Exchange Hub — API Endpoint Health Check + Schema Validation
 *
 *  Testa dois níveis por endpoint:
 *    1. HTTP — a URL responde com 200 e JSON válido?
 *    2. Schema — os campos que os Normalizers usam ainda existem?
 *
 *  Uso:
 *    php check-endpoints.php                     # salva no mesmo diretório
 *    php check-endpoints.php /path/to/reports    # salva no diretório especificado
 * ══════════════════════════════════════════════════════════════════
 */

declare(strict_types=1);

const TIMEOUT     = 10;
const SYMBOL_SPOT = 'BTCUSDT';

$outputDir = isset($argv[1]) ? rtrim($argv[1], '/') : __DIR__;
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$OUTPUT_JSON = $outputDir . '/endpoint-report.json';
$OUTPUT_MD   = $outputDir . '/endpoint-report.md';

// ─── Known datacenter blocks ──────────────────────────────────────────────────

$datacenterBlocked = [
    'binance'  => ['codes' => [451, 403], 'reason' => 'Bloqueia IPs de datacenter/EUA (HTTP 451). Teste localmente com IP residencial.'],
    'bybit'    => ['codes' => [403],      'reason' => 'Bloqueia IPs de datacenter desde 2023 (HTTP 403). Teste localmente com IP residencial.'],
    'coinbase' => ['codes' => [401, 403], 'reason' => 'Coinbase Advanced Trade API exige autenticação mesmo em endpoints públicos.'],
];

// ─── Schema validators ────────────────────────────────────────────────────────
//
// Cada validator recebe o array decodificado da resposta e retorna:
//   ['ok' => true]                          se os campos obrigatórios existem
//   ['ok' => false, 'missing' => [...]]     se algum campo sumiu
//
// Os campos mapeados são exatamente os que cada Normalizer usa sem ?? fallback,
// ou seja: campos cuja ausência quebraria silenciosamente o DTO.

$schemas = [

    // ── Binance ───────────────────────────────────────────────────────────────
    // BinanceNormalizer::ticker() usa: symbol, lastPrice(ou price), bidPrice, askPrice
    // BinanceNormalizer::candle() usa: $d[0..8] (array posicional)
    // BinanceNormalizer::orderBook() usa: bids, asks (arrays)
    // BinanceNormalizer::trade() usa: id, price, qty
    'binance' => [
        'ticker'        => fn($r) => validateKeys($r, ['symbol', 'lastPrice', 'bidPrice', 'askPrice', 'highPrice', 'lowPrice', 'volume']),
        'order_book'    => fn($r) => validateKeys($r, ['bids', 'asks']),
        'recent_trades' => fn($r) => validateKeys($r[0] ?? [], ['id', 'price', 'qty', 'time']),
        'candles'       => fn($r) => validatePositional($r[0] ?? [], 9, 'candle[0..8]'),
        'exchange_info' => fn($r) => validateKeys($r, ['symbols', 'timezone']),
        'server_time'   => fn($r) => validateKeys($r, ['serverTime']),
        'avg_price'     => fn($r) => validateKeys($r, ['price']),
    ],

    // ── OKX ───────────────────────────────────────────────────────────────────
    // OkxNormalizer::ticker() usa: instId, last, bidPx, askPx, open24h, high24h, low24h, vol24h, volCcy24h, ts
    // OkxNormalizer::candle() usa: $d[0..7] posicional
    'okx' => [
        'ticker'        => fn($r) => validateKeys($r[0] ?? $r, ['instId', 'last', 'bidPx', 'askPx', 'open24h', 'high24h', 'low24h', 'vol24h', 'ts']),
        'order_book'    => fn($r) => validatePath($r, 'data.0.bids') && validatePath($r, 'data.0.asks') ? ['ok' => true] : ['ok' => false, 'missing' => ['data[0].bids or data[0].asks']],
        'recent_trades' => fn($r) => validateKeys($r[0] ?? [], ['tradeId', 'px', 'sz', 'side', 'ts']),
        'candles'       => fn($r) => validatePositional($r[0] ?? [], 8, 'candle[0..7]'),
        'exchange_info' => fn($r) => validateKeys($r[0] ?? [], ['instId', 'instType', 'baseCcy', 'quoteCcy']),
        'server_time'   => fn($r) => validateKeys($r[0] ?? $r, ['ts']),
    ],

    // ── Bybit ─────────────────────────────────────────────────────────────────
    // BybitNormalizer::ticker() usa: symbol, lastPrice, bid1Price, ask1Price, highPrice24h, lowPrice24h, volume24h
    // BybitNormalizer::candle() usa: $d[0..5] posicional
    'bybit' => [
        'ticker'        => fn($r) => validatePath($r, 'result.list.0')
            ? validateKeys($r['result']['list'][0], ['symbol', 'lastPrice', 'bid1Price', 'ask1Price', 'highPrice24h', 'lowPrice24h', 'volume24h'])
            : ['ok' => false, 'missing' => ['result.list[0]']],
        'order_book'    => fn($r) => validatePath($r, 'result.b') && validatePath($r, 'result.a') ? ['ok' => true] : ['ok' => false, 'missing' => ['result.b or result.a']],
        'recent_trades' => fn($r) => validatePath($r, 'result.list.0')
            ? validateKeys($r['result']['list'][0], ['execId', 'price', 'size', 'side', 'time'])
            : ['ok' => false, 'missing' => ['result.list[0]']],
        'candles'       => fn($r) => validatePath($r, 'result.list.0')
            ? validatePositional($r['result']['list'][0], 6, 'candle[0..5]')
            : ['ok' => false, 'missing' => ['result.list[0]']],
        'exchange_info' => fn($r) => validatePath($r, 'result.list.0')
            ? validateKeys($r['result']['list'][0], ['symbol', 'baseCoin', 'quoteCoin', 'status'])
            : ['ok' => false, 'missing' => ['result.list[0]']],
    ],

    // ── Kraken ────────────────────────────────────────────────────────────────
    // KrakenNormalizer::ticker() usa: result[pair].c[0] (price), b[0] (bid), a[0] (ask), v[1] (vol), h[1] (high), l[1] (low)
    'kraken' => [
        'ticker'        => fn($r) => !empty($r['result']) && !empty(reset($r['result']))
            ? validateKeys(reset($r['result']), ['c', 'b', 'a', 'v', 'h', 'l'])
            : ['ok' => false, 'missing' => ['result[pair]']],
        'order_book'    => fn($r) => !empty($r['result']) ? validateKeys(reset($r['result']), ['bids', 'asks']) : ['ok' => false, 'missing' => ['result[pair].bids/asks']],
        'recent_trades' => fn($r) => !empty($r['result']) ? ['ok' => true] : ['ok' => false, 'missing' => ['result']],
        'candles'       => fn($r) => !empty($r['result']) ? ['ok' => true] : ['ok' => false, 'missing' => ['result']],
        'exchange_info' => fn($r) => !empty($r['result']) ? ['ok' => true] : ['ok' => false, 'missing' => ['result']],
        'ping'          => fn($r) => validateKeys($r, ['result']),
        'server_time'   => fn($r) => validateKeys($r, ['result']),
    ],

    // ── KuCoin ────────────────────────────────────────────────────────────────
    // KucoinNormalizer::ticker() usa: data.price, data.bestBid, data.bestAsk, data.size
    'kucoin' => [
        'ticker'        => fn($r) => validateKeys($r['data'] ?? [], ['price', 'bestBid', 'bestAsk', 'size']),
        'order_book'    => fn($r) => validateKeys($r['data'] ?? [], ['bids', 'asks']),
        'recent_trades' => fn($r) => !empty($r['data'][0]) ? validateKeys($r['data'][0], ['price', 'size', 'side', 'time']) : ['ok' => false, 'missing' => ['data[0]']],
        'candles'       => fn($r) => !empty($r['data'][0]) ? validatePositional($r['data'][0], 7, 'candle[0..6]') : ['ok' => false, 'missing' => ['data[0]']],
        'exchange_info' => fn($r) => !empty($r['data'][0]) ? validateKeys($r['data'][0], ['symbol', 'baseCurrency', 'quoteCurrency']) : ['ok' => false, 'missing' => ['data[0]']],
        'server_time'   => fn($r) => validateKeys($r, ['data']),
    ],

    // ── Coinbase ─────────────────────────────────────────────────────────────
    // CoinbaseNormalizer::ticker() usa: product_id, price(ou close), best_bid, best_ask
    'coinbase' => [
        'ticker'        => fn($r) => validateKeys($r, ['product_id', 'price', 'best_bid', 'best_ask']),
        'order_book'    => fn($r) => validateKeys($r, ['pricebooks']),
        'candles'       => fn($r) => !empty($r['candles'][0]) ? validateKeys($r['candles'][0], ['start', 'open', 'high', 'low', 'close', 'volume']) : ['ok' => false, 'missing' => ['candles[0]']],
        'exchange_info' => fn($r) => !empty($r['products'][0]) ? validateKeys($r['products'][0], ['product_id', 'base_currency_id', 'quote_currency_id']) : ['ok' => false, 'missing' => ['products[0]']],
    ],

    // ── Mercado Bitcoin ───────────────────────────────────────────────────────
    // MercadoBitcoinNormalizer::ticker() usa: ticker.last, ticker.sell, ticker.buy, ticker.high, ticker.low, ticker.vol
    'mercadobitcoin' => [
        'ticker'        => fn($r) => validateKeys($r['ticker'] ?? [], ['last', 'sell', 'buy', 'high', 'low', 'vol']),
        'order_book'    => fn($r) => validateKeys($r, ['bids', 'asks']),
        'recent_trades' => fn($r) => !empty($r[0]) ? validateKeys($r[0], ['date', 'price', 'amount', 'type']) : ['ok' => false, 'missing' => ['[0]']],
    ],

    // ── Gate.io ───────────────────────────────────────────────────────────────
    // GateioNormalizer::ticker() usa: currency_pair, last, highest_bid, lowest_ask, high_24h, low_24h, base_volume
    'gateio' => [
        'ticker'        => fn($r) => validateKeys($r[0] ?? $r, ['currency_pair', 'last', 'highest_bid', 'lowest_ask', 'high_24h', 'low_24h', 'base_volume']),
        'order_book'    => fn($r) => validateKeys($r, ['bids', 'asks']),
        'recent_trades' => fn($r) => !empty($r[0]) ? validateKeys($r[0], ['id', 'price', 'amount', 'side', 'create_time']) : ['ok' => false, 'missing' => ['[0]']],
        'candles'       => fn($r) => !empty($r[0]) ? validatePositional($r[0], 7, 'candle[0..6]') : ['ok' => false, 'missing' => ['[0]']],
        'exchange_info' => fn($r) => !empty($r[0]) ? validateKeys($r[0], ['id', 'base', 'quote']) : ['ok' => false, 'missing' => ['[0]']],
    ],

    // ── MEXC ──────────────────────────────────────────────────────────────────
    // MexcNormalizer::ticker() usa: symbol, lastPrice(ou price), bidPrice, askPrice, highPrice, lowPrice, volume
    // MexcNormalizer::candle() usa: $d[0..8] posicional
    'mexc' => [
        'ticker'        => fn($r) => validateKeys(is_array($r[0] ?? null) ? $r[0] : $r, ['symbol', 'lastPrice', 'bidPrice', 'askPrice', 'highPrice', 'lowPrice', 'volume']),
        'order_book'    => fn($r) => validateKeys($r, ['bids', 'asks']),
        'recent_trades' => fn($r) => !empty($r[0]) ? validateKeys($r[0], ['id', 'price', 'qty', 'time']) : ['ok' => false, 'missing' => ['[0]']],
        'candles'       => fn($r) => !empty($r[0]) ? validatePositional($r[0], 9, 'candle[0..8]') : ['ok' => false, 'missing' => ['[0]']],
        'exchange_info' => fn($r) => !empty($r['symbols'][0]) ? validateKeys($r['symbols'][0], ['symbol', 'baseAsset', 'quoteAsset', 'status']) : ['ok' => false, 'missing' => ['symbols[0]']],
        'server_time'   => fn($r) => validateKeys($r, ['serverTime']),
    ],

    // ── Bitget ────────────────────────────────────────────────────────────────
    // BitgetNormalizer::ticker() usa: symbol, lastPr, bidPr, askPr, high24h, low24h, baseVolume
    // BitgetNormalizer::candle() usa: $d[0..5] posicional
    'bitget' => [
        'ticker'        => fn($r) => validateKeys($r['data'][0] ?? (is_array($r[0] ?? null) ? $r[0] : $r), ['symbol', 'lastPr', 'bidPr', 'askPr', 'high24h', 'low24h', 'baseVolume']),
        'order_book'    => fn($r) => validateKeys($r['data'] ?? $r, ['bids', 'asks']),
        'recent_trades' => fn($r) => !empty(($r['data'] ?? $r)[0]) ? validateKeys(($r['data'] ?? $r)[0], ['price', 'size', 'side', 'ts']) : ['ok' => false, 'missing' => ['data[0]']],
        'candles'       => fn($r) => !empty(($r['data'] ?? $r)[0]) ? validatePositional(($r['data'] ?? $r)[0], 6, 'candle[0..5]') : ['ok' => false, 'missing' => ['data[0]']],
        'exchange_info' => fn($r) => !empty(($r['data'] ?? $r)[0]) ? validateKeys(($r['data'] ?? $r)[0], ['symbol', 'baseCoin', 'quoteCoin']) : ['ok' => false, 'missing' => ['data[0]']],
        'server_time'   => fn($r) => validateKeys($r['data'] ?? $r, ['serverTime']),
    ],

    // ── Gemini ────────────────────────────────────────────────────────────────
    // GeminiNormalizer::ticker() usa: bid, ask, volume.BTC, volume.USD, last (v2 ticker)
    // GeminiNormalizer::candle() usa: $d[0..5] posicional
    'gemini' => [
        'ticker'        => fn($r) => validateKeys($r, ['bid', 'ask', 'volume', 'close']),
        'order_book'    => fn($r) => validateKeys($r, ['bids', 'asks']),
        'recent_trades' => fn($r) => !empty($r[0]) ? validateKeys($r[0], ['tid', 'price', 'amount', 'type']) : ['ok' => false, 'missing' => ['[0]']],
        'candles'       => fn($r) => !empty($r[0]) ? validatePositional($r[0], 6, 'candle[0..5]') : ['ok' => false, 'missing' => ['[0]']],
        'exchange_info' => fn($r) => is_array($r) && !empty($r[0]) ? ['ok' => true] : ['ok' => false, 'missing' => ['[0] symbol string']],
    ],

    // ── Bitstamp ─────────────────────────────────────────────────────────────
    // BitstampNormalizer::ticker() usa: last, bid, ask, high, low, volume, open
    // BitstampNormalizer::candle() usa: timestamp, open, high, low, close, volume
    'bitstamp' => [
        'ticker'        => fn($r) => validateKeys($r['data'] ?? $r, ['last', 'bid', 'ask', 'high', 'low', 'volume', 'open']),
        'order_book'    => fn($r) => validateKeys($r['data'] ?? $r, ['bids', 'asks']),
        'recent_trades' => fn($r) => !empty(($r['data'] ?? $r)[0]) ? validateKeys(($r['data'] ?? $r)[0], ['date', 'price', 'amount', 'type']) : ['ok' => false, 'missing' => ['data[0]']],
        'candles'       => fn($r) => !empty($r['data']['ohlc'][0]) ? validateKeys($r['data']['ohlc'][0], ['timestamp', 'open', 'high', 'low', 'close', 'volume']) : ['ok' => false, 'missing' => ['data.ohlc[0]']],
    ],

    // ── Bitfinex ──────────────────────────────────────────────────────────────
    // BitfinexNormalizer::ticker() usa: $d[1](bid), $d[3](ask), $d[7](last), $d[8](vol), $d[9](high), $d[10](low)
    // BitfinexNormalizer::candle() usa: $d[0..5] posicional
    'bitfinex' => [
        'ticker'        => fn($r) => validatePositional($r, 11, 'ticker[0..10]'),
        'order_book'    => fn($r) => is_array($r) && is_array($r[0] ?? null) && count($r[0]) >= 3 ? ['ok' => true] : ['ok' => false, 'missing' => ['[0][price,count,amount]']],
        'recent_trades' => fn($r) => !empty($r[0]) ? validatePositional($r[0], 4, 'trade[0..3]') : ['ok' => false, 'missing' => ['[0]']],
        'candles'       => fn($r) => !empty($r[0]) ? validatePositional($r[0], 6, 'candle[0..5]') : ['ok' => false, 'missing' => ['[0]']],
        'server_time'   => fn($r) => is_array($r) && isset($r[0]) ? ['ok' => true] : ['ok' => false, 'missing' => ['[0] status']],
    ],

];

// ─── Endpoints ────────────────────────────────────────────────────────────────

$exchanges = [

    'binance' => [
        'name'        => 'Binance',
        'base_url'    => 'https://api.binance.com',
        'api_version' => 'v3',
        'docs_url'    => 'https://binance-docs.github.io/apidocs/spot/en/',
        'endpoints'   => [
            'ping'          => ['GET', '/api/v3/ping',         []],
            'server_time'   => ['GET', '/api/v3/time',         []],
            'exchange_info' => ['GET', '/api/v3/exchangeInfo', ['symbol' => SYMBOL_SPOT]],
            'ticker'        => ['GET', '/api/v3/ticker/24hr',  ['symbol' => SYMBOL_SPOT]],
            'order_book'    => ['GET', '/api/v3/depth',        ['symbol' => SYMBOL_SPOT, 'limit' => 5]],
            'recent_trades' => ['GET', '/api/v3/trades',       ['symbol' => SYMBOL_SPOT, 'limit' => 5]],
            'candles'       => ['GET', '/api/v3/klines',       ['symbol' => SYMBOL_SPOT, 'interval' => '1h', 'limit' => 3]],
            'avg_price'     => ['GET', '/api/v3/avgPrice',     ['symbol' => SYMBOL_SPOT]],
        ],
    ],

    'okx' => [
        'name'        => 'OKX',
        'base_url'    => 'https://www.okx.com',
        'api_version' => 'v5',
        'docs_url'    => 'https://www.okx.com/docs-v5/en/',
        'endpoints'   => [
            'server_time'   => ['GET', '/api/v5/public/time',        []],
            'exchange_info' => ['GET', '/api/v5/public/instruments',  ['instType' => 'SPOT', 'instId' => 'BTC-USDT']],
            'ticker'        => ['GET', '/api/v5/market/ticker',       ['instId' => 'BTC-USDT']],
            'all_tickers'   => ['GET', '/api/v5/market/tickers',      ['instType' => 'SPOT']],
            'order_book'    => ['GET', '/api/v5/market/books',        ['instId' => 'BTC-USDT', 'sz' => 5]],
            'recent_trades' => ['GET', '/api/v5/market/trades',       ['instId' => 'BTC-USDT', 'limit' => 5]],
            'candles'       => ['GET', '/api/v5/market/candles',      ['instId' => 'BTC-USDT', 'bar' => '1H', 'limit' => 3]],
        ],
    ],

    'bybit' => [
        'name'        => 'Bybit',
        'base_url'    => 'https://api.bybit.com',
        'api_version' => 'v5',
        'docs_url'    => 'https://bybit-exchange.github.io/docs/v5/',
        'endpoints'   => [
            'server_time'   => ['GET', '/v5/market/time',             []],
            'exchange_info' => ['GET', '/v5/market/instruments-info', ['category' => 'spot', 'symbol' => SYMBOL_SPOT]],
            'ticker'        => ['GET', '/v5/market/tickers',          ['category' => 'spot', 'symbol' => SYMBOL_SPOT]],
            'order_book'    => ['GET', '/v5/market/orderbook',        ['category' => 'spot', 'symbol' => SYMBOL_SPOT, 'limit' => 5]],
            'recent_trades' => ['GET', '/v5/market/recent-trade',     ['category' => 'spot', 'symbol' => SYMBOL_SPOT, 'limit' => 5]],
            'candles'       => ['GET', '/v5/market/kline',            ['category' => 'spot', 'symbol' => SYMBOL_SPOT, 'interval' => '60', 'limit' => 3]],
        ],
    ],

    'kraken' => [
        'name'        => 'Kraken',
        'base_url'    => 'https://api.kraken.com',
        'api_version' => 'v0',
        'docs_url'    => 'https://docs.kraken.com/rest/',
        'endpoints'   => [
            'ping'          => ['GET', '/0/public/SystemStatus', []],
            'server_time'   => ['GET', '/0/public/Time',         []],
            'exchange_info' => ['GET', '/0/public/AssetPairs',   ['pair' => 'XXBTZUSD']],
            'ticker'        => ['GET', '/0/public/Ticker',       ['pair' => 'XXBTZUSD']],
            'order_book'    => ['GET', '/0/public/Depth',        ['pair' => 'XXBTZUSD', 'count' => 5]],
            'recent_trades' => ['GET', '/0/public/Trades',       ['pair' => 'XXBTZUSD']],
            'candles'       => ['GET', '/0/public/OHLC',         ['pair' => 'XXBTZUSD', 'interval' => 60]],
        ],
    ],

    'kucoin' => [
        'name'        => 'KuCoin',
        'base_url'    => 'https://api.kucoin.com',
        'api_version' => 'v1/v2',
        'docs_url'    => 'https://docs.kucoin.com/',
        'endpoints'   => [
            'server_time'   => ['GET', '/api/v1/timestamp',                  []],
            'exchange_info' => ['GET', '/api/v1/symbols',                    ['market' => 'USDS']],
            'ticker'        => ['GET', '/api/v1/market/orderbook/level1',    ['symbol' => 'BTC-USDT']],
            'all_tickers'   => ['GET', '/api/v1/market/allTickers',          []],
            'order_book'    => ['GET', '/api/v1/market/orderbook/level2_20', ['symbol' => 'BTC-USDT']],
            'recent_trades' => ['GET', '/api/v1/market/histories',           ['symbol' => 'BTC-USDT']],
            'candles'       => ['GET', '/api/v1/market/candles',             ['symbol' => 'BTC-USDT', 'type' => '1hour']],
        ],
    ],

    'coinbase' => [
        'name'        => 'Coinbase Advanced',
        'base_url'    => 'https://api.coinbase.com',
        'api_version' => 'v3',
        'docs_url'    => 'https://docs.cdp.coinbase.com/advanced-trade/reference/',
        'endpoints'   => [
            'exchange_info' => ['GET', '/api/v3/brokerage/products',                  ['product_type' => 'SPOT', 'limit' => 5]],
            'ticker'        => ['GET', '/api/v3/brokerage/products/BTC-USDT',         []],
            'order_book'    => ['GET', '/api/v3/brokerage/best_bid_ask',              ['product_ids' => 'BTC-USDT']],
            'candles'       => ['GET', '/api/v3/brokerage/products/BTC-USDT/candles', ['start' => (string)(time() - 3600), 'end' => (string)time(), 'granularity' => 'ONE_HOUR']],
        ],
    ],

    'mercadobitcoin' => [
        'name'        => 'Mercado Bitcoin',
        'base_url'    => 'https://www.mercadobitcoin.net',
        'api_version' => 'v4',
        'docs_url'    => 'https://api.mercadobitcoin.net/api/v4/docs',
        'endpoints'   => [
            'ticker'        => ['GET', '/api/BTC/ticker/',    []],
            'order_book'    => ['GET', '/api/BTC/orderbook/', []],
            'recent_trades' => ['GET', '/api/BTC/trades/',    []],
        ],
    ],

    'gateio' => [
        'name'        => 'Gate.io',
        'base_url'    => 'https://api.gateio.ws',
        'api_version' => 'v4',
        'docs_url'    => 'https://www.gate.io/docs/developers/apiv4/',
        'endpoints'   => [
            'exchange_info' => ['GET', '/api/v4/spot/currency_pairs', []],
            'ticker'        => ['GET', '/api/v4/spot/tickers',        ['currency_pair' => 'BTC_USDT']],
            'order_book'    => ['GET', '/api/v4/spot/order_book',     ['currency_pair' => 'BTC_USDT', 'limit' => 5]],
            'recent_trades' => ['GET', '/api/v4/spot/trades',         ['currency_pair' => 'BTC_USDT', 'limit' => 5]],
            'candles'       => ['GET', '/api/v4/spot/candlesticks',   ['currency_pair' => 'BTC_USDT', 'interval' => '1h', 'limit' => 3]],
        ],
    ],

    'mexc' => [
        'name'        => 'MEXC',
        'base_url'    => 'https://api.mexc.com',
        'api_version' => 'v3',
        'docs_url'    => 'https://mxcdevelop.github.io/apidocs/spot_v3_en/',
        'endpoints'   => [
            'ping'          => ['GET', '/api/v3/ping',         []],
            'server_time'   => ['GET', '/api/v3/time',         []],
            'exchange_info' => ['GET', '/api/v3/exchangeInfo', ['symbol' => SYMBOL_SPOT]],
            'ticker'        => ['GET', '/api/v3/ticker/24hr',  ['symbol' => SYMBOL_SPOT]],
            'order_book'    => ['GET', '/api/v3/depth',        ['symbol' => SYMBOL_SPOT, 'limit' => 5]],
            'recent_trades' => ['GET', '/api/v3/trades',       ['symbol' => SYMBOL_SPOT, 'limit' => 5]],
            // MEXC não aceita '1h' — usa '60m'. Ver MexcConfig::INTERVAL_MAP.
            'candles'       => ['GET', '/api/v3/klines',       ['symbol' => SYMBOL_SPOT, 'interval' => '60m', 'limit' => 3]],
        ],
    ],

    'bitget' => [
        'name'        => 'Bitget',
        'base_url'    => 'https://api.bitget.com',
        'api_version' => 'v2',
        'docs_url'    => 'https://www.bitget.com/api-doc/spot/market/Get-Symbols',
        'endpoints'   => [
            'server_time'   => ['GET', '/api/v2/public/time',           []],
            'exchange_info' => ['GET', '/api/v2/spot/public/symbols',   ['symbol' => SYMBOL_SPOT]],
            'ticker'        => ['GET', '/api/v2/spot/market/tickers',   ['symbol' => SYMBOL_SPOT]],
            'order_book'    => ['GET', '/api/v2/spot/market/orderbook', ['symbol' => SYMBOL_SPOT, 'limit' => '5']],
            'recent_trades' => ['GET', '/api/v2/spot/market/fills',     ['symbol' => SYMBOL_SPOT, 'limit' => '5']],
            'candles'       => ['GET', '/api/v2/spot/market/candles',   ['symbol' => SYMBOL_SPOT, 'granularity' => '1h', 'limit' => '3']],
        ],
    ],

    'gemini' => [
        'name'        => 'Gemini',
        'base_url'    => 'https://api.gemini.com',
        'api_version' => 'v1/v2',
        'docs_url'    => 'https://docs.gemini.com/rest-api/',
        'endpoints'   => [
            'exchange_info' => ['GET', '/v1/symbols',            []],
            'ticker'        => ['GET', '/v2/ticker/btcusd',      []],
            'order_book'    => ['GET', '/v1/book/btcusd',        ['limit_bids' => 5, 'limit_asks' => 5]],
            'recent_trades' => ['GET', '/v1/trades/btcusd',      ['limit_trades' => 5]],
            'candles'       => ['GET', '/v2/candles/btcusd/1hr', []],
        ],
    ],

    'bitstamp' => [
        'name'        => 'Bitstamp',
        'base_url'    => 'https://www.bitstamp.net',
        'api_version' => 'v2',
        'docs_url'    => 'https://www.bitstamp.net/api/',
        'endpoints'   => [
            'ticker'        => ['GET', '/api/v2/ticker/btcusdt/',       []],
            'order_book'    => ['GET', '/api/v2/order_book/btcusdt/',   []],
            'recent_trades' => ['GET', '/api/v2/transactions/btcusdt/', ['time' => 'hour']],
            'candles'       => ['GET', '/api/v2/ohlc/btcusdt/',         ['step' => 3600, 'limit' => 3]],
        ],
    ],

    'bitfinex' => [
        'name'        => 'Bitfinex',
        'base_url'    => 'https://api-pub.bitfinex.com',
        'api_version' => 'v2',
        'docs_url'    => 'https://docs.bitfinex.com/reference',
        'endpoints'   => [
            'server_time'   => ['GET', '/v2/platform/status',               []],
            'ticker'        => ['GET', '/v2/ticker/tBTCUST',                []],
            'all_tickers'   => ['GET', '/v2/tickers',                       ['symbols' => 'tBTCUST,tETHUST']],
            'order_book'    => ['GET', '/v2/book/tBTCUST/P0',               []],
            'recent_trades' => ['GET', '/v2/trades/tBTCUST/hist',           ['limit' => 5]],
            'candles'       => ['GET', '/v2/candles/trade:1h:tBTCUST/hist', ['limit' => 3]],
        ],
    ],

];

// ─── Helper functions ─────────────────────────────────────────────────────────

function httpGet(string $url): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json', 'User-Agent: ExchangeHub-HealthCheck/1.0'],
    ]);

    $start    = microtime(true);
    $body     = curl_exec($ch);
    $latency  = (int)round((microtime(true) - $start) * 1000);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['ok' => false, 'code' => 0, 'latency_ms' => $latency, 'error' => $curlErr, 'body' => null];
    }

    $decoded = json_decode((string)$body, true);
    $ok      = $httpCode >= 200 && $httpCode < 300 && $decoded !== null;

    return [
        'ok'         => $ok,
        'code'       => $httpCode,
        'latency_ms' => $latency,
        'error'      => $ok ? null : "HTTP {$httpCode}" . ($decoded === null ? ' (JSON inválido)' : ''),
        'body'       => $decoded,
    ];
}

function validateKeys(array $data, array $keys): array
{
    $missing = array_filter($keys, fn($k) => !array_key_exists($k, $data));
    return $missing ? ['ok' => false, 'missing' => array_values($missing)] : ['ok' => true];
}

function validatePositional(array $data, int $minCount, string $label): array
{
    return count($data) >= $minCount
        ? ['ok' => true]
        : ['ok' => false, 'missing' => ["{$label} (got " . count($data) . ", need {$minCount})"]];
}

function validatePath(array $data, string $path): bool
{
    $parts   = explode('.', $path);
    $current = $data;
    foreach ($parts as $part) {
        if (is_numeric($part)) {
            if (!isset($current[(int)$part])) return false;
            $current = $current[(int)$part];
        } else {
            if (!is_array($current) || !array_key_exists($part, $current)) return false;
            $current = $current[$part];
        }
    }
    return true;
}

function buildUrl(string $base, string $path, array $params): string
{
    $url = rtrim($base, '/') . $path;
    return $params ? $url . '?' . http_build_query($params) : $url;
}

function latencyLabel(int $ms): string
{
    if ($ms < 300) return "🟢 {$ms}ms";
    if ($ms < 800) return "🟡 {$ms}ms";
    return "🔴 {$ms}ms";
}

function isDatacenterBlock(string $key, int $httpCode, array $blockedList): bool
{
    return isset($blockedList[$key]) && in_array($httpCode, $blockedList[$key]['codes'], true);
}

// ─── Execução ─────────────────────────────────────────────────────────────────

$results      = [];
$totalOk      = 0;
$totalFail    = 0;
$totalBlocked = 0;
$runAt        = gmdate('Y-m-d H:i:s') . ' UTC';

echo "\n";
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║    Exchange Hub — API Health Check + Schema Validation  ║\n";
echo "║    {$runAt}                       ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

foreach ($exchanges as $key => $exchange) {
    $isBlocked    = isset($datacenterBlocked[$key]);
    $exchangeSchemas = $schemas[$key] ?? [];

    echo "🔍 [{$exchange['name']}]  (API {$exchange['api_version']})" . ($isBlocked ? "  ⚠️  datacenter block esperado" : "") . "\n";

    $exchangeResult = [
        'name'               => $exchange['name'],
        'key'                => $key,
        'base_url'           => $exchange['base_url'],
        'api_version'        => $exchange['api_version'],
        'docs_url'           => $exchange['docs_url'],
        'checked_at'         => $runAt,
        'datacenter_blocked' => $isBlocked,
        'block_reason'       => $isBlocked ? $datacenterBlocked[$key]['reason'] : null,
        'endpoints'          => [],
        'summary'            => ['ok' => 0, 'fail' => 0, 'blocked' => 0, 'schema_fail' => 0],
        'healthy'            => true,
    ];

    foreach ($exchange['endpoints'] as $endpointName => [$method, $path, $params]) {
        $url    = buildUrl($exchange['base_url'], $path, $params);
        $result = httpGet($url);

        $blocked     = !$result['ok'] && isDatacenterBlock($key, $result['code'], $datacenterBlocked);
        $schemaCheck = null;
        $schemaFail  = false;

        // Só valida schema se HTTP passou
        if ($result['ok'] && isset($exchangeSchemas[$endpointName])) {
            $schemaCheck = $exchangeSchemas[$endpointName]($result['body']);
            $schemaFail  = !$schemaCheck['ok'];
        }

        // Determina status final
        if ($blocked) {
            $icon = '⚠️ ';
            $exchangeResult['summary']['blocked']++;
            $totalBlocked++;
        } elseif (!$result['ok']) {
            $icon = '❌';
            $exchangeResult['summary']['fail']++;
            $totalFail++;
        } elseif ($schemaFail) {
            $icon = '🔴';  // HTTP ok mas schema quebrado — o mais perigoso
            $exchangeResult['summary']['schema_fail']++;
            $exchangeResult['summary']['fail']++;
            $totalFail++;
        } else {
            $icon = '✅';
            $exchangeResult['summary']['ok']++;
            $totalOk++;
        }

        $lat = latencyLabel($result['latency_ms']);
        echo "   {$icon} {$endpointName}";
        echo str_repeat(' ', max(1, 22 - strlen($endpointName)));
        echo $lat;

        if ($blocked) {
            echo "  ← bloqueio datacenter";
        } elseif (!$result['ok']) {
            echo "  ← {$result['error']}";
        } elseif ($schemaFail) {
            $missing = implode(', ', $schemaCheck['missing'] ?? []);
            echo "  ← ⚠️  SCHEMA: campos ausentes: [{$missing}]";
        }
        echo "\n";

        $exchangeResult['endpoints'][$endpointName] = [
            'status'          => $blocked ? 'BLOCKED' : (!$result['ok'] ? 'HTTP_FAIL' : ($schemaFail ? 'SCHEMA_FAIL' : 'OK')),
            'ok'              => $result['ok'] && !$schemaFail,
            'blocked'         => $blocked,
            'schema_fail'     => $schemaFail,
            'schema_missing'  => $schemaFail ? ($schemaCheck['missing'] ?? []) : [],
            'url'             => $url,
            'http_code'       => $result['code'],
            'latency_ms'      => $result['latency_ms'],
            'error'           => $result['error'],
        ];
    }

    $exchangeResult['healthy'] = $exchangeResult['summary']['fail'] === 0;

    $realFail   = $exchangeResult['summary']['fail'];
    $schemaFails = $exchangeResult['summary']['schema_fail'];
    $blocked    = $exchangeResult['summary']['blocked'];
    $total      = $exchangeResult['summary']['ok'] + $realFail + $blocked;

    if ($isBlocked && $realFail === 0) {
        echo "   ⚠️  Todos os endpoints bloqueados por restrição geográfica (esperado em datacenter)\n\n";
    } elseif ($schemaFails > 0) {
        echo "   🔴 {$schemaFails} endpoint(s) com schema quebrado — API pode ter mudado!\n\n";
    } elseif ($realFail > 0) {
        echo "   ❌ {$realFail}/{$total} endpoint(s) com falha\n\n";
    } else {
        echo "   ✅ Tudo OK (HTTP + Schema)\n\n";
    }

    $results[$key] = $exchangeResult;
}

// ─── Sumário ──────────────────────────────────────────────────────────────────

$totalEndpoints  = $totalOk + $totalFail + $totalBlocked;
$failedExchanges = array_filter($results, fn($r) => !$r['healthy']);
$blockedOnly     = array_filter($results, fn($r) => $r['datacenter_blocked'] && $r['summary']['fail'] === 0);

echo "══════════════════════════════════════════════════════════\n";
echo "  RESULTADO GERAL\n";
echo "  Endpoints OK (HTTP + Schema): {$totalOk}/{$totalEndpoints}\n";
echo "  Bloqueados (datacenter):      {$totalBlocked}\n";
echo "  Falhas reais:                 {$totalFail}\n";
echo "  Exchanges saudáveis:          " . (count($results) - count($failedExchanges)) . '/' . count($results) . "\n";

if ($blockedOnly) {
    echo "\n  ⚠️  Bloqueios geográficos (não são bugs):\n";
    foreach ($blockedOnly as $ex) {
        echo "     • {$ex['name']} — {$ex['block_reason']}\n";
    }
}

if ($failedExchanges) {
    echo "\n  ❌ Exchanges com falhas:\n";
    foreach ($failedExchanges as $ex) {
        $schemaFails = $ex['summary']['schema_fail'] ?? 0;
        $httpFails   = $ex['summary']['fail'] - $schemaFails;
        if ($schemaFails > 0) echo "     • {$ex['name']} — {$schemaFails} campo(s) ausente(s) no schema 🔴\n";
        if ($httpFails > 0)   echo "     • {$ex['name']} — {$httpFails} endpoint(s) HTTP ❌\n";
    }
}

echo "══════════════════════════════════════════════════════════\n\n";

// ─── JSON ─────────────────────────────────────────────────────────────────────

$report = [
    'generated_at'    => $runAt,
    'total_endpoints' => $totalEndpoints,
    'total_ok'        => $totalOk,
    'total_blocked'   => $totalBlocked,
    'total_fail'      => $totalFail,
    'all_healthy'     => $totalFail === 0,
    'exchanges'       => $results,
];

file_put_contents($OUTPUT_JSON, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "📄 JSON  → {$OUTPUT_JSON}\n";

// ─── Markdown ─────────────────────────────────────────────────────────────────

$md  = "# 🩺 Exchange Hub — API Health Report\n\n";
$md .= "> Gerado em: **{$runAt}**\n\n";
$md .= "## Resumo\n\n";
$md .= "| Métrica | Valor |\n|---|---|\n";
$md .= "| Exchanges verificadas | " . count($results) . " |\n";
$md .= "| Endpoints testados | {$totalEndpoints} |\n";
$md .= "| ✅ OK (HTTP + Schema) | {$totalOk} |\n";
$md .= "| ⚠️ Bloqueados (datacenter) | {$totalBlocked} |\n";
$md .= "| ❌ Falhas reais | {$totalFail} |\n";
$md .= "| Status | " . ($totalFail === 0 ? '✅ Nenhuma falha real' : "❌ {$totalFail} falha(s)") . " |\n\n";

$md .= "## Resultado por Exchange\n\n";
$md .= "| Exchange | API | Docs | OK | Bloq. | HTTP ❌ | Schema 🔴 | Status |\n|---|---|---|---|---|---|---|---|\n";
foreach ($results as $ex) {
    $ok     = $ex['summary']['ok'];
    $bl     = $ex['summary']['blocked'];
    $fail   = $ex['summary']['fail'] - ($ex['summary']['schema_fail'] ?? 0);
    $schema = $ex['summary']['schema_fail'] ?? 0;
    $icon   = $ex['healthy'] ? ($bl > 0 ? '⚠️' : '✅') : ($schema > 0 ? '🔴' : '❌');
    $md    .= "| **{$ex['name']}** | `{$ex['api_version']}` | [docs]({$ex['docs_url']}) | {$ok} | {$bl} | {$fail} | {$schema} | {$icon} |\n";
}

$md .= "\n## Detalhe por Endpoint\n\n";
foreach ($results as $ex) {
    $tag = $ex['datacenter_blocked'] ? ' ⚠️' : '';
    $md .= "### {$ex['name']}{$tag}\n\n";
    if ($ex['block_reason']) $md .= "> ℹ️ {$ex['block_reason']}\n\n";
    $md .= "| Endpoint | HTTP | Schema | Latência | Detalhe |\n|---|---|---|---|---|\n";
    foreach ($ex['endpoints'] as $name => $ep) {
        $httpIcon   = $ep['blocked'] ? '⚠️' : ($ep['http_code'] >= 200 && $ep['http_code'] < 300 ? '✅' : '❌');
        $schemaIcon = $ep['blocked'] ? '—' : ($ep['schema_fail'] ? ('🔴 `' . implode(', ', $ep['schema_missing']) . '`') : ($ep['ok'] ? '✅' : '—'));
        $detail     = $ep['blocked'] ? 'Bloqueio geográfico' : ($ep['error'] ? "`{$ep['error']}`" : '—');
        $md        .= "| `{$name}` | {$httpIcon} | {$schemaIcon} | {$ep['latency_ms']}ms | {$detail} |\n";
    }
    $md .= "\n";
}

$md .= "---\n_Gerado automaticamente pelo [Exchange Hub](https://github.com/israel-nogueira/exchange-hub) Health Check_\n";

file_put_contents($OUTPUT_MD, $md);
echo "📄 MD    → {$OUTPUT_MD}\n\n";

// ─── Exit ─────────────────────────────────────────────────────────────────────

if ($totalFail > 0) {
    $failedNames = implode(', ', array_column($failedExchanges, 'name'));
    echo "FAILED_EXCHANGES={$failedNames}\n";
    exit(1);
}

exit(0);
