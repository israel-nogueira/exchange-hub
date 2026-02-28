<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Bybit;

/** Bybit v5: HMAC-SHA256. Signature = HMAC(timestamp+apiKey+recvWindow+queryString|body) */
class BybitSigner
{
    private int $recvWindow = 5000;

    public function __construct(
        private string $apiKey,
        private string $secret
    ) {}

    public function signGet(array $params): array
    {
        $ts  = $this->timestamp();
        $qs  = http_build_query($params);
        $sig = hash_hmac('sha256', $ts . $this->apiKey . $this->recvWindow . $qs, $this->secret);
        return ['headers' => $this->headers($ts, $sig), 'params' => $params];
    }

    public function signPost(array $body): array
    {
        $ts  = $this->timestamp();
        $str = json_encode($body);
        $sig = hash_hmac('sha256', $ts . $this->apiKey . $this->recvWindow . $str, $this->secret);
        return ['headers' => $this->headers($ts, $sig), 'body' => $body];
    }

    private function headers(string $ts, string $sig): array
    {
        return [
            'X-BAPI-API-KEY'    => $this->apiKey,
            'X-BAPI-TIMESTAMP'  => $ts,
            'X-BAPI-SIGN'       => $sig,
            'X-BAPI-RECV-WINDOW'=> (string)$this->recvWindow,
            'Content-Type'      => 'application/json',
        ];
    }

    private function timestamp(): string
    {
        return (string)(int)(microtime(true) * 1000);
    }
}
