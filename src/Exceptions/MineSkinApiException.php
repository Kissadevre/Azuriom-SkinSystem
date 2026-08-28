<?php

namespace Azuriom\Plugin\SkinSystem\Exceptions;

use RuntimeException;

class MineSkinApiException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly bool $retryable = false,
    ) {
        parent::__construct($reason);
    }
}
