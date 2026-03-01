# 🔁 Exchange Hub — PHP

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue)](https://www.php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Status](https://img.shields.io/badge/status-active-success)]()
[![Type Safe](https://img.shields.io/badge/type--safe-100%25-brightgreen)]()

Biblioteca PHP unificada para integração com múltiplas exchanges de criptomoedas.  
Você escreve o código uma vez e troca a exchange com uma linha.

A biblioteca abstrai completamente as diferenças de autenticação, formato de resposta, endpoints e comportamentos de cada exchange. Os retornos são sempre objetos DTO padronizados, independente da fonte.

A **FakeExchange** permite desenvolvimento e testes completos **sem necessidade de nenhuma API key**.

---

## 📦 Exchanges Suportadas

| Exchange | Chave no Manager | Status | Autenticação | Região |
|---|---|---|---|---|
| **FakeExchange** | `fake` | ✅ Completa | — | — |
| **Binance** | `binance` | ✅ Completa | HMAC-SHA256 | Global |
| **OKX** | `okx` | ✅ Completa | HMAC-SHA256 + passphrase | Global |
| **Bybit** | `bybit` | ✅ Completa | HMAC-SHA256 | Dubai |
| **Kraken** | `kraken` | ✅ Completa | HMAC-SHA512 | EUA |
| **KuCoin** | `kucoin` | ✅ Completa | HMAC-SHA256 + passphrase | Seychelles |
| **Coinbase Advanced** | `coinbase` | ✅ Completa | HMAC-SHA256 | EUA |
| **Mercado Bitcoin** | `mercadobitcoin` | ✅ Completa | OAuth2 Bearer | Brasil |
| **Gate.io** | `gateio` | ✅ Completa | HMAC-SHA512 | Cayman |
| **MEXC** | `mexc` | ✅ Completa | HMAC-SHA256 | Seychelles |
| **Bitget** | `bitget` | ✅ Completa | HMAC-SHA256 + passphrase | Seychelles |
| **Gemini** | `gemini` | ✅ Completa | HMAC-SHA384 (payload) | EUA |
| **Bitstamp** | `bitstamp` | ✅ Completa | HMAC-SHA256 | Luxemburgo |
| **Bitfinex** | `bitfinex` | ✅ Completa | HMAC-SHA384 | BVI |

---

## 🚀 Instalação

```bash
composer require israel-nogueira/exchange-hub
```

**Requisitos:**
- PHP >= 8.1
- Extensões: `ext-curl`, `ext-json`

---

## ⚙️ Configuração

```php
require 'vendor/autoload.php';

use IsraelNogueira\ExchangeHub\Core\ExchangeManager;

// ─── FakeExchange — sem API, dados mockados, persistência em JSON local ───────
$exchange = ExchangeManager::make('fake', [
    'data_path' => __DIR__ . '/src/Exchanges/Fake/data',
]);

// ─── Binance ──────────────────────────────────────────────────────────────────
$exchange = ExchangeManager::make('binance', [
    'api_key'    => 'SUA_API_KEY',
    'api_secret' => 'SUA_API_SECRET',
]);

// ─── OKX / KuCoin / Bitget — exigem passphrase ───────────────────────────────
$exchange = ExchangeManager::make('okx', [
    'api_key'    => 'SUA_API_KEY',
    'api_secret' => 'SUA_API_SECRET',
    'passphrase' => 'SUA_PASSPHRASE',
]);

// ─── Mercado Bitcoin — OAuth2 ─────────────────────────────────────────────────
$exchange = ExchangeManager::make('mercadobitcoin', [
    'api_key'    => 'SUA_API_KEY',
    'api_secret' => 'SUA_API_SECRET',
]);

// ─── Testnet (suportado: Binance, Bybit, OKX) ────────────────────────────────
$exchange = ExchangeManager::make('binance', [
    'api_key'    => 'SUA_API_KEY',
    'api_secret' => 'SUA_API_SECRET',
    'testnet'    => true,
]);

// ─── OKX Demo Trading ────────────────────────────────────────────────────────
$exchange = ExchangeManager::make('okx', [
    'api_key'    => 'SUA_API_KEY',
    'api_secret' => 'SUA_API_SECRET',
    'passphrase' => 'SUA_PASSPHRASE',
    'demo'       => true,
]);
```

---

## 📖 Operações

A interface é **idêntica** independente da exchange instanciada.

### Market Data

```php
// Ping / Status
$online = $exchange->ping();            // bool
$time   = $exchange->getServerTime();   // int (timestamp ms)

// Symbols
$symbols = $exchange->getSymbols();     // string[]
$info    = $exchange->getExchangeInfo(); // ExchangeInfoDTO

// Ticker
$ticker  = $exchange->getTicker('BTCUSDT');
$ticker  = $exchange->getTicker24h('BTCUSDT');
$tickers = $exchange->getAllTickers();

echo $ticker->price;        // 98500.00
echo $ticker->bid;          // 98490.00
echo $ticker->ask;          // 98510.00
echo $ticker->changePct24h; // +2.35
echo $ticker->volume24h;    // 123456.78

// Order Book
$book = $exchange->getOrderBook('BTCUSDT', 20);
echo $book->bestBid();      // float
echo $book->bestAsk();      // float
echo $book->spread();       // float

// Candles (OHLCV)
$candles = $exchange->getCandles('BTCUSDT', '1h', 100);
// Intervalos: 1m, 3m, 5m, 15m, 30m, 1h, 2h, 4h, 6h, 12h, 1d, 1w, 1M

// Trades
$recent     = $exchange->getRecentTrades('BTCUSDT', 50);
$historical = $exchange->getHistoricalTrades('BTCUSDT', 100);

// Preço médio
$avg = $exchange->getAvgPrice('BTCUSDT');  // float
```

### Trading

```php
// Criar ordem
$order = $exchange->createOrder(
    symbol:        'BTCUSDT',
    side:          'BUY',          // BUY | SELL
    type:          'LIMIT',        // MARKET | LIMIT | STOP_LOSS_LIMIT | STOP_LIMIT
    quantity:      0.01,
    price:         95000.00,       // null para MARKET
    stopPrice:     null,
    timeInForce:   'GTC',          // GTC | IOC | FOK
    clientOrderId: null,
);

echo $order->orderId;   // string
echo $order->status;    // OPEN | FILLED | CANCELLED | PARTIALLY_FILLED
echo $order->avgPrice;  // float

// Cancelar ordem
$cancelled = $exchange->cancelOrder('BTCUSDT', $order->orderId);

// Cancelar todas as ordens abertas de um símbolo
$exchange->cancelAllOrders('BTCUSDT');

// Consultar ordem
$order = $exchange->getOrder('BTCUSDT', $orderId);

// Editar ordem (cancela + recria)
$updated = $exchange->editOrder('BTCUSDT', $orderId, price: 94000.00);

// Ordens abertas
$open = $exchange->getOpenOrders('BTCUSDT');

// Histórico de ordens
$history = $exchange->getOrderHistory('BTCUSDT', limit: 100);

// Trades executados
$trades = $exchange->getMyTrades('BTCUSDT', limit: 100);

// Ordem OCO (One-Cancels-the-Other)
$oco = $exchange->createOCOOrder(
    symbol:         'BTCUSDT',
    side:           'SELL',
    quantity:       0.01,
    price:          100000.00,    // Limit
    stopPrice:      90000.00,     // Stop trigger
    stopLimitPrice: 89500.00,     // Stop limit
);
```

### Account

```php
// Saldos
$balances = $exchange->getBalances();                   // BalanceDTO[]
$balance  = $exchange->getBalance('USDT');              // BalanceDTO

echo $balance->free;    // float — disponível
echo $balance->locked;  // float — em ordens abertas
echo $balance->total;   // float — total

// Info da conta
$info = $exchange->getAccountInfo();                    // array

// Taxas
$rates = $exchange->getCommissionRates();               // array

// Depósito
$deposit = $exchange->getDepositAddress('BTC', 'BEP20'); // DepositDTO
$history = $exchange->getDepositHistory('USDT');

// Saque
$withdraw = $exchange->withdraw('USDT', '0xEndereco...', 100.0, 'BEP20');
$history  = $exchange->getWithdrawHistory('USDT');

// Staking (suportado: Binance, Kraken)
$staked   = $exchange->stakeAsset('ETH', 1.0);
$unstaked = $exchange->unstakeAsset('ETH', 1.0);
$positions = $exchange->getStakingPositions();
```

---

## 🧪 FakeExchange

Permite desenvolvimento e testes completos sem API keys. Os dados são persistidos em arquivos JSON locais e o motor de preços simula variações realistas de mercado.

```php
$exchange = ExchangeManager::make('fake', [
    'data_path' => __DIR__ . '/src/Exchanges/Fake/data',
]);

// Todas as operações funcionam normalmente:
// getTicker, getOrderBook, getCandles, getBalances,
// createOrder, cancelOrder, getMyTrades, withdraw...

// Ordens MARKET são executadas imediatamente
// Ordens LIMIT ficam abertas até cancelamento
// Balanços são debitados/creditados corretamente
// Histórico persiste entre execuções
```

**Pares disponíveis por padrão:**
`BTCUSDT`, `ETHUSDT`, `BNBUSDT`, `SOLUSDT`, `ADAUSDT`, `XRPUSDT`, `DOGEUSDT`, `DOTUSDT`, `MATICUSDT`, `LINKUSDT`, `LTCUSDT`, `UNIUSDT`, `ATOMUSDT`, `AVAXUSDT`, `BTCBRL`, `ETHBRL`

---

## 📐 Arquitetura

```
Seu código
    │
    ▼
ExchangeManager::make('binance', $config)
    │
    ▼
BinanceExchange  extends AbstractExchange  implements ExchangeInterface
    │                         │
    │           ┌─────────────┴─────────────┐
    │           │                           │
    │     HasTestnet trait           HttpClient (cURL)
    │
    ├── BinanceSigner     ← Assinatura HMAC-SHA256
    ├── BinanceNormalizer ← Converte resposta bruta → DTOs
    └── BinanceConfig     ← Endpoints e constantes
```

```
ExchangeInterface
    ├── MarketDataInterface
    │   ├── ping()
    │   ├── getServerTime()
    │   ├── getExchangeInfo()
    │   ├── getSymbols()
    │   ├── getTicker()
    │   ├── getTicker24h()
    │   ├── getAllTickers()
    │   ├── getOrderBook()
    │   ├── getRecentTrades()
    │   ├── getHistoricalTrades()
    │   ├── getCandles()
    │   └── getAvgPrice()
    │
    ├── TradingInterface
    │   ├── createOrder()
    │   ├── cancelOrder()
    │   ├── cancelAllOrders()
    │   ├── getOrder()
    │   ├── editOrder()
    │   ├── getOpenOrders()
    │   ├── getOrderHistory()
    │   ├── getMyTrades()
    │   └── createOCOOrder()
    │
    └── AccountInterface
        ├── getAccountInfo()
        ├── getBalances()
        ├── getBalance()
        ├── getCommissionRates()
        ├── getDepositAddress()
        ├── getDepositHistory()
        ├── getWithdrawHistory()
        ├── withdraw()
        ├── stakeAsset()
        ├── unstakeAsset()
        └── getStakingPositions()
```

---

## 📦 DTOs

Todos os retornos são objetos DTO tipados e padronizados:

| DTO | Campos principais |
|---|---|
| `TickerDTO` | `symbol`, `price`, `bid`, `ask`, `volume24h`, `changePct24h`, `high24h`, `low24h` |
| `OrderBookDTO` | `symbol`, `bids[]`, `asks[]`, `bestBid()`, `bestAsk()`, `spread()` |
| `CandleDTO` | `symbol`, `interval`, `open`, `high`, `low`, `close`, `volume`, `openTime`, `closeTime` |
| `TradeDTO` | `tradeId`, `orderId`, `symbol`, `side`, `price`, `quantity`, `quoteQty`, `fee`, `isMaker`, `time` |
| `OrderDTO` | `orderId`, `clientOrderId`, `symbol`, `side`, `type`, `status`, `quantity`, `executedQty`, `price`, `avgPrice`, `stopPrice`, `fee`, `createdAt` |
| `BalanceDTO` | `asset`, `free`, `locked`, `total` |
| `DepositDTO` | `asset`, `address`, `network`, `memo`, `tag` |
| `WithdrawDTO` | `withdrawId`, `asset`, `address`, `memo`, `network`, `amount`, `fee`, `status`, `txId` |
| `ExchangeInfoDTO` | `name`, `status`, `symbols[]`, `makerFee`, `takerFee` |

---

## 🔑 Autenticação por Exchange

| Exchange | Campos obrigatórios | Notas |
|---|---|---|
| `fake` | `data_path` (opcional) | Sem API key |
| `binance` | `api_key`, `api_secret` | Suporta `testnet: true` |
| `okx` | `api_key`, `api_secret`, `passphrase` | Suporta `demo: true` |
| `bybit` | `api_key`, `api_secret` | Suporta `testnet: true` |
| `kraken` | `api_key`, `api_secret` | — |
| `kucoin` | `api_key`, `api_secret`, `passphrase` | — |
| `coinbase` | `api_key`, `api_secret` | Coinbase Advanced Trade API v3 |
| `mercadobitcoin` | `api_key`, `api_secret` | OAuth2 automático |
| `gateio` | `api_key`, `api_secret` | — |
| `mexc` | `api_key`, `api_secret` | — |
| `bitget` | `api_key`, `api_secret`, `passphrase` | — |
| `gemini` | `api_key`, `api_secret` | HMAC-SHA384 via payload |
| `bitstamp` | `api_key`, `api_secret` | — |
| `bitfinex` | `api_key`, `api_secret` | HMAC-SHA384 via path |

---

## 🔄 Singleton e múltiplas instâncias

```php
// Por padrão, ExchangeManager retorna singleton por configuração
$a = ExchangeManager::make('binance', $config);
$b = ExchangeManager::make('binance', $config);
// $a === $b  (mesma instância)

// Para forçar nova instância:
$c = ExchangeManager::make('binance', $config, singleton: false);

// Múltiplas exchanges ao mesmo tempo
$binance  = ExchangeManager::make('binance',  ['api_key' => '...', 'api_secret' => '...']);
$bybit    = ExchangeManager::make('bybit',    ['api_key' => '...', 'api_secret' => '...']);
$fake     = ExchangeManager::make('fake');
```

---

## 🛠️ Tratamento de Erros

```php
use IsraelNogueira\ExchangeHub\Exceptions\ExchangeException;
use IsraelNogueira\ExchangeHub\Exceptions\OrderNotFoundException;
use IsraelNogueira\ExchangeHub\Exceptions\InvalidOrderException;
use IsraelNogueira\ExchangeHub\Exceptions\InsufficientBalanceException;
use IsraelNogueira\ExchangeHub\Exceptions\InvalidSymbolException;
use IsraelNogueira\ExchangeHub\Exceptions\WithdrawException;

try {
    $order = $exchange->createOrder('BTCUSDT', 'BUY', 'LIMIT', 0.01, 95000.00);
} catch (InsufficientBalanceException $e) {
    echo "Saldo insuficiente: " . $e->getMessage();
} catch (InvalidSymbolException $e) {
    echo "Par inválido: " . $e->getMessage();
} catch (InvalidOrderException $e) {
    echo "Parâmetros inválidos: " . $e->getMessage();
} catch (ExchangeException $e) {
    echo "Erro da exchange: " . $e->getMessage();
}

try {
    $order = $exchange->getOrder('BTCUSDT', 'ID_INEXISTENTE');
} catch (OrderNotFoundException $e) {
    echo "Ordem não encontrada: " . $e->getMessage();
}
```

---

## 🧪 Testes

```bash
# Rodar a suíte de testes
composer test

# Análise estática
composer analyse
```

A FakeExchange é projetada especificamente para testes. Todos os cenários de trading podem ser simulados sem necessidade de API ou conexão com internet.

---

## 📁 Estrutura do Projeto

```
src/
├── Contracts/
│   ├── ExchangeInterface.php     ← Contrato principal
│   ├── MarketDataInterface.php   ← Dados de mercado
│   ├── TradingInterface.php      ← Ordens e trades
│   ├── AccountInterface.php      ← Conta e saldos
│   └── StorageInterface.php      ← Persistência (FakeExchange)
│
├── Core/
│   ├── ExchangeManager.php       ← Factory / registry
│   └── AbstractExchange.php      ← Base com helpers HTTP
│
├── DTOs/
│   ├── TickerDTO.php
│   ├── OrderBookDTO.php
│   ├── CandleDTO.php
│   ├── OrderDTO.php
│   ├── TradeDTO.php
│   ├── BalanceDTO.php
│   ├── DepositDTO.php
│   ├── WithdrawDTO.php
│   └── ExchangeInfoDTO.php
│
├── Exceptions/
│   ├── ExchangeException.php
│   ├── OrderNotFoundException.php
│   ├── InvalidOrderException.php
│   ├── InsufficientBalanceException.php
│   ├── InvalidSymbolException.php
│   └── WithdrawException.php
│
├── Traits/
│   └── HasTestnet.php            ← Suporte a testnet/mainnet
│
└── Exchanges/
    ├── Fake/                     ← FakeExchange (testes)
    │   ├── FakeExchange.php
    │   ├── FakeConfig.php
    │   ├── FakePriceEngine.php
    │   ├── FakeOrderMatcher.php
    │   ├── FakeNormalizer.php
    │   ├── FakeLogs.php
    │   └── data/
    │       ├── market/
    │       │   ├── symbols.json
    │       │   └── prices.json
    │       └── trading/
    │           ├── balances.json
    │           └── open_orders.json
    │
    ├── Binance/                  ← BinanceExchange
    ├── Okx/                      ← OkxExchange
    ├── Bybit/                    ← BybitExchange
    ├── Kraken/                   ← KrakenExchange
    ├── Kucoin/                   ← KucoinExchange
    ├── Coinbase/                 ← CoinbaseExchange
    ├── MercadoBitcoin/           ← MercadoBitcoinExchange
    ├── Gateio/                   ← GateioExchange
    ├── Mexc/                     ← MexcExchange
    ├── Bitget/                   ← BitgetExchange
    ├── Gemini/                   ← GeminiExchange
    ├── Bitstamp/                 ← BitstampExchange
    └── Bitfinex/                 ← BitfinexExchange
```

Cada pasta de exchange contém:
- `{Name}Exchange.php` — Implementação completa
- `{Name}Config.php` — URLs e constantes de endpoints
- `{Name}Signer.php` — Lógica de autenticação/assinatura
- `{Name}Normalizer.php` — Conversão de respostas → DTOs

---

## 🤝 Contribuindo

1. Fork o repositório
2. Crie uma branch: `git checkout -b feature/nova-exchange`
3. Implemente seguindo o padrão existente (Config, Signer, Normalizer, Exchange)
4. Registre no `ExchangeManager::$registry`
5. Abra um Pull Request

---

## 📄 Licença

MIT — [Israel Nogueira](https://github.com/israel-nogueira)
