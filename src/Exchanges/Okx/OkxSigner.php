<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Okx;

/** OKX: HMAC-SHA256 + passphrase. Signature = base64(HMAC-SHA256(timestamp+METHOD+path+body)) */
class OkxSigner
{
    public function __construct(
        private string $apiKey,
        private string $secret,
        private string $passphrase
    ) {}

    public function getHeaders(string $method, string $path, string $body = '', bool $demo = false): array
    {
        $ts        = gmdate("Y-m-d\TH:i:s") . '.' . str_pad((string)((int)(microtime(true) * 1000) % 1000), 3, '0') . 'Z';
        $signature = base64_encode(hash_hmac('sha256', $ts . strtoupper($method) . $path . $body, $this->secret, true));
        $headers   = [
            'OK-ACCESS-KEY'       => $this->apiKey,
            'OK-ACCESS-SIGN'      => $signature,
            'OK-ACCESS-TIMESTAMP' => $ts,
            'OK-ACCESS-PASSPHRASE'=> $this->passphrase,
            'Content-Type'        => 'application/json',
        ];
        if ($demo) {
            $headers['x-simulated-trading'] = '1';
        }
        return $headers;
    }
}
