<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Bitstamp;
class BitstampSigner
{
    public function __construct(
        private string $apiKey,
        private string $apiSecret,
    ) {}
    public function getHeaders(string $method, string $host, string $path, string $query, string $body, string $contentType = 'application/x-www-form-urlencoded'): array
    {
        $nonce     = uniqid('', true);
        $ts        = (string)(int)(microtime(true) * 1000);
        $msgParts  = ['BITSTAMP ' . $this->apiKey, strtoupper($method), $host, $path, $query, '', $ts, 'v2', $body];
        // Bitstamp uses nonce in HMAC
        $payload   = $nonce . $this->apiKey . $ts;
        $sig       = strtoupper(hash_hmac('sha256', $payload, $this->apiSecret));
        return [
            'X-Auth'            => 'BITSTAMP ' . $this->apiKey,
            'X-Auth-Signature'  => $sig,
            'X-Auth-Nonce'      => $nonce,
            'X-Auth-Timestamp'  => $ts,
            'X-Auth-Version'    => 'v2',
            'Content-Type'      => $contentType,
        ];
    }
}
