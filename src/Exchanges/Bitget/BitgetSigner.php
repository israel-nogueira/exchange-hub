<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Bitget;
class BitgetSigner
{
    public function __construct(
        private string $apiKey,
        private string $apiSecret,
        private string $passphrase,
    ) {}
    public function getHeaders(string $method, string $path, string $body = ''): array
    {
        $ts  = (string)(int)(microtime(true) * 1000);
        $msg = $ts . strtoupper($method) . $path . $body;
        $sig = base64_encode(hash_hmac('sha256', $msg, $this->apiSecret, true));
        $pp  = base64_encode(hash_hmac('sha256', $this->passphrase, $this->apiSecret, true));
        return [
            'ACCESS-KEY'        => $this->apiKey,
            'ACCESS-SIGN'       => $sig,
            'ACCESS-PASSPHRASE' => $pp,
            'ACCESS-TIMESTAMP'  => $ts,
            'Content-Type'      => 'application/json',
            'locale'            => 'en-US',
        ];
    }
}
