<?php

namespace IsraelNogueira\ExchangeHub\Core;

use IsraelNogueira\ExchangeHub\Contracts\ExchangeInterface;
use IsraelNogueira\ExchangeHub\Exceptions\ExchangeException;

class ExchangeManager
{
    /** @var array<string, class-string<ExchangeInterface>> */
    private static array $registry = [
        'fake'           => \IsraelNogueira\ExchangeHub\Exchanges\Fake\FakeExchange::class,
        'binance'        => \IsraelNogueira\ExchangeHub\Exchanges\Binance\BinanceExchange::class,
        'coinbase'       => \IsraelNogueira\ExchangeHub\Exchanges\Coinbase\CoinbaseExchange::class,
        'okx'            => \IsraelNogueira\ExchangeHub\Exchanges\Okx\OkxExchange::class,
        'bybit'          => \IsraelNogueira\ExchangeHub\Exchanges\Bybit\BybitExchange::class,
        'kraken'         => \IsraelNogueira\ExchangeHub\Exchanges\Kraken\KrakenExchange::class,
        'kucoin'         => \IsraelNogueira\ExchangeHub\Exchanges\Kucoin\KucoinExchange::class,
        'gateio'         => \IsraelNogueira\ExchangeHub\Exchanges\Gateio\GateioExchange::class,
        'bitfinex'       => \IsraelNogueira\ExchangeHub\Exchanges\Bitfinex\BitfinexExchange::class,
        'mercadobitcoin' => \IsraelNogueira\ExchangeHub\Exchanges\MercadoBitcoin\MercadoBitcoinExchange::class,
        'mexc'           => \IsraelNogueira\ExchangeHub\Exchanges\Mexc\MexcExchange::class,
        'bitget'         => \IsraelNogueira\ExchangeHub\Exchanges\Bitget\BitgetExchange::class,
        'gemini'         => \IsraelNogueira\ExchangeHub\Exchanges\Gemini\GeminiExchange::class,
        'bitstamp'       => \IsraelNogueira\ExchangeHub\Exchanges\Bitstamp\BitstampExchange::class,
    ];

    /** @var array<string, ExchangeInterface> */
    private static array $instances = [];

    /**
     * Cria ou retorna instância de uma exchange.
     *
     * @example
     *   $exchange = ExchangeManager::make('fake');
     *   $exchange = ExchangeManager::make('binance', ['api_key' => '...', 'api_secret' => '...']);
     */
    public static function make(string $name, array $config = [], bool $singleton = true): ExchangeInterface
    {
        $name = strtolower(trim($name));
        $key  = $name . '_' . md5(serialize($config));

        if ($singleton && isset(self::$instances[$key])) {
            return self::$instances[$key];
        }

        if (!isset(self::$registry[$name])) {
            throw new ExchangeException(
                "Exchange '{$name}' não encontrada. Disponíveis: " . implode(', ', self::available()),
                $name
            );
        }

        $class    = self::$registry[$name];
        $instance = new $class($config);

        if ($singleton) {
            self::$instances[$key] = $instance;
        }

        return $instance;
    }

    /** Lista exchanges registradas */
    public static function available(): array
    {
        return array_keys(self::$registry);
    }

    /** Registra exchange customizada em runtime */
    public static function register(string $name, string $class): void
    {
        if (!is_subclass_of($class, ExchangeInterface::class)) {
            throw new ExchangeException("Classe {$class} deve implementar ExchangeInterface", $name);
        }
        self::$registry[strtolower($name)] = $class;
    }

    /** Remove exchange do registry */
    public static function unregister(string $name): void
    {
        unset(self::$registry[strtolower($name)]);
    }

    /** Limpa instâncias em cache (útil para testes) */
    public static function flush(): void
    {
        self::$instances = [];
    }
}
