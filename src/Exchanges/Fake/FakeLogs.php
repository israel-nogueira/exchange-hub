<?php
namespace IsraelNogueira\ExchangeHub\Exchanges\Fake;

class FakeLogs
{
    private string $logPath;

    public function __construct(string $dataPath)
    {
        $this->logPath = rtrim($dataPath, '/') . '/fake_exchange.log';
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
    }

    public function info(string $method, array $params = [], mixed $result = null): void
    {
        $this->write('INFO', $method, $params, $result);
    }

    public function error(string $method, string $error, array $params = []): void
    {
        $this->write('ERROR', $method, $params, ['error' => $error]);
    }

    private function write(string $level, string $method, array $params, mixed $result): void
    {
        $line = sprintf(
            "[%s] [%s] %s | params: %s | result: %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $method,
            json_encode($params),
            is_string($result) ? $result : json_encode($result)
        );
        file_put_contents($this->logPath, $line, FILE_APPEND | LOCK_EX);
    }
}
