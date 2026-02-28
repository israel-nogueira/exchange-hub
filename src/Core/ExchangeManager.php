<?php

namespace Exchanges\Core;

use Exchanges\Contracts\ExchangeInterface;
use Exchanges\Exceptions\ExchangeException;

class ExchangeManager
{
    /** @var array<string, class-string<ExchangeInterface>> */
    private static array $registry = [
        'fake'            => \Exchanges\Exchanges\Fake\FakeExchange::class,
        'binance'         => \Exchanges\Exchanges\Binance\BinanceExchange::class,
        'coinbase'        => \Exchanges\Exchanges\Coinbase\CoinbaseExchange::class,
        'okx'             => \Exchanges\Exchanges\Okx\OkxExchange::class,
        'bybit'           => \Exchanges\Exchanges\Bybit\BybitExchange::class,
        'kraken'          => \Exchanges\Exchanges\Kraken\KrakenExchange::class,
        'kucoin'          => \Exchanges\Exchanges\Kucoin\KucoinExchange::class,
        'gateio'          => \Exchanges\Exchanges\Gateio\GateioExchange::class,
        'bitfinex'        => \Exchanges\Exchanges\Bitfinex\BitfinexExchange::class,
        'mercadobitcoin'  => \Exchanges\Exchanges\MercadoBitcoin\MercadoBitcoinExchange::class,
        'mexc'            => \Exchanges\Exchanges\Mexc\MexcExchange::class,
        'bitget'          => \Exchanges\Exchanges\Bitget\BitgetExchange::class,
        'gemini'          => \Exchanges\Exchanges\Gemini\GeminiExchange::class,
        'bitstamp'        => \Exchanges\Exchanges\Bitstamp\BitstampExchange::class,
    ];

    /** @var array<string, ExchangeInterface> instâncias singleton por config */
    private static array $instances = [];

    /**
     * Cria ou retorna instância de uma exchange.
     *
     * Uso:
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

    /** Lista exchanges disponíveis */
    public static function available(): array
    {
        return array_keys(self::$registry);
    }

    /** Registra exchange customizada */
    public static function register(string $name, string $class): void
    {
        if (!is_subclass_of($class, ExchangeInterface::class)) {
            throw new ExchangeException("Classe {$class} deve implementar ExchangeInterface", $name);
        }
        self::$registry[strtolower($name)] = $class;
    }

    /** Limpa instâncias em cache (útil para testes) */
    public static function flush(): void
    {
        self::$instances = [];
    }
}
