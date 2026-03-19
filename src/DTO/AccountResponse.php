<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Account;

class AccountResponse
{
    public readonly int $id;
    public readonly string $currency;
    public readonly int $balance;
    public readonly string $balanceFormatted;
    public readonly string $createdAt;
    public readonly string $updatedAt;

    public function __construct(Account $account)
    {
        $this->id = $account->getId();
        $this->currency = $account->getCurrency();
        $this->balance = $account->getBalance();
        $this->balanceFormatted = number_format($account->getBalance() / 100, 2, '.', '');
        $this->createdAt = $account->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $account->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }
}
