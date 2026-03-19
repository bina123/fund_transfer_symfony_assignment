<?php

declare(strict_types=1);

namespace App\Exception;

class AccountNotFoundException extends ApiException
{
    public function __construct(int $accountId)
    {
        parent::__construct(
            sprintf('Account with ID %d not found.', $accountId),
            404,
            'account_not_found',
        );
    }
}
