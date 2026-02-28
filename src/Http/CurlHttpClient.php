<?php

namespace IsraelNogueira\ExchangeHub\Http;

use IsraelNogueira\ExchangeHub\Exceptions\NetworkException;
use IsraelNogueira\ExchangeHub\Exceptions\RateLimitException;
use IsraelNogueira\ExchangeHub\Exceptions\AuthenticationException;
use IsraelNogueira\ExchangeHub\Exceptions\ExchangeException;

class CurlHttpClient
{
    private int   $timeout    = 10;
    private int   $maxRetries = 3;
    private float $retryDelay = 0.5;

    public function __construct(array $options = [])
    {
        $this->timeout    = $options['timeout']     ?? 10;
        $this->maxRetries = $options['max_retries'] ?? 3;
        $this->retryDelay = $options['retry_delay'] ?? 0.5;
    }

    public function get(string $url, array $headers = [], string $exchange = ''): array
    {
        return $this->request('GET', $url, null, $headers, $exchange);
    }

    public function post(string $url, mixed $body, array $headers = [], string $exchange = ''): array
    {
        return $this->request('POST', $url, $body, $headers, $exchange);
    }

    public function put(string $url, mixed $body, array $headers = [], string $exchange = ''): array
    {
        return $this->request('PUT', $url, $body, $headers, $exchange);
    }

    public function delete(string $url, array $headers = [], string $exchange = ''): array
    {
        return $this->request('DELETE', $url, null, $headers, $exchange);
    }

    public function request(string $method, string $url, mixed $body, array $headers, string $exchange): array
    {
        $attempt = 0;

        while ($attempt < $this->maxRetries) {
            $attempt++;
            try {
                return $this->execute($method, $url, $body, $headers, $exchange);
            } catch (RateLimitException $e) {
                throw $e;
            } catch (NetworkException $e) {
                if ($attempt >= $this->maxRetries) {
                    throw $e;
                }
                usleep((int) ($this->retryDelay * 1_000_000 * $attempt));
            }
        }

        throw new NetworkException("Máximo de tentativas atingido", $exchange);
    }

    private function execute(string $method, string $url, mixed $body, array $headers, string $exchange): array
    {
        $ch = curl_init();

        $bodyStr = null;
        if ($body !== null) {
            $bodyStr = is_string($body) ? $body : json_encode($body);
        }

        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = is_int($key) ? $value : "{$key}: {$value}";
        }
        if ($bodyStr !== null && !$this->hasHeader($headers, 'Content-Type')) {
            $curlHeaders[] = 'Content-Type: application/json';
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => $curlHeaders,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER         => true,
        ]);

        match ($method) {
            'POST'   => curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $bodyStr ?? '']),
            'PUT'    => curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => 'PUT', CURLOPT_POSTFIELDS => $bodyStr ?? '']),
            'DELETE' => curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE'),
            default  => null,
        };

        $raw        = curl_exec($ch);
        $errno      = curl_errno($ch);
        $error      = curl_error($ch);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new NetworkException("cURL erro #{$errno}: {$error}", $exchange);
        }

        $responseBody = substr($raw, $headerSize);
        $decoded      = json_decode($responseBody, true);

        $this->handleHttpError($httpCode, $decoded ?? [], $responseBody, $exchange);

        return $decoded ?? [];
    }

    private function handleHttpError(int $code, array $response, string $raw, string $exchange): void
    {
        match (true) {
            $code === 401 => throw new AuthenticationException('Credenciais inválidas ou expiradas', $exchange),
            $code === 403 => throw new AuthenticationException('Acesso negado — verifique permissões da API Key', $exchange),
            $code === 429 => throw new RateLimitException($exchange, (int) ($response['retryAfter'] ?? 0)),
            $code === 418 => throw new RateLimitException($exchange, 60),
            $code >= 500  => throw new NetworkException("Erro interno da exchange (HTTP {$code})", $exchange),
            $code >= 400  => throw new ExchangeException($this->extractError($response, $raw), $exchange, $code),
            default       => null,
        };
    }

    private function extractError(array $response, string $raw): string
    {
        return $response['msg'] ?? $response['message'] ?? $response['error'] ?? $response['code'] ?? substr($raw, 0, 200);
    }

    private function hasHeader(array $headers, string $name): bool
    {
        $name = strtolower($name);
        foreach ($headers as $key => $value) {
            if (is_string($key) && strtolower($key) === $name) {
                return true;
            }
            if (is_string($value) && str_starts_with(strtolower($value), $name . ':')) {
                return true;
            }
        }
        return false;
    }
}
