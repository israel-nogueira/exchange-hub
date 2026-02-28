<?php
namespace Exchanges\Exchanges\Gemini;
/** Gemini: HMAC-SHA384 payload base64 */
class GeminiSigner
{
    public function __construct(private string $apiKey, private string $secret ) {}

    public function getHeaders(string $path, array $payload=[]): array {
        $payload['nonce'] = (string)(int)(microtime(true)*1000);
        $b64  = base64_encode(json_encode($payload));
        $sig  = hash_hmac('sha384',$b64,$this->secret);
        return ['X-GEMINI-APIKEY'=>$this->apiKey,'X-GEMINI-PAYLOAD'=>$b64,'X-GEMINI-SIGNATURE'=>$sig,'Content-Type'=>'text/plain'];
    }
}
