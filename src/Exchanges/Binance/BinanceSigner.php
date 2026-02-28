<?php

namespace Exchanges\Exchanges\Binance;

/**
 * Binance usa HMAC-SHA256.
 * Todos os parâmetros (query string + body) são concatenados e assinados.
 * A signature é adicionada como último parâmetro.
 */
class BinanceSigner
{
    public function __construct(
        private string $apiKey,
        private string $apiSecret
    ) {}

    /**
     * Assina array de parâmetros — retorna array com 'signature' adicionada.
     */
    public function signParams(array $params): array
    {
        $params['timestamp'] = $this->timestamp();
        $queryString         = http_build_query($params);
        $params['signature'] = hash_hmac('sha256', $queryString, $this->apiSecret);
        return $params;
    }

    /**
     * Assina request com body JSON — assinatura vai na query string.
     */
    public function signWithBody(array $queryParams, array $body): array
    {
        $queryParams['timestamp'] = $this->timestamp();
        $queryString  = http_build_query($queryParams);
        $bodyString   = !empty($body) ? json_encode($body) : '';
        $payload      = $queryString . $bodyString;
        $queryParams['signature'] = hash_hmac('sha256', $payload, $this->apiSecret);
        return [$queryParams, $body];
    }

    public function getHeaders(): array
    {
        return [
            'X-MBX-APIKEY' => $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    private function timestamp(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}
