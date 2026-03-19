<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class TransferRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'From account ID is required.')]
        #[Assert\Positive(message: 'From account ID must be a positive integer.')]
        public readonly ?int $fromAccountId = null,

        #[Assert\NotBlank(message: 'To account ID is required.')]
        #[Assert\Positive(message: 'To account ID must be a positive integer.')]
        public readonly ?int $toAccountId = null,

        #[Assert\NotBlank(message: 'Amount is required.')]
        #[Assert\Positive(message: 'Amount must be a positive integer (in cents).')]
        public readonly ?int $amount = null,

        #[Assert\NotBlank(message: 'Currency is required.')]
        #[Assert\Length(exactly: 3, exactMessage: 'Currency must be a 3-letter ISO 4217 code.')]
        public readonly ?string $currency = null,

        #[Assert\NotBlank(message: 'Idempotency key is required.')]
        #[Assert\Length(max: 64, maxMessage: 'Idempotency key must be at most 64 characters.')]
        public readonly ?string $idempotencyKey = null,
    ) {
    }

    #[Assert\IsTrue(message: 'Source and destination accounts must be different.')]
    public function isNotSelfTransfer(): bool
    {
        if ($this->fromAccountId === null || $this->toAccountId === null) {
            return true; // Let NotBlank handle null values
        }

        return $this->fromAccountId !== $this->toAccountId;
    }
}
