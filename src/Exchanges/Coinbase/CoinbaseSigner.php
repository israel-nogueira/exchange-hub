<?php
namespace Exchanges\Exchanges\Coinbase;
/** Coinbase Advanced: JWT via EC P-256 (ou HMAC legacy). Suporta ambos. */
class CoinbaseSigner
{
    public function __construct(private string $apiKey, private string $secret) {}
    public function getHeaders(string $method, string $path, string $body=''): array
    {
        $ts  = (string)time();
        $msg = $ts.$method.$path.$body;
        $sig = hash_hmac('sha256',$msg,$this->secret);
        return ['CB-ACCESS-KEY'=>$this->apiKey,'CB-ACCESS-SIGN'=>$sig,'CB-ACCESS-TIMESTAMP'=>$ts,'Content-Type'=>'application/json'];
    }
}
