<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TransferRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransferRepository::class)]
#[ORM\Table(name: 'transfers')]
#[ORM\UniqueConstraint(name: 'uniq_idempotency_key', columns: ['idempotency_key'])]
class Transfer
{
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $idempotencyKey;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Account $fromAccount;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Account $toAccount;

    /** @var int Amount in minor units (cents) */
    #[ORM\Column(type: Types::BIGINT)]
    private int $amount;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(length: 20)]
    private string $status;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $failureReason;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $idempotencyKey,
        Account $fromAccount,
        Account $toAccount,
        int $amount,
        string $currency,
        string $status = self::STATUS_COMPLETED,
        ?string $failureReason = null,
    ) {
        $this->idempotencyKey = $idempotencyKey;
        $this->fromAccount = $fromAccount;
        $this->toAccount = $toAccount;
        $this->amount = $amount;
        $this->currency = strtoupper($currency);
        $this->status = $status;
        $this->failureReason = $failureReason;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getFromAccount(): Account
    {
        return $this->fromAccount;
    }

    public function getToAccount(): Account
    {
        return $this->toAccount;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
