<?php

namespace Exchanges\Storage;

use Exchanges\Contracts\StorageInterface;
use Exchanges\Exceptions\ExchangeException;

class JsonStorage implements StorageInterface
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }
    }

    // ─── Interface ───────────────────────────────────────────────────────────

    public function read(string $key): mixed
    {
        $path = $this->path($key);
        if (!file_exists($path)) {
            return null;
        }
        $content = file_get_contents($path);
        return json_decode($content, true);
    }

    public function write(string $key, mixed $data): void
    {
        $path = $this->path($key);
        $this->ensureDir(dirname($path));

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $this->atomicWrite($path, $json);
    }

    public function exists(string $key): bool
    {
        return file_exists($this->path($key));
    }

    public function delete(string $key): void
    {
        $path = $this->path($key);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /** Adiciona item ao array raiz do JSON */
    public function append(string $key, mixed $item): void
    {
        $data   = $this->read($key) ?? [];
        $data[] = $item;
        $this->write($key, $data);
    }

    /** Atualiza item por ID dentro do array */
    public function update(string $key, string $id, array $newData): bool
    {
        $data = $this->read($key) ?? [];
        foreach ($data as &$item) {
            if (isset($item['id']) && $item['id'] === $id) {
                $item = array_merge($item, $newData);
                $this->write($key, $data);
                return true;
            }
        }
        return false;
    }

    /** Remove item por ID do array */
    public function removeById(string $key, string $id, string $field = 'id'): bool
    {
        $data    = $this->read($key) ?? [];
        $before  = count($data);
        $data    = array_values(array_filter($data, fn($item) => ($item[$field] ?? null) !== $id));

        if (count($data) < $before) {
            $this->write($key, $data);
            return true;
        }
        return false;
    }

    /** Busca item por campo/valor */
    public function findOne(string $key, string $field, mixed $value): ?array
    {
        $data = $this->read($key) ?? [];
        foreach ($data as $item) {
            if (($item[$field] ?? null) === $value) {
                return $item;
            }
        }
        return null;
    }

    /** Filtra itens por campo/valor */
    public function filter(string $key, callable $fn): array
    {
        $data = $this->read($key) ?? [];
        return array_values(array_filter($data, $fn));
    }

    // ─── Internos ────────────────────────────────────────────────────────────

    private function path(string $key): string
    {
        // Transforma "market/tickers" em "/basePath/market/tickers.json"
        $key = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $key);
        return $this->basePath . DIRECTORY_SEPARATOR . ltrim($key, DIRECTORY_SEPARATOR) . '.json';
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /** Escrita atômica: escreve em temp e renomeia — evita corrupção */
    private function atomicWrite(string $path, string $content): void
    {
        $tmp = $path . '.tmp.' . getmypid();
        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            throw new ExchangeException("Falha ao escrever storage em {$path}", 'FakeExchange');
        }
        rename($tmp, $path);
    }
}
