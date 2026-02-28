<?php
namespace Exchanges\Exchanges\MercadoBitcoin;
/** MB: OAuth2 — busca token JWT com login e usa como Bearer */
class MercadoBitcoinSigner
{
    private ?string $token     = null;
    private int     $expiresAt = 0;

    public function __construct(private string $apiKey, private string $secret) {}

    public function getToken(): string
    {
        if ($this->token && time() < $this->expiresAt - 60) return $this->token;
        $url  = MercadoBitcoinConfig::AUTH_URL;
        $body = json_encode(['login'=>$this->apiKey,'password'=>$this->secret]);
        $ch   = curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>['Content-Type: application/json']]);
        $res  = json_decode(curl_exec($ch),true);
        curl_close($ch);
        $this->token     = $res['access_token'] ?? '';
        $this->expiresAt = time() + (int)($res['expiration'] ?? 3600);
        return $this->token;
    }

    public function getHeaders(): array
    {
        return ['Authorization'=>'Bearer '.$this->getToken(),'Content-Type'=>'application/json'];
    }
}
