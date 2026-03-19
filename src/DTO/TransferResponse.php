<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Transfer;

class TransferResponse
{
    public readonly int $id;
    public readonly string $idempotencyKey;
    public readonly int $fromAccountId;
    public readonly int $toAccountId;
    public readonly int $amount;
    public readonly string $currency;
    public readonly string $status;
    public readonly ?string $failureReason;
    public readonly string $createdAt;

    public function __construct(Transfer $transfer)
    {
        $this->id = $transfer->getId();
        $this->idempotencyKey = $transfer->getIdempotencyKey();
        $this->fromAccountId = $transfer->getFromAccount()->getId();
        $this->toAccountId = $transfer->getToAccount()->getId();
        $this->amount = $transfer->getAmount();
        $this->currency = $transfer->getCurrency();
        $this->status = $transfer->getStatus();
        $this->failureReason = $transfer->getFailureReason();
        $this->createdAt = $transfer->getCreatedAt()->format(\DateTimeInterface::ATOM);
    }
}
