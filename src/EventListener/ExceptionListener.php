<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\ApiException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
class ExceptionListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $appEnv = 'prod',
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof ApiException) {
            $payload = [
                'error' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
            ];

            $this->logger->warning('API error: ' . $exception->getMessage(), [
                'error_code' => $exception->getErrorCode(),
                'status_code' => $exception->getStatusCode(),
            ]);

            $event->setResponse(new JsonResponse($payload, $exception->getStatusCode()));

            return;
        }

        $this->logger->error('Unhandled exception: ' . $exception->getMessage(), [
            'exception' => $exception::class,
            'trace' => $exception->getTraceAsString(),
        ]);

        $payload = [
            'error' => 'internal_error',
            'message' => 'An internal server error occurred.',
        ];

        if ($this->appEnv === 'dev' || $this->appEnv === 'test') {
            $payload['debug'] = [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ];
        }

        $event->setResponse(new JsonResponse($payload, 500));
    }
}
