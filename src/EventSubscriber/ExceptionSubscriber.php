<?php

namespace App\EventSubscriber;

use App\Exception\AppException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscriber for handling exceptions and converting them to JSON responses.
 */
class ExceptionSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    /**
     * Handle exceptions and convert them to JSON responses.
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Only handle exceptions for API routes and specific endpoints
        $request = $event->getRequest();
        $pathInfo = $request->getPathInfo();
        if (!str_starts_with($pathInfo, '/api') && 
            !str_starts_with($pathInfo, '/register') && 
            !str_starts_with($pathInfo, '/me') && 
            !str_starts_with($pathInfo, '/login')) {
            return;
        }

        // Determine status code
        $statusCode = 500;
        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
        } elseif ($exception instanceof AppException) {
            $statusCode = $exception->getCode() ?: 500;
        }

        // Create response data
        if ($exception instanceof AppException) {
            $responseData = $exception->toResponseArray();
        } else {
            $responseData = [
                'error' => $exception->getMessage(),
            ];

            // In production, don't expose internal errors
            if ($statusCode >= 500 && $_ENV['APP_ENV'] === 'prod') {
                $responseData['error'] = 'Internal server error';
            }
        }

        // Create and set the response
        $response = new JsonResponse($responseData, $statusCode);
        $event->setResponse($response);
    }
}
