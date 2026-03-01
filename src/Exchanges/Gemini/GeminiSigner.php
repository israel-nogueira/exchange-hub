<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Gemini;
class GeminiSigner
{
    public function __construct(
        private string $apiKey,
        private string $apiSecret,
    ) {}
    public function getHeaders(string $endpoint, array $payload = []): array
    {
        $payload['request'] = $endpoint;
        $payload['nonce']   = (string)(int)(microtime(true) * 1000);
        $json      = json_encode($payload);
        $b64       = base64_encode($json);
        $sig       = hash_hmac('sha384', $b64, $this->apiSecret);
        return [
            'Content-Type'       => 'text/plain',
            'Content-Length'     => '0',
            'X-GEMINI-APIKEY'    => $this->apiKey,
            'X-GEMINI-PAYLOAD'   => $b64,
            'X-GEMINI-SIGNATURE' => $sig,
            'Cache-Control'      => 'no-cache',
        ];
    }
}
