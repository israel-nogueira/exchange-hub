<?php
namespace Exchanges\Exchanges\Bitfinex;
/** Bitfinex: HMAC-SHA384 payload base64 */
class BitfinexSigner
{
    public function __construct(private string $apiKey, private string $secret ) {}

    public function getHeaders(string $path, array $body=[]): array {
        $nonce = (string)(int)(microtime(true)*1000000);
        $sig_payload = '/api'.$path.$nonce.json_encode($body);
        $sig  = hash_hmac('sha384',$sig_payload,$this->secret);
        return ['bfx-nonce'=>$nonce,'bfx-apikey'=>$this->apiKey,'bfx-signature'=>$sig,'Content-Type'=>'application/json'];
    }
}
