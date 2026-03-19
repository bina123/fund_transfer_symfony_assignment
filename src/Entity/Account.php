<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AccountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[ORM\Table(name: 'accounts')]
#[ORM\HasLifecycleCallbacks]
class Account
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 3)]
    private string $currency;

    /** @var int Balance stored in minor units (cents) */
    #[ORM\Column(type: Types::BIGINT)]
    private int $balance = 0;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $currency, int $balance = 0)
    {
        $this->currency = strtoupper($currency);
        $this->balance = $balance;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getBalance(): int
    {
        return $this->balance;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function debit(int $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Debit amount must be positive.');
        }

        if ($this->balance < $amount) {
            throw new \DomainException('Insufficient funds.');
        }

        $this->balance -= $amount;
    }

    public function credit(int $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive.');
        }

        $this->balance += $amount;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
