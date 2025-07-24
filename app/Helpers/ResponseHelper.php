<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class ResponseHelper
{
    /**
     * Return a success response
     */
    public static function success($data = null, $message = 'Success', $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Return an error response
     */
    public static function error($message = 'Error', $data = null, $code = 400): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Return a validation error response
     */
    public static function validationError($errors, $message = 'Validation failed'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], 422);
    }

    /**
     * Return an unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 401);
    }

    /**
     * Return a forbidden response
     */
    public static function forbidden($message = 'Forbidden'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 403);
    }

    /**
     * Return a not found response
     */
    public static function notFound($message = 'Not found'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 404);
    }

    /**
     * Return a server error response
     */
    public static function serverError($message = 'Internal server error'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 500);
    }

    /**
     * Format currency for display
     */
    public static function formatCurrency($amount): string
    {
        $symbol = config('pos.currency.symbol', 'Rp');
        $position = config('pos.currency.position', 'before');
        $decimalPlaces = config('pos.currency.decimal_places', 0);
        $thousandsSeparator = config('pos.currency.thousands_separator', '.');
        $decimalSeparator = config('pos.currency.decimal_separator', ',');

        $formatted = number_format($amount, $decimalPlaces, $decimalSeparator, $thousandsSeparator);

        return $position === 'before' ? $symbol . ' ' . $formatted : $formatted . ' ' . $symbol;
    }

    /**
     * Generate pagination metadata
     */
    public static function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }
}