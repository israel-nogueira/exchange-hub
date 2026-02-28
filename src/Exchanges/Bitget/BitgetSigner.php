<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Bitget;

class BitgetSigner
{
    public function __construct(
        private string $apiKey,
        private string $apiSecret
    ) {}

    public function getHeaders(string $method, string $path, string $body = ''): array
    {
        $ts  = (string)(int)(microtime(true) * 1000);
        $str = $ts . $method . $path . $body;
        $sig = hash_hmac('sha256', $str, $this->apiSecret);
        return [
            'X-API-KEY'   => $this->apiKey,
            'X-SIGNATURE' => $sig,
            'X-TIMESTAMP' => $ts,
            'Content-Type'=> 'application/json',
        ];
    }
}
