<?php

namespace Azuriom\Plugin\SkinSystem\Support;

final readonly class SyncResult
{
    public const STALE = 'stale';

    public function __construct(
        public string $status,
        public ?string $error = null,
    ) {}
}
