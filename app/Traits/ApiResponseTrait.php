<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    protected function successResponse(mixed $data,string $message="Operation Successful",int $status=200,array $meta =[]): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
        if(!empty($meta)){
            $response['meta'] = $meta;
        }
        return response()->json($response, $status);
    }
    protected function errorResponse(string $message="something went wrong",int $status=400,mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'status' => $status,
        ];
        if(!is_null($errors)){
            $response['errors'] = $errors;
        }
        return response()->json($response, $status);
    }
    protected function notFoundResponse(string $resource = "Resource"): JsonResponse
    {
        return $this->errorResponse("{$resource} not found", 404);
    }
    protected function createdResponse(mixed $data, string $message = 'Created successfully'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }
    protected function paginatedResponse(
        mixed $paginator,
        mixed $resource,
        string $message = 'Data retrieved successfully'
    ): JsonResponse {
        return $this->successResponse(
            data: $resource,
            message: $message,
            meta: [
                'pagination' => [
                    'total'        => $paginator->total(),
                    'per_page'     => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page'    => $paginator->lastPage(),
                    'from'         => $paginator->firstItem(),
                    'to'           => $paginator->lastItem(),
                ],
            ]
        );
    }
}
