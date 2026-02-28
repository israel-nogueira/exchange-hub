<?php
namespace Exchanges\Exchanges\Mexc;
/** Mexc: HMAC-SHA256 (similar Binance) */
class MexcSigner
{
    public function __construct(private string $apiKey, private string $secret ) {}

    public function sign(array $params): array {
        $params['timestamp'] = (string)(int)(microtime(true)*1000);
        $qs = http_build_query($params);
        $params['signature'] = hash_hmac('sha256', $qs, $this->secret);
        return $params;
    }
    public function getHeaders(): array { return ['X-MEXC-APIKEY'=>$this->apiKey,'Content-Type'=>'application/json']; }
}
