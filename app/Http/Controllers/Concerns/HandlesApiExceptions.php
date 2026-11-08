<?php

namespace App\Http\Controllers\Concerns;

use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Throwable;

trait HandlesApiExceptions
{
    protected function respondToException(Throwable $exception): JsonResponse
    {
        if ($exception instanceof ModelNotFoundException) {
            return response()->json(['message' => 'Resource not found.'], 404);
        }

        if ($exception instanceof DomainException) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        report($exception);

        return response()->json(['message' => 'Unable to process the request.'], 500);
    }
}
