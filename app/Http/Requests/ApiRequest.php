<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base class for all API requests.
 * Ensures validation errors always return JSON responses.
 */
abstract class ApiRequest extends FormRequest
{
    /**
     * Determine if the current request expects a JSON response.
     * For API requests, we always expect JSON.
     */
    public function expectsJson(): bool
    {
        return true;
    }

    /**
     * Determine if the current request is asking for JSON.
     * For API requests, we always want JSON.
     */
    public function wantsJson(): bool
    {
        return true;
    }
}
