<?php

namespace IsraelNogueira\ExchangeHub\Storage;

use IsraelNogueira\ExchangeHub\Contracts\StorageInterface;
use IsraelNogueira\ExchangeHub\Exceptions\ExchangeException;

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

    public function read(string $key): mixed
    {
        $path = $this->path($key);
        if (!file_exists($path)) {
            return null;
        }
        return json_decode(file_get_contents($path), true);
    }

    public function write(string $key, mixed $data): void
    {
        $path = $this->path($key);
        $this->ensureDir(dirname($path));
        $this->atomicWrite($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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

    public function append(string $key, mixed $item): void
    {
        $data   = $this->read($key) ?? [];
        $data[] = $item;
        $this->write($key, $data);
    }

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

    public function removeById(string $key, string $id, string $field = 'id'): bool
    {
        $data   = $this->read($key) ?? [];
        $before = count($data);
        $data   = array_values(array_filter($data, fn($item) => ($item[$field] ?? null) !== $id));

        if (count($data) < $before) {
            $this->write($key, $data);
            return true;
        }
        return false;
    }

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

    public function filter(string $key, callable $fn): array
    {
        $data = $this->read($key) ?? [];
        return array_values(array_filter($data, $fn));
    }

    private function path(string $key): string
    {
        return $this->basePath . '/' . ltrim($key, '/') . '.json';
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function atomicWrite(string $path, string $content): void
    {
        $tmp = $path . '.tmp.' . uniqid();
        file_put_contents($tmp, $content, LOCK_EX);
        rename($tmp, $path);
    }
}
