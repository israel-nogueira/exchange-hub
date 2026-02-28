<?php
namespace Exchanges\Exchanges\Bitget;
/** Bitget: HMAC-SHA256 + passphrase */
class BitgetSigner
{
    public function __construct(private string $apiKey, private string $secret, private string $passphrase = "") {}

    public function getHeaders(string $method, string $path, string $body=''): array {
        $ts  = (string)(int)(microtime(true)*1000);
        $pre = $ts.$method.$path.$body;
        $sig = base64_encode(hash_hmac('sha256',$pre,$this->secret,true));
        $pp  = base64_encode(hash_hmac('sha256',$this->passphrase,$this->secret,true));
        return ['ACCESS-KEY'=>$this->apiKey,'ACCESS-SIGN'=>$sig,'ACCESS-TIMESTAMP'=>$ts,'ACCESS-PASSPHRASE'=>$pp,'Content-Type'=>'application/json'];
    }
}
