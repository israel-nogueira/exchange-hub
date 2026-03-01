# 🔁 Exchange Hub — PHP

Biblioteca PHP unificada para integração com múltiplas exchanges de criptomoedas.  
Você escreve o código uma vez e troca a exchange com uma linha.

A biblioteca abstrai completamente as diferenças de autenticação, formato de resposta, endpoints e comportamentos de cada exchange. Os retornos são sempre objetos DTO padronizados, independente da fonte.

A **FakeExchange** permite desenvolvimento e testes completos sem necessidade de nenhuma API key.

---

## 📦 Exchanges Suportadas

| Exchange | Status | Autenticação | Região |
|---|---|---|---|
| **FakeExchange** | ✅ Completa | — | — |
| **Binance** | ✅ Completa | HMAC-SHA256 | Global |
| **OKX** | ✅ Completa | HMAC-SHA256 + passphrase | Global |
| **Bybit** | ✅ Completa | HMAC-SHA256 | Dubai |
| **Kraken** | ✅ Completa | HMAC-SHA512 | EUA |
| **KuCoin** | ✅ Completa | HMAC-SHA256 + passphrase | Seychelles |
| **Coinbase Advanced** | ✅ Completa | HMAC-SHA256 | EUA |
| **Mercado Bitcoin** | ✅ Completa | OAuth2 Bearer | Brasil |
| **Gate.io** | ✅ Completa | HMAC-SHA512 | Cayman |
| **MEXC** | ✅ Completa | HMAC-SHA256 | Seychelles |
| **Bitget** | ✅ Completa | HMAC-SHA256 + passphrase | Seychelles |
| **Gemini** | ✅ Completa | HMAC-SHA384 (payload) | EUA |
| **Bitstamp** | ✅ Completa | HMAC-SHA256 | Luxemburgo |
| **Bitfinex** | ✅ Completa | HMAC-SHA384 | BVI |

---

## 🚀 Uso

### Configuração

```php
require 'vendor/autoload.php';

use IsraelNogueira\ExchangeHub\Core\ExchangeManager;

// FakeExchange — sem API, dados mockados, persistência em JSON local
$exchange = ExchangeManager::make('fake', [
    'data_path' => __DIR__ . '/src/Exchanges/Fake/data',
]);

// Binance
$exchange = ExchangeManager::make('binance', [
    'api_key'    => 'SUA_API_KEY',
    'api_secret' => 'SUA_API_SECRET',
]);

// OKX / KuCoin / Bitget — exigem passphrase
$exchange = ExchangeManager::make('okx', [
    'api_key'    => 'SUA_API_KEY',
    'api_secret' => 'SUA_API_SECRET',
    'passphrase' => 'SUA_PASSPHRASE',
]);

// Mercado Bitcoin — OAuth2
$exchange = ExchangeManager::make('mercadobitcoin', [
    'api_key'    => 'SUA_API_KEY',
    'api_secret' => 'SUA_API_SECRET',
]);

// Testnet (Binance / Bybit)
$exchange = ExchangeManager::make('binance', [
    'api_key'    => 'SUA_API_KEY',
    'api_secret' => 'SUA_API_SECRET',
    'testnet'    => true,
]);
```

### Operações

```php
// A interface é idêntica independente da exchange instanciada

// Market Data
$ticker  = $exchange->getTicker('BTCUSDT');
$book    = $exchange->getOrderBook('BTCUSDT', 20);
$candles = $exchange->getCandles('BTCUSDT', '1h', 100);

echo $ticker->price;        // 98500.00
echo $ticker->changePct24h; // +2.35

// Account
$balances = $exchange->getBalances();
$address  = $exchange->getDepositAddress('BTC', 'BEP20');
$withdraw = $exchange->withdraw('USDT', '0xEndereco...', 100.0);

// Trading
$order = $exchange->createOrder('BTCUSDT', 'BUY', 'LIMIT', 0.01, 95000.00);
$exchange->cancelOrder('BTCUSDT', $order->orderId);
$trades = $exchange->getMyTrades('BTCUSDT');

echo $order->status; // OPEN | FILLED | CANCELLED
```

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
    │                    │
    │              HTTP + Assinatura HMAC
    │
    ▼
BinanceNormalizer  →  TickerDTO / OrderDTO / BalanceDTO ...
    │
    ▼
Seu código recebe sempre o mesmo objeto, independente da exchange
```

---

## 🔧 Funcionalidades por Exchange

Legenda: ✅ implementado · ❌ não disponível na exchange

### 📊 Market Data

| Função | Fake | Binance | OKX | Bybit | Kraken | KuCoin | Coinbase | MB | Gate.io | MEXC | Bitget | Gemini | Bitstamp | Bitfinex |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `ping` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getServerTime` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getExchangeInfo` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getSymbols` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getTicker` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getTicker24h` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getAllTickers` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getOrderBook` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getRecentTrades` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getHistoricalTrades` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getCandles` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getAvgPrice` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

### 🔐 Account

| Função | Fake | Binance | OKX | Bybit | Kraken | KuCoin | Coinbase | MB | Gate.io | MEXC | Bitget | Gemini | Bitstamp | Bitfinex |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `getAccountInfo` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getBalances` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getBalance` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getCommissionRates` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getDepositAddress` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getDepositHistory` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| `getWithdrawHistory` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `withdraw` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

### 📦 Trading

| Função | Fake | Binance | OKX | Bybit | Kraken | KuCoin | Coinbase | MB | Gate.io | MEXC | Bitget | Gemini | Bitstamp | Bitfinex |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `createOrder` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `cancelOrder` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `cancelAllOrders` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getOrder` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getOpenOrders` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getOrderHistory` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `getMyTrades` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `editOrder` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `createOCOOrder` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

> **Nota `editOrder`:** Binance, KuCoin, Gate.io, MEXC, Gemini, Bitstamp e Mercado Bitcoin não têm edição nativa — a implementação cancela e recria automaticamente. OKX, Bybit e Bitget têm amend nativo. Bitfinex tem update nativo.

### 🏦 Staking / Earn

| Função | Fake | Binance | OKX | Bybit | Kraken | KuCoin | Coinbase | MB | Gate.io | MEXC | Bitget | Gemini | Bitstamp | Bitfinex |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `stakeAsset` | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `unstakeAsset` | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `getStakingPositions` | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

---

## 🎭 FakeExchange — Comportamentos Simulados

| Comportamento | Descrição |
|---|---|
| **Preço dinâmico** | Variação aleatória de ±0.5% a cada chamada de `getTicker` |
| **Spread simulado** | Bid/Ask com spread entre 0.05% e 0.15% |
| **Ordem Market** | Executa imediatamente ao preço corrente |
| **Ordem Limit** | Fica em aberto e executa quando o preço cruza o limite |
| **Ordem Stop** | Ativa ao cruzar o stop price, executa como Limit |
| **OCO** | Duas ordens vinculadas — limit e stop-limit |
| **Saldo** | Debitado/creditado em tempo real a cada trade |
| **Lock de saldo** | Reserva o valor ao criar ordem, libera ao cancelar |
| **Taxas** | Aplicadas em todo trade — maker/taker configurável (padrão 0.1%) |
| **Candles** | Gerados sinteticamente e persistidos em JSON |
| **Persistência** | Todo estado salvo em JSON local — sobrevive a restarts |
| **Logs** | Todas as operações registradas em `fake_exchange.log` |
| **Staking** | Lock de saldo, APY simulado de 5%, unstake disponível |

---

## 🗂️ Estrutura de Diretórios

```
src/
├── Contracts/              # Interfaces globais
├── Core/
│   ├── AbstractExchange.php    # Base HTTP, sign, helpers
│   └── ExchangeManager.php     # Factory — ::make('binance', $config)
├── DTOs/                   # Objetos de retorno padronizados
├── Enums/                  # OrderSide, OrderType, OrderStatus, etc.
├── Exceptions/
├── Http/                   # CurlHttpClient, ExchangeLogger
├── Storage/                # JsonStorage
├── Traits/                 # HasCandleCache, HasRateLimit, HasTestnet
└── Exchanges/
    ├── Fake/               # ✅ Engine completo de simulação
    ├── Binance/            # ✅ Config / Signer / Normalizer / Exchange
    ├── Okx/                # ✅
    ├── Bybit/              # ✅
    ├── Kraken/             # ✅
    ├── Kucoin/             # ✅
    ├── Coinbase/           # ✅
    ├── MercadoBitcoin/     # ✅
    ├── Gateio/             # ✅
    ├── Mexc/               # ✅
    ├── Bitget/             # ✅
    ├── Gemini/             # ✅
    ├── Bitstamp/           # ✅
    └── Bitfinex/           # ✅
```

---

## ⚙️ Instalação

```bash
composer install
```

Requer **PHP >= 8.1** e extensão `curl`.

---

## 📄 Licença

MIT
