# 🏦 PHP Exchange Integration

Uma biblioteca PHP unificada para integração com as principais exchanges de criptomoedas do mundo. Independente de qual exchange você use — Binance, Kraken, Mercado Bitcoin ou uma FakeExchange para testes — a interface é sempre a mesma. Você escreve o código uma vez e troca a exchange com uma linha.

A biblioteca abstrai completamente as diferenças de autenticação, formato de resposta, endpoints e comportamentos específicos de cada exchange. Cada exchange é um módulo isolado com seus próprios arquivos de configuração, normalização, assinatura e logs. Os retornos são sempre objetos DTO padronizados, independente da fonte.

A **FakeExchange** permite desenvolvimento e testes completos sem necessidade de nenhuma API key, com preços dinâmicos simulados, execução automática de ordens e persistência total em JSON local.

---

## 📦 Exchanges Suportadas

| Exchange | Suporte | Tipo | Região |
|---|---|---|---|
| **FakeExchange** | ✅ Implementada | Mock / Testes | — |
| **Binance** | 🔜 Em breve | Spot + Futures + Margin | Global |
| **Coinbase Advanced** | 🔜 Em breve | Spot + Futures | EUA |
| **OKX** | 🔜 Em breve | Spot + Futures + Options | Global |
| **Bybit** | 🔜 Em breve | Spot + Futures + Options | Dubai |
| **Kraken** | 🔜 Em breve | Spot + Margin | EUA |
| **KuCoin** | 🔜 Em breve | Spot + Futures + Margin | Seychelles |
| **Gate.io** | 🔜 Em breve | Spot + Futures + Options | Cayman |
| **Bitfinex** | 🔜 Em breve | Spot + Margin + Funding | BVI |
| **Mercado Bitcoin** | 🔜 Em breve | Spot + BRL | Brasil |
| **MEXC** | 🔜 Em breve | Spot + Futures | Seychelles |
| **Bitget** | 🔜 Em breve | Spot + Futures + Copy | Seychelles |
| **Gemini** | 🔜 Em breve | Spot + OTC | EUA |
| **Bitstamp** | 🔜 Em breve | Spot + EUR/USD | Luxemburgo |

---

## 🗂️ Estrutura de Diretórios

```
src/
├── Contracts/              # Interfaces globais
│   ├── ExchangeInterface.php
│   └── StorageInterface.php
├── Core/
│   ├── AbstractExchange.php    # Base HTTP, sign, helpers
│   └── ExchangeManager.php     # Factory — ::make('binance', $config)
├── DTOs/                   # Objetos de retorno padronizados
│   ├── TickerDTO.php
│   ├── OrderBookDTO.php
│   ├── OrderDTO.php
│   ├── TradeDTO.php
│   ├── BalanceDTO.php
│   ├── CandleDTO.php
│   ├── DepositDTO.php
│   ├── WithdrawDTO.php
│   └── ExchangeInfoDTO.php
├── Exceptions/             # Erros tipados
│   └── ExchangeException.php
├── Storage/
│   └── JsonStorage.php     # Leitura/escrita atômica de JSON
├── Traits/                 # (futuro) HasSpotTrading, HasMarketData...
├── Http/                   # (futuro) HttpClient, RequestSigner
└── Exchanges/
    ├── Fake/               # ✅ Implementada
    │   ├── FakeExchange.php
    │   ├── FakeConfig.php
    │   ├── FakeNormalizer.php
    │   ├── FakePriceEngine.php
    │   ├── FakeOrderMatcher.php
    │   ├── FakeLogs.php
    │   └── data/
    │       ├── market/     symbols.json, tickers.json, trades.json, candles/
    │       ├── account/    balances.json, deposit_history.json, withdraw_history.json
    │       └── trading/    open_orders.json, order_history.json, my_trades.json
    ├── Binance/            # Exchange.php, Config.php, Normalizer.php, Signer.php, Logs.php
    ├── Coinbase/
    ├── Okx/
    ├── Bybit/
    ├── Kraken/
    ├── Kucoin/
    ├── Gateio/
    ├── Bitfinex/
    ├── MercadoBitcoin/
    ├── Mexc/
    ├── Bitget/
    ├── Gemini/
    └── Bitstamp/
```

---

## ⚙️ Instalação

```bash
composer install
```

Requer **PHP >= 8.1**.

---

## 🚀 Uso

### Configuração

```php
require 'vendor/autoload.php';

use Exchanges\Core\ExchangeManager;

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

echo $balances['BTC']->free;   // 1.5
echo $address->address;        // 0xFake...
echo $withdraw->status;        // CONFIRMED

// Trading
$order = $exchange->createOrder('BTCUSDT', 'BUY', 'LIMIT', 0.01, 95000.00);
$exchange->cancelOrder('BTCUSDT', $order->orderId);
$trades = $exchange->getMyTrades('BTCUSDT');

echo $order->orderId; // ORD-abc123...
echo $order->status;  // OPEN | FILLED | CANCELLED
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

### 📊 Market Data

| Função | Fake | Binance | Coinbase | OKX | Bybit | Kraken | KuCoin | Gate.io | Bitfinex | MB |
|---|---|---|---|---|---|---|---|---|---|---|
| `ping` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `getServerTime` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `getExchangeInfo` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `getSymbols` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `getTicker` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `getTicker24h` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `getAllTickers` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `getOrderBook` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `getRecentTrades` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `getHistoricalTrades` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `getCandles` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `getAvgPrice` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |

### 🔐 Account

| Função | Fake | Binance | Coinbase | OKX | Bybit | Kraken | KuCoin | Gate.io | Bitfinex | MB |
|---|---|---|---|---|---|---|---|---|---|---|
| `getAccountInfo` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `getBalances` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `getBalance` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `getCommissionRates` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `getDepositAddress` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `getDepositHistory` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `getWithdrawHistory` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `withdraw` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |

### 📦 Trading

| Função | Fake | Binance | Coinbase | OKX | Bybit | Kraken | KuCoin | Gate.io | Bitfinex | MB |
|---|---|---|---|---|---|---|---|---|---|---|
| `createOrder` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `cancelOrder` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `cancelAllOrders` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `getOrder` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `getOpenOrders` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `getOrderHistory` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `getMyTrades` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `editOrder` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |
| `createOCOOrder` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 |

### 🏦 Staking & Earn

| Função | Fake | Binance | Coinbase | OKX | Bybit | Kraken | KuCoin | Gate.io | Bitfinex | MB |
|---|---|---|---|---|---|---|---|---|---|---|
| `stakeAsset` | ✅ | 🔜 | ❌ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | ❌ |
| `unstakeAsset` | ✅ | 🔜 | ❌ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | ❌ |
| `getStakingPositions` | ✅ | 🔜 | ❌ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | ❌ |
| `subscribeEarn` | ❌ | 🔜 | ❌ | 🔜 | 🔜 | ❌ | 🔜 | 🔜 | ❌ | ❌ |
| `redeemEarn` | ❌ | 🔜 | ❌ | 🔜 | 🔜 | ❌ | 🔜 | 🔜 | ❌ | ❌ |

### 📈 Margin & Futures

| Função | Fake | Binance | Coinbase | OKX | Bybit | Kraken | KuCoin | Gate.io | Bitfinex | MB |
|---|---|---|---|---|---|---|---|---|---|---|
| `getPositions` | ❌ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | ❌ |
| `setLeverage` | ❌ | 🔜 | ❌ | 🔜 | 🔜 | ❌ | 🔜 | 🔜 | ❌ | ❌ |
| `getFundingRate` | ❌ | 🔜 | 🔜 | 🔜 | 🔜 | ❌ | 🔜 | 🔜 | ❌ | ❌ |
| `borrowMargin` | ❌ | 🔜 | ❌ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | ❌ |
| `repayMargin` | ❌ | 🔜 | ❌ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | ❌ |
| `closePosition` | ❌ | 🔜 | 🔜 | 🔜 | 🔜 | ❌ | 🔜 | 🔜 | ❌ | ❌ |
| `setTradingStop` | ❌ | ❌ | ❌ | ❌ | 🔜 | ❌ | ❌ | ❌ | ❌ | ❌ |

### 👥 Sub-Contas & Transferências

| Função | Fake | Binance | Coinbase | OKX | Bybit | Kraken | KuCoin | Gate.io | Bitfinex | MB |
|---|---|---|---|---|---|---|---|---|---|---|
| `getSubAccounts` | ✅ | 🔜 | ❌ | 🔜 | 🔜 | ❌ | 🔜 | 🔜 | ❌ | ❌ |
| `internalTransfer` | ✅ | 🔜 | 🔜 | 🔜 | 🔜 | ❌ | 🔜 | 🔜 | ❌ | ❌ |
| `createSubAccount` | ❌ | 🔜 | ❌ | 🔜 | 🔜 | ❌ | 🔜 | 🔜 | ❌ | ❌ |

### 🔄 Convert & Swap

| Função | Fake | Binance | Coinbase | OKX | Bybit | Kraken | KuCoin | Gate.io | Bitfinex | MB |
|---|---|---|---|---|---|---|---|---|---|---|
| `convertDust` | ✅ | 🔜 | ❌ | 🔜 | ❌ | ❌ | ❌ | 🔜 | ❌ | ❌ |
| `createConvert` | ❌ | 🔜 | 🔜 | 🔜 | 🔜 | ❌ | 🔜 | 🔜 | ❌ | ❌ |
| `createFlashSwap` | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | 🔜 | ❌ | ❌ |

### 🤖 Ordens Avançadas & Bots

| Função | Fake | Binance | Coinbase | OKX | Bybit | Kraken | KuCoin | Gate.io | Bitfinex | MB |
|---|---|---|---|---|---|---|---|---|---|---|
| `createBatchOrders` | ❌ | ❌ | ❌ | 🔜 | 🔜 | 🔜 | 🔜 | 🔜 | ❌ | ❌ |
| `createAlgoOrder` | ❌ | ❌ | ❌ | 🔜 | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `createGridStrategy` | ❌ | ❌ | ❌ | 🔜 | ❌ | ❌ | 🔜 | 🔜 | ❌ | ❌ |
| `createCopyTrade` | ❌ | ❌ | ❌ | ❌ | 🔜 | ❌ | ❌ | 🔜 | ❌ | ❌ |
| `cancelAllAfter` | ❌ | ❌ | ❌ | ❌ | ❌ | 🔜 | ❌ | ❌ | ❌ | ❌ |

---

## 🔐 Métodos de Assinatura por Exchange

| Exchange | Método | Observação |
|---|---|---|
| **FakeExchange** | — | Sem autenticação |
| **Binance** | HMAC-SHA256 | Timestamp + query string assinados |
| **Coinbase** | JWT (EC P-256) | Token gerado por request |
| **OKX** | HMAC-SHA256 | Passphrase obrigatória no header |
| **Bybit** | HMAC-SHA256 | Timestamp no header |
| **Kraken** | HMAC-SHA512 | Nonce único por request |
| **KuCoin** | HMAC-SHA256 | Passphrase obrigatória no header |
| **Bitfinex** | HMAC-SHA384 | Payload em base64 no header |
| **Mercado Bitcoin** | OAuth2 + JWT | Token com expiração renovável |
| **Gemini** | HMAC-SHA384 | Payload base64 no header |
| **Bitstamp** | HMAC-SHA256 | API Key no header X-Auth |
| **MEXC** | HMAC-SHA256 | Similar à Binance |
| **Bitget** | HMAC-SHA256 | Passphrase obrigatória no header |

---

## 🎭 FakeExchange — Comportamentos Simulados

| Comportamento | Descrição |
|---|---|
| **Preço dinâmico** | Variação aleatória de ±0.5% a cada chamada de `getTicker` |
| **Spread simulado** | Bid/Ask gerados com spread entre 0.05% e 0.15% |
| **Ordem Market** | Executa imediatamente ao preço corrente |
| **Ordem Limit** | Fica em aberto e executa quando o preço cruza o limite |
| **Ordem Stop** | Ativa ao cruzar o stop price, então executa como Limit |
| **OCO** | Executa uma perna e cancela a outra automaticamente |
| **Saldo** | Debitado/creditado em tempo real a cada trade executado |
| **Lock de saldo** | Reserva o valor ao criar ordem, libera ao cancelar |
| **Taxas** | Aplicadas em todo trade — maker/taker configurável (padrão 0.1%) |
| **Candles** | Gerados sinteticamente e persistidos em JSON por par/intervalo |
| **Persistência** | Todo estado salvo em JSON local — sobrevive a restarts |
| **Logs** | Todas as operações registradas em `fake_exchange.log` |

---

## 📄 Licença

MIT
