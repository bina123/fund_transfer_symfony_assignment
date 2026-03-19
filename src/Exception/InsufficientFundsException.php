<?php

declare(strict_types=1);

namespace App\Exception;

class InsufficientFundsException extends ApiException
{
    public function __construct(int $accountId)
    {
        parent::__construct(
            sprintf('Insufficient funds in account %d.', $accountId),
            422,
            'insufficient_funds',
        );
    }
}
