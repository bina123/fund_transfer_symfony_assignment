<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\TransferRequest;
use App\Entity\Account;
use App\Entity\Transfer;
use App\Exception\AccountNotFoundException;
use App\Exception\CurrencyMismatchException;
use App\Exception\InsufficientFundsException;
use App\Exception\TransferConflictException;
use App\Repository\AccountRepository;
use App\Repository\TransferRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Psr\Log\LoggerInterface;

class TransferService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AccountRepository $accountRepository,
        private readonly TransferRepository $transferRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(TransferRequest $request): Transfer
    {
        // 1. Idempotency: return existing transfer if key was already used
        $existing = $this->transferRepository->findByIdempotencyKey($request->idempotencyKey);
        if ($existing !== null) {
            $this->logger->info('Returning existing transfer for idempotency key.', [
                'idempotency_key' => $request->idempotencyKey,
                'transfer_id' => $existing->getId(),
            ]);

            return $existing;
        }

        // 2. Load accounts — order by ID to prevent deadlocks
        $fromAccount = $this->accountRepository->find($request->fromAccountId);
        if ($fromAccount === null) {
            throw new AccountNotFoundException($request->fromAccountId);
        }

        $toAccount = $this->accountRepository->find($request->toAccountId);
        if ($toAccount === null) {
            throw new AccountNotFoundException($request->toAccountId);
        }

        // 3. Currency validation
        $currency = strtoupper($request->currency);
        if ($fromAccount->getCurrency() !== $currency) {
            throw new CurrencyMismatchException($currency, $fromAccount->getCurrency());
        }
        if ($toAccount->getCurrency() !== $currency) {
            throw new CurrencyMismatchException($currency, $toAccount->getCurrency());
        }

        // 4. Sufficient funds check
        if ($fromAccount->getBalance() < $request->amount) {
            throw new InsufficientFundsException($request->fromAccountId);
        }

        // 5. Execute transfer with optimistic locking
        try {
            $fromAccount->debit($request->amount);
            $toAccount->credit($request->amount);

            $transfer = new Transfer(
                idempotencyKey: $request->idempotencyKey,
                fromAccount: $fromAccount,
                toAccount: $toAccount,
                amount: $request->amount,
                currency: $currency,
            );

            $this->entityManager->persist($transfer);

            // Lock check — Doctrine will verify versions on flush
            $this->entityManager->lock($fromAccount, LockMode::OPTIMISTIC, $fromAccount->getVersion());
            $this->entityManager->lock($toAccount, LockMode::OPTIMISTIC, $toAccount->getVersion());

            $this->entityManager->flush();

            $this->logger->info('Transfer completed successfully.', [
                'transfer_id' => $transfer->getId(),
                'from_account' => $request->fromAccountId,
                'to_account' => $request->toAccountId,
                'amount' => $request->amount,
                'currency' => $currency,
            ]);

            return $transfer;
        } catch (OptimisticLockException $e) {
            $this->logger->warning('Optimistic lock conflict during transfer.', [
                'from_account' => $request->fromAccountId,
                'to_account' => $request->toAccountId,
                'amount' => $request->amount,
            ]);

            throw new TransferConflictException($e);
        }
    }
}
