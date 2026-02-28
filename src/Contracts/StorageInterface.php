<?php

namespace IsraelNogueira\ExchangeHub\Contracts;

interface StorageInterface
{
    public function read(string $key): mixed;

    public function write(string $key, mixed $data): void;

    public function exists(string $key): bool;

    public function delete(string $key): void;

    public function append(string $key, mixed $item): void;

    public function update(string $key, string $id, array $data): bool;
}
