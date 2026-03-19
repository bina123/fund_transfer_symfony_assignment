<?php

declare(strict_types=1);

namespace App\Exception;

class RateLimitExceededException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            'Rate limit exceeded. Please try again later.',
            429,
            'rate_limit_exceeded',
        );
    }
}
