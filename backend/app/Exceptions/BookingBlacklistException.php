<?php

namespace App\Exceptions;

use Exception;

class BookingBlacklistException extends Exception
{
    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => null,
        ], 422);
    }
}
