<?php
namespace Exchanges\Exchanges\Kraken;

/**
 * Kraken: HMAC-SHA512 com nonce único por request.
 * Assinatura = base64(HMAC-SHA512(uri_path + SHA256(nonce + body), base64decode(secret)))
 */
class KrakenSigner
{
    public function __construct(
        private string $apiKey,
        private string $apiSecret,
    ) {}

    public function sign(string $path, array $data): array
    {
        $nonce        = $this->nonce();
        $data['nonce']= $nonce;
        $postData     = http_build_query($data);
        $encoded      = $nonce . $postData;
        $sha256       = hash('sha256', $encoded, true);
        $hmac         = hash_hmac('sha512', $path . $sha256, base64_decode($this->apiSecret), true);
        $signature    = base64_encode($hmac);

        return ['data' => $data, 'headers' => [
            'API-Key'  => $this->apiKey,
            'API-Sign' => $signature,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]];
    }

    private function nonce(): string
    {
        return (string)(int)(microtime(true) * 1000000);
    }
}
