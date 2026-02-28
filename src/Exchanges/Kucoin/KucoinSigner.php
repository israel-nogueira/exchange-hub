<?php
namespace Exchanges\Exchanges\Kucoin;
/** KuCoin: HMAC-SHA256 + passphrase (a passphrase também é assinada com HMAC-SHA256 se v2) */
class KucoinSigner
{
    public function __construct(private string $apiKey, private string $secret, private string $passphrase) {}

    public function getHeaders(string $method, string $path, string $body=''): array
    {
        $ts  = (string)(int)(microtime(true)*1000);
        $str = $ts.$method.$path.$body;
        $sig = base64_encode(hash_hmac('sha256',$str,$this->secret,true));
        $pp  = base64_encode(hash_hmac('sha256',$this->passphrase,$this->secret,true));
        return ['KC-API-KEY'=>$this->apiKey,'KC-API-SIGN'=>$sig,'KC-API-TIMESTAMP'=>$ts,'KC-API-PASSPHRASE'=>$pp,'KC-API-KEY-VERSION'=>'2','Content-Type'=>'application/json'];
    }
}
