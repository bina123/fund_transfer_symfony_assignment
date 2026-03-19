<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\DTO\AccountResponse;
use App\Exception\AccountNotFoundException;
use App\Repository\AccountRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1')]
class AccountController extends AbstractController
{
    public function __construct(
        private readonly AccountRepository $accountRepository,
    ) {
    }

    #[Route('/accounts/{id}', name: 'api_v1_account_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $account = $this->accountRepository->find($id);

        if ($account === null) {
            throw new AccountNotFoundException($id);
        }

        return $this->json(new AccountResponse($account));
    }
}
