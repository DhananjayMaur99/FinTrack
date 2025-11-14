<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Base exception for FinTrack application
 * 
 * Provides consistent error responses with proper HTTP status codes
 * and structured error messages for API consumers.
 */
abstract class FinTrackException extends Exception
{
    /**
     * HTTP status code for this exception
     */
    protected int $statusCode = 500;

    /**
     * Error code for API consumers
     */
    protected string $errorCode = 'FINTRACK_ERROR';

    /**
     * Additional context for logging
     */
    protected array $context = [];

    /**
     * Create a new exception instance
     */
    public function __construct(string $message = '', array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->context = $context;
    }

    /**
     * Get the HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the error code
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get the error context
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Render the exception as an HTTP response
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage() ?: 'An error occurred',
            'error_code' => $this->errorCode,
            'status' => $this->statusCode,
        ], $this->statusCode);
    }

    /**
     * Report the exception to logs
     */
    public function report(): void
    {
        logger()->error($this->getMessage(), array_merge([
            'exception' => static::class,
            'error_code' => $this->errorCode,
            'status_code' => $this->statusCode,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ], $this->context));
    }
}
