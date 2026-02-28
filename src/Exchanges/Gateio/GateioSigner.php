<?php
namespace Exchanges\Exchanges\Gateio;
/** Gateio: HMAC-SHA512 */
class GateioSigner
{
    public function __construct(private string $apiKey, private string $secret ) {}

    public function getHeaders(string $method, string $path, string $query='', string $body=''): array {
        $ts      = (string)time();
        $bodyHash= hash('sha512',$body);
        $pre     = $method."\n".$path."\n".$query."\n".$bodyHash."\n".$ts;
        $sig     = hash_hmac('sha512',$pre,$this->secret);
        return ['KEY'=>$this->apiKey,'Timestamp'=>$ts,'SIGN'=>$sig,'Content-Type'=>'application/json'];
    }
}
