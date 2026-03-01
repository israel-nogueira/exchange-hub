<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Bitfinex;
class BitfinexSigner
{
    public function __construct(
        private string $apiKey,
        private string $apiSecret,
    ) {}
    /**
     * Bitfinex v2 authentication:
     * signature = HMAC-SHA384(/api/v2/auth/{endpoint}{nonce}{body})
     */
    public function getHeaders(string $path, string $body = ''): array
    {
        $nonce   = (string)(int)(microtime(true) * 1000000);
        $payload = '/api' . $path . $nonce . $body;
        $sig     = hash_hmac('sha384', $payload, $this->apiSecret);
        return [
            'bfx-nonce'     => $nonce,
            'bfx-apikey'    => $this->apiKey,
            'bfx-signature' => $sig,
            'Content-Type'  => 'application/json',
        ];
    }
}
