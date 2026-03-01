<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Gateio;

class GateioSigner
{
    public function __construct(
        private string $apiKey,
        private string $apiSecret,
    ) {}

    /**
     * Gate.io v4 signature:
     * sign = HMAC-SHA512(method + "\n" + path + "\n" + queryString + "\n" + SHA512(body) + "\n" + timestamp)
     */
    public function getHeaders(string $method, string $path, string $query = '', string $body = ''): array
    {
        $ts        = (string)(int)microtime(true);
        $bodyHash  = hash('sha512', $body);
        $payload   = implode("\n", [strtoupper($method), $path, $query, $bodyHash, $ts]);
        $sig       = hash_hmac('sha512', $payload, $this->apiSecret);

        return [
            'KEY'          => $this->apiKey,
            'SIGN'         => $sig,
            'Timestamp'    => $ts,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
    }
}
