<?php
namespace Exchanges\Exchanges\Bitstamp;
/** Bitstamp: HMAC-SHA256 X-Auth header */
class BitstampSigner
{
    public function __construct(private string $apiKey, private string $secret ) {}

    public function getHeaders(string $method, string $url, string $body=''): array {
        $ts    = (string)(int)(microtime(true)*1000);
        $nonce = uniqid('',true);
        $msg   = 'BITSTAMP '.$this->apiKey.$method.$url.$nonce.$ts.'v2'.$body;
        $sig   = strtoupper(hash_hmac('sha256',$msg,$this->secret));
        return ['X-Auth'=>'BITSTAMP '.$this->apiKey,'X-Auth-Signature'=>$sig,'X-Auth-Nonce'=>$nonce,'X-Auth-Timestamp'=>$ts,'X-Auth-Version'=>'v2','Content-Type'=>'application/x-www-form-urlencoded'];
    }
}
