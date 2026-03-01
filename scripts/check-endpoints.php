<?php

/**
 * ══════════════════════════════════════════════════════════════════
 *  Exchange Hub — API Endpoint Health Check
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

// ─── Known issues: exchanges que bloqueiam IPs de datacenter ─────────────────
//
// Algumas exchanges bloqueiam requisições vindas de IPs de provedores cloud
// (AWS, Azure, GCP) por restrições geográficas ou de ToS.
// Estas exchanges são marcadas com 'datacenter_blocked' e seus erros HTTP
// esperados são tratados como AVISO (não como falha crítica no CI).
//
// HTTP 451 = "Unavailable For Legal Reasons" (Binance bloqueia IPs dos EUA)
// HTTP 403 = "Forbidden"  (Bybit bloqueia IPs de datacenters desde 2023)
//
// O relatório documenta o bloqueio mas NÃO abre Issue para essas exchanges,
// pois não se trata de bug na biblioteca.

$datacenterBlocked = [
    'binance'  => ['codes' => [451, 403], 'reason' => 'Bloqueia IPs de datacenter/EUA (HTTP 451). Teste localmente com VPN ou IP residencial.'],
    'bybit'    => ['codes' => [403],      'reason' => 'Bloqueia IPs de datacenter desde 2023 (HTTP 403). Teste localmente com IP residencial.'],
    'coinbase' => ['codes' => [401, 403], 'reason' => 'Coinbase Advanced Trade API exige autenticação mesmo em endpoints públicos.'],
];

// ─── Definição das exchanges e endpoints ─────────────────────────────────────

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
            'exchange_info' => ['GET', '/api/v3/brokerage/products',              ['product_type' => 'SPOT', 'limit' => 5]],
            'ticker'        => ['GET', '/api/v3/brokerage/products/BTC-USDT',     []],
            'order_book'    => ['GET', '/api/v3/brokerage/best_bid_ask',          ['product_ids' => 'BTC-USDT']],
            'candles'       => ['GET', '/api/v3/brokerage/products/BTC-USDT/candles', [
                'start'       => (string)(time() - 3600),
                'end'         => (string)time(),
                'granularity' => 'ONE_HOUR',
            ]],
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
            'candles'       => ['GET', '/api/v3/klines',       ['symbol' => SYMBOL_SPOT, 'interval' => '1h', 'limit' => 3]],
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

// ─── Funções utilitárias ──────────────────────────────────────────────────────

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
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'User-Agent: ExchangeHub-HealthCheck/1.0',
        ],
    ]);

    $start    = microtime(true);
    $body     = curl_exec($ch);
    $latency  = (int)round((microtime(true) - $start) * 1000);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['ok' => false, 'code' => 0, 'latency_ms' => $latency, 'error' => $curlErr];
    }

    $decoded = json_decode((string)$body, true);
    $ok      = $httpCode >= 200 && $httpCode < 300 && $decoded !== null;

    return [
        'ok'         => $ok,
        'code'       => $httpCode,
        'latency_ms' => $latency,
        'error'      => $ok ? null : "HTTP {$httpCode}" . ($decoded === null ? ' (JSON inválido)' : ''),
    ];
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

// Verifica se o código HTTP de uma falha é um bloqueio conhecido de datacenter
function isDatacenterBlock(string $key, int $httpCode, array $blockedList): bool
{
    if (!isset($blockedList[$key])) return false;
    return in_array($httpCode, $blockedList[$key]['codes'], true);
}

// ─── Execução ─────────────────────────────────────────────────────────────────

$results          = [];
$totalOk          = 0;
$totalFail        = 0;       // apenas falhas reais (não bloqueios de datacenter)
$totalBlocked     = 0;       // bloqueios conhecidos de datacenter
$runAt            = gmdate('Y-m-d H:i:s') . ' UTC';

echo "\n";
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║       Exchange Hub — API Endpoint Health Check          ║\n";
echo "║       {$runAt}                       ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

foreach ($exchanges as $key => $exchange) {
    $isBlocked = isset($datacenterBlocked[$key]);

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
        'summary'            => ['ok' => 0, 'fail' => 0, 'blocked' => 0],
        'healthy'            => true,
    ];

    foreach ($exchange['endpoints'] as $endpointName => [$method, $path, $params]) {
        $url    = buildUrl($exchange['base_url'], $path, $params);
        $result = httpGet($url);

        $blocked = !$result['ok'] && isDatacenterBlock($key, $result['code'], $datacenterBlocked);

        if ($result['ok']) {
            $icon = '✅';
            $exchangeResult['summary']['ok']++;
            $totalOk++;
        } elseif ($blocked) {
            $icon = '⚠️ ';
            $exchangeResult['summary']['blocked']++;
            $totalBlocked++;
        } else {
            $icon = '❌';
            $exchangeResult['summary']['fail']++;
            $totalFail++;
        }

        $lat = latencyLabel($result['latency_ms']);
        echo "   {$icon} {$endpointName}";
        echo str_repeat(' ', max(1, 22 - strlen($endpointName)));
        echo $lat;
        if (!$result['ok']) {
            $label = $blocked ? "(bloqueio datacenter)" : $result['error'];
            echo "  ← {$label}";
        }
        echo "\n";

        $exchangeResult['endpoints'][$endpointName] = [
            'status'     => $result['ok'] ? 'OK' : ($blocked ? 'BLOCKED' : 'FAIL'),
            'ok'         => $result['ok'],
            'blocked'    => $blocked,
            'url'        => $url,
            'http_code'  => $result['code'],
            'latency_ms' => $result['latency_ms'],
            'error'      => $result['error'],
        ];
    }

    // Exchange só é marcada como não saudável se tiver falhas REAIS (não bloqueios)
    $exchangeResult['healthy'] = $exchangeResult['summary']['fail'] === 0;

    $realFail = $exchangeResult['summary']['fail'];
    $blocked  = $exchangeResult['summary']['blocked'];
    $total    = $exchangeResult['summary']['ok'] + $realFail + $blocked;

    if ($isBlocked && $realFail === 0) {
        echo "   ⚠️  Todos os endpoints bloqueados por restrição geográfica (esperado em datacenter)\n\n";
    } elseif ($realFail > 0) {
        echo "   ❌ {$realFail}/{$total} endpoint(s) com falha real\n\n";
    } else {
        echo "   ✅ Tudo OK\n\n";
    }

    $results[$key] = $exchangeResult;
}

// ─── Sumário final ────────────────────────────────────────────────────────────

$totalEndpoints  = $totalOk + $totalFail + $totalBlocked;
$failedExchanges = array_filter($results, fn($r) => !$r['healthy']);
$blockedOnly     = array_filter($results, fn($r) => $r['datacenter_blocked'] && $r['summary']['fail'] === 0);

echo "══════════════════════════════════════════════════════════\n";
echo "  RESULTADO GERAL\n";
echo "  Endpoints OK:              {$totalOk}/{$totalEndpoints}\n";
echo "  Bloqueados (datacenter):   {$totalBlocked}\n";
echo "  Falhas reais:              {$totalFail}\n";
echo "  Exchanges saudáveis:       " . (count($results) - count($failedExchanges)) . '/' . count($results) . "\n";

if ($blockedOnly) {
    echo "\n  ⚠️  Bloqueios geográficos conhecidos (não são bugs):\n";
    foreach ($blockedOnly as $ex) {
        echo "     • {$ex['name']} — {$ex['block_reason']}\n";
    }
}

if ($failedExchanges) {
    echo "\n  ❌ Exchanges com falhas REAIS:\n";
    foreach ($failedExchanges as $ex) {
        echo "     • {$ex['name']} — {$ex['summary']['fail']} endpoint(s) falharam\n";
    }
}

echo "══════════════════════════════════════════════════════════\n\n";

// ─── Gerar relatório JSON ─────────────────────────────────────────────────────

$report = [
    'generated_at'     => $runAt,
    'total_endpoints'  => $totalEndpoints,
    'total_ok'         => $totalOk,
    'total_blocked'    => $totalBlocked,
    'total_fail'       => $totalFail,
    'all_healthy'      => $totalFail === 0,  // bloqueios não contam como falha
    'exchanges'        => $results,
];

file_put_contents($OUTPUT_JSON, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "📄 Relatório JSON → {$OUTPUT_JSON}\n";

// ─── Gerar relatório Markdown ─────────────────────────────────────────────────

$md  = "# 🩺 Exchange Hub — API Health Report\n\n";
$md .= "> Gerado em: **{$runAt}**\n\n";
$md .= "## Resumo\n\n";
$md .= "| Métrica | Valor |\n|---|---|\n";
$md .= "| Exchanges verificadas | " . count($results) . " |\n";
$md .= "| Endpoints testados | {$totalEndpoints} |\n";
$md .= "| ✅ Endpoints OK | {$totalOk} |\n";
$md .= "| ⚠️ Bloqueados por datacenter | {$totalBlocked} |\n";
$md .= "| ❌ Falhas reais | {$totalFail} |\n";
$md .= "| Status geral | " . ($totalFail === 0 ? '✅ Nenhuma falha real detectada' : "❌ {$totalFail} falha(s) real(is)") . " |\n\n";

if ($blockedOnly) {
    $md .= "> **ℹ️ Nota:** Algumas exchanges bloqueiam IPs de provedores cloud (AWS/GCP/Azure).\n";
    $md .= "> Isso é esperado e **não indica problemas na biblioteca**. Teste localmente para validar.\n\n";
}

$md .= "## Resultado por Exchange\n\n";
$md .= "| Exchange | API Version | Docs | OK | Bloqueados | Falhas | Status |\n|---|---|---|---|---|---|---|\n";

foreach ($results as $ex) {
    $ok      = $ex['summary']['ok'];
    $blocked = $ex['summary']['blocked'];
    $fail    = $ex['summary']['fail'];
    $icon    = $ex['healthy'] ? ($ex['datacenter_blocked'] && $blocked > 0 ? '⚠️' : '✅') : '❌';
    $md     .= "| **{$ex['name']}** | `{$ex['api_version']}` | [docs]({$ex['docs_url']}) | {$ok} | {$blocked} | {$fail} | {$icon} |\n";
}

$md .= "\n## Detalhe por Endpoint\n\n";

foreach ($results as $ex) {
    $tag = $ex['datacenter_blocked'] ? ' ⚠️ (bloqueio geográfico esperado)' : '';
    $md .= "### {$ex['name']}{$tag}\n\n";
    if ($ex['block_reason']) {
        $md .= "> ℹ️ {$ex['block_reason']}\n\n";
    }
    $md .= "| Endpoint | Status | Latência | Detalhe |\n|---|---|---|---|\n";
    foreach ($ex['endpoints'] as $name => $ep) {
        if ($ep['ok'])      $icon = '✅';
        elseif ($ep['blocked']) $icon = '⚠️';
        else                $icon = '❌';
        $detail = $ep['ok'] ? '—' : ($ep['blocked'] ? 'Bloqueio geográfico' : "`{$ep['error']}`");
        $md    .= "| `{$name}` | {$icon} | {$ep['latency_ms']}ms | {$detail} |\n";
    }
    $md .= "\n";
}

$md .= "---\n_Gerado automaticamente pelo [Exchange Hub](https://github.com/israel-nogueira/exchange-hub) Health Check_\n";

file_put_contents($OUTPUT_MD, $md);
echo "📄 Relatório Markdown → {$OUTPUT_MD}\n\n";

// ─── Exit code ────────────────────────────────────────────────────────────────
// Sai com 1 apenas se houver falhas REAIS.
// Bloqueios de datacenter (403/451 esperados) não são falhas da biblioteca.

if ($totalFail > 0) {
    $failedNames = implode(', ', array_column($failedExchanges, 'name'));
    echo "FAILED_EXCHANGES={$failedNames}\n";
    exit(1);
}

exit(0);
