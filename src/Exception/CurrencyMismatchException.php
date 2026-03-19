<?php

declare(strict_types=1);

namespace App\Exception;

class CurrencyMismatchException extends ApiException
{
    public function __construct(string $expected, string $actual)
    {
        parent::__construct(
            sprintf('Currency mismatch: expected %s, got %s.', $expected, $actual),
            422,
            'currency_mismatch',
        );
    }
}
