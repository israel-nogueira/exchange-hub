<?php

namespace IsraelNogueira\ExchangeHub\Http;

class ExchangeLogger
{
    private string $logFile;
    private bool   $enabled;
    private int    $maxFileSize = 10_485_760; // 10MB

    public function __construct(string $logDir, string $exchangeName, bool $enabled = true)
    {
        $this->enabled = $enabled;

        if ($enabled) {
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            $this->logFile = rtrim($logDir, '/') . "/{$exchangeName}.log";
        }
    }

    public function logRequest(string $method, string $url, mixed $body = null): void
    {
        if (!$this->enabled) {
            return;
        }
        $extra = $body ? ' | body: ' . json_encode($body) : '';
        $this->write('REQ', "{$method} {$url}{$extra}");
    }

    public function logResponse(int $httpCode, mixed $response): void
    {
        if (!$this->enabled) {
            return;
        }
        $this->write('RES', "HTTP {$httpCode} | " . json_encode($response));
    }

    public function logError(string $message): void
    {
        if (!$this->enabled) {
            return;
        }
        $this->write('ERR', $message);
    }

    public function info(string $method, array $params = [], mixed $result = null): void
    {
        if (!$this->enabled) {
            return;
        }
        $this->write('INF', "{$method} | params: " . json_encode($params) . " | result: " . json_encode($result));
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getLogFile(): string
    {
        return $this->logFile ?? '';
    }

    private function write(string $level, string $message): void
    {
        if (isset($this->logFile) && file_exists($this->logFile) && filesize($this->logFile) > $this->maxFileSize) {
            rename($this->logFile, $this->logFile . '.old');
        }

        $line = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $level, $message);
        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
