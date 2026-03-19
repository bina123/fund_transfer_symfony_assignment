<?php

declare(strict_types=1);

namespace App\Exception;

abstract class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly string $errorCode,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
