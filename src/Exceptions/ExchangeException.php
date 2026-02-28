<?php

namespace IsraelNogueira\ExchangeHub\Exceptions;

// ─── Base ────────────────────────────────────────────────────────────────────

class ExchangeException extends \RuntimeException
{
    public function __construct(
        string          $message,
        public readonly string $exchange = '',
        int             $code = 0,
        ?\Throwable     $previous = null
    ) {
        parent::__construct("[{$exchange}] {$message}", $code, $previous);
    }
}

// ─── Específicas ─────────────────────────────────────────────────────────────

class InsufficientBalanceException extends ExchangeException
{
    public function __construct(string $asset, float $required, float $available, string $exchange = '')
    {
        parent::__construct(
            "Saldo insuficiente de {$asset}. Necessário: {$required}, Disponível: {$available}",
            $exchange
        );
    }
}

class InvalidSymbolException extends ExchangeException
{
    public function __construct(string $symbol, string $exchange = '')
    {
        parent::__construct("Par inválido ou não suportado: {$symbol}", $exchange);
    }
}

class OrderNotFoundException extends ExchangeException
{
    public function __construct(string $orderId, string $exchange = '')
    {
        parent::__construct("Ordem não encontrada: {$orderId}", $exchange);
    }
}

class AuthenticationException extends ExchangeException
{
    public function __construct(string $detail = '', string $exchange = '')
    {
        parent::__construct("Falha de autenticação. {$detail}", $exchange);
    }
}

class RateLimitException extends ExchangeException
{
    public function __construct(string $exchange = '', public readonly int $retryAfter = 0)
    {
        $msg = "Rate limit atingido" . ($retryAfter > 0 ? ". Tente novamente em {$retryAfter}s" : '');
        parent::__construct($msg, $exchange);
    }
}

class NetworkException extends ExchangeException
{
    public function __construct(string $detail = '', string $exchange = '', ?\Throwable $previous = null)
    {
        parent::__construct("Erro de rede. {$detail}", $exchange, 0, $previous);
    }
}

class InvalidOrderException extends ExchangeException
{
    public function __construct(string $detail = '', string $exchange = '')
    {
        parent::__construct("Parâmetros de ordem inválidos. {$detail}", $exchange);
    }
}

class WithdrawException extends ExchangeException
{
    public function __construct(string $detail = '', string $exchange = '')
    {
        parent::__construct("Erro ao processar saque. {$detail}", $exchange);
    }
}
