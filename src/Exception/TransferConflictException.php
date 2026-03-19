<?php

declare(strict_types=1);

namespace App\Exception;

class TransferConflictException extends ApiException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(
            'Transfer conflict: the account was modified concurrently. Please retry.',
            409,
            'transfer_conflict',
            $previous,
        );
    }
}
