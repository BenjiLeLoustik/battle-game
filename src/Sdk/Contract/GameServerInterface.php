<?php

namespace App\Sdk\Contract;

interface GameServerInterface
{
    public function handle(string $action, array $payload, string $player, array $state): array;

    public function view(array $state): array;

    public function secret(array $state, string $player): array;

    public function scoreboard(array $state): array;

    public function progress(array $state): ?array;

    public function isFinished(array $state): bool;

    public function results(array $state): array;
}