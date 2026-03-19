<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\DTO\TransferRequest;
use App\DTO\TransferResponse;
use App\Service\TransferService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1')]
class TransferController extends AbstractController
{
    public function __construct(
        private readonly TransferService $transferService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/transfers', name: 'api_v1_transfer_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'error' => 'invalid_json',
                'message' => 'Request body must be valid JSON.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $dto = new TransferRequest(
            fromAccountId: isset($data['from_account_id']) ? (int) $data['from_account_id'] : null,
            toAccountId: isset($data['to_account_id']) ? (int) $data['to_account_id'] : null,
            amount: isset($data['amount']) ? (int) $data['amount'] : null,
            currency: $data['currency'] ?? null,
            idempotencyKey: $data['idempotency_key'] ?? null,
        );

        $violations = $this->validator->validate($dto);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = [
                    'field' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                ];
            }

            return $this->json([
                'error' => 'validation_failed',
                'message' => 'Request validation failed.',
                'details' => $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        $transfer = $this->transferService->execute($dto);

        return $this->json(new TransferResponse($transfer), Response::HTTP_CREATED);
    }
}
