<?php

namespace App\Domain\Subscription\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlanLimitReachedException extends Exception
{
    protected string $errorCode = 'PLAN_LIMIT_REACHED';

    protected string $entity;

    public function __construct(string $entity = 'entitas', ?string $message = null)
    {
        $this->entity = $entity;
        $defaultMessage = 'Batas paket Anda sudah tercapai. Upgrade paket untuk menambah.';

        parent::__construct($message ?? $defaultMessage, 403);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return new JsonResponse([
                'error' => [
                    'code' => $this->errorCode,
                    'message' => $this->getMessage(),
                    'entity' => $this->entity,
                ],
            ], 403);
        }

        abort(403, $this->getMessage());
    }
}
