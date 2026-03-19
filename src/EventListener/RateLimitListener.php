<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\RateLimitExceededException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class RateLimitListener
{
    public function __construct(
        private readonly RateLimiterFactory $transferApiLimiter,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->getPathInfo() !== '/api/v1/transfers' || $request->getMethod() !== 'POST') {
            return;
        }

        $limiter = $this->transferApiLimiter->create($request->getClientIp() ?? 'unknown');
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            throw new RateLimitExceededException();
        }
    }
}
