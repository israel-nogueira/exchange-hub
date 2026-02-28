<?php

namespace Exchanges\Core;

use Exchanges\Contracts\ExchangeInterface;
use Exchanges\Http\CurlHttpClient;
use Exchanges\Http\ExchangeLogger;

abstract class AbstractExchange implements ExchangeInterface
{
    protected string $name       = '';
    protected string $baseUrl    = '';
    protected string $apiKey     = '';
    protected string $apiSecret  = '';
    protected string $passphrase = '';
    protected bool   $testnet    = false;
    protected array  $options    = [];

    protected CurlHttpClient $http;
    protected ExchangeLogger  $logger;

    public function __construct(array $config = [])
    {
        $this->apiKey     = $config['api_key']    ?? '';
        $this->apiSecret  = $config['api_secret'] ?? '';
        $this->passphrase = $config['passphrase'] ?? '';
        $this->testnet    = $config['testnet']    ?? false;
        $this->options    = $config;

        $this->configure();

        $this->http = new CurlHttpClient([
            'timeout'     => $config['timeout']     ?? 10,
            'max_retries' => $config['max_retries'] ?? 3,
        ]);

        $logDir = $config['log_dir'] ?? sys_get_temp_dir() . '/exchange_logs';
        $this->logger = new ExchangeLogger($logDir, $this->name, $config['log'] ?? true);
    }

    /** Cada exchange define baseUrl, testnet URL etc. */
    abstract protected function configure(): void;

    // ─── HTTP helpers ────────────────────────────────────────────────────────

    protected function get(string $endpoint, array $params = [], bool $signed = false): array
    {
        $url = $this->buildUrl($endpoint, $signed ? $this->signParams($params) : $params);
        $this->logger->logRequest('GET', $url);
        $res = $this->http->get($url, $this->buildHeaders('GET', $endpoint, $params, [], $signed), $this->name);
        $this->logger->logResponse(200, $res);
        return $res;
    }

    protected function post(string $endpoint, array $body = [], array $params = [], bool $signed = true): array
    {
        [$signedParams, $signedBody] = $signed ? $this->signRequest('POST', $endpoint, $params, $body) : [$params, $body];
        $url = $this->buildUrl($endpoint, $signedParams);
        $this->logger->logRequest('POST', $url, $signedBody);
        $res = $this->http->post($url, $signedBody, $this->buildHeaders('POST', $endpoint, $signedParams, $signedBody, $signed), $this->name);
        $this->logger->logResponse(200, $res);
        return $res;
    }

    protected function delete(string $endpoint, array $params = [], bool $signed = true): array
    {
        $signed_params = $signed ? $this->signParams($params) : $params;
        $url = $this->buildUrl($endpoint, $signed_params);
        $this->logger->logRequest('DELETE', $url);
        $res = $this->http->delete($url, $this->buildHeaders('DELETE', $endpoint, $params, [], $signed), $this->name);
        $this->logger->logResponse(200, $res);
        return $res;
    }

    protected function put(string $endpoint, array $body = [], bool $signed = true): array
    {
        [$signedParams, $signedBody] = $signed ? $this->signRequest('PUT', $endpoint, [], $body) : [[], $body];
        $url = $this->buildUrl($endpoint, $signedParams);
        $this->logger->logRequest('PUT', $url, $signedBody);
        $res = $this->http->put($url, $signedBody, $this->buildHeaders('PUT', $endpoint, [], $signedBody, $signed), $this->name);
        $this->logger->logResponse(200, $res);
        return $res;
    }

    // ─── Assinatura e headers — override por exchange ────────────────────────

    protected function signParams(array $params): array          { return $params; }
    protected function signRequest(string $method, string $endpoint, array $params, array $body): array { return [$params, $body]; }
    protected function buildHeaders(string $method, string $endpoint, array $params, array $body, bool $signed): array { return ['Content-Type: application/json']; }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    protected function buildUrl(string $endpoint, array $params = []): string
    {
        $url = $this->baseUrl . $endpoint;
        if (!empty($params)) $url .= '?' . http_build_query($params);
        return $url;
    }

    protected function timestamp(): int   { return (int) round(microtime(true) * 1000); }
    protected function nonce(): string    { return (string) $this->timestamp(); }
    protected function generateId(): string { return strtoupper(substr($this->name, 0, 3)) . '-' . bin2hex(random_bytes(8)); }

    protected function hmac(string $data, string $secret, string $algo = 'sha256'): string
    {
        return hash_hmac($algo, $data, $secret);
    }

    protected function hmacBase64(string $data, string $secret, string $algo = 'sha256'): string
    {
        return base64_encode(hash_hmac($algo, $data, $secret, true));
    }

    protected function b64encode(string $data): string { return base64_encode($data); }
    protected function b64decode(string $data): string { return base64_decode($data); }

    protected function filterNulls(array $data): array
    {
        return array_filter($data, fn($v) => $v !== null);
    }

    public function getName(): string  { return $this->name; }
    public function isTestnet(): bool  { return $this->testnet; }
}
