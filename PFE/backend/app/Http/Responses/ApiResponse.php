<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(mixed $data = null, array $meta = []): JsonResponse
    {
        $body = [];
        if ($data !== null) {
            $body['data'] = $data;
        }
        if (! empty($meta)) {
            $body['meta'] = $meta;
        }
        return response()->json($body);
    }

    protected function created(mixed $data = null): JsonResponse
    {
        $body = $data !== null ? ['data' => $data] : [];
        return response()->json($body, 201);
    }

    protected function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $body = ['message' => $message];
        if (! empty($errors)) {
            $body['errors'] = $errors;
        }
        return response()->json($body, $status);
    }

    protected function validationErrors(array $errors): JsonResponse
    {
        return response()->json(['message' => 'Erreur de validation.', 'errors' => $errors], 422);
    }
}
