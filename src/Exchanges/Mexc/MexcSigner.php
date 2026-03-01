<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Mexc;

class MexcSigner
{
    public function __construct(
        private string $apiKey,
        private string $apiSecret,
    ) {}

    public function getHeaders(): array
    {
        return [
            'X-MEXC-APIKEY' => $this->apiKey,
            'Content-Type'  => 'application/json',
        ];
    }

    /** Append timestamp + signature to params array */
    public function signParams(array $params): array
    {
        $params['timestamp'] = (int)(microtime(true) * 1000);
        $params['signature'] = hash_hmac('sha256', http_build_query($params), $this->apiSecret);
        return $params;
    }
}
