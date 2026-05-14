<?php

namespace App\Traits;

trait ApiResponseTrait
{
    /**
     * Berikan response sukses berformat standar
     */
    protected function successResponse($message, $data = null, $code = 200, $extra = [])
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if (!is_null($data)) {
            $response['data'] = $data;
        }

        if (!empty($extra)) {
            $response = array_merge($response, $extra);
        }

        return response()->json($response, $code);
    }

    /**
     * Berikan response error berformat standar
     */
    protected function errorResponse($message, $code = 500, $exception = null)
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        // Tampilkan detail error hanya jika dalam mode debug
        if (!is_null($exception) && config('app.debug')) {
            $response['error'] = (is_object($exception) && method_exists($exception, 'getMessage')) 
                                ? $exception->getMessage() 
                                : $exception;
        }

        return response()->json($response, $code);
    }
}
