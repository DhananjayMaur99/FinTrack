<?php

namespace App\Exceptions;

/**
 * Exception thrown when a requested resource is not found
 * 
 * Examples:
 * - Transaction not found
 * - Category not found
 * - Budget not found
 */
class ResourceNotFoundException extends FinTrackException
{
    protected int $statusCode = 404;
    protected string $errorCode = 'RESOURCE_NOT_FOUND';

    /**
     * Create a new resource not found exception
     */
    public static function make(string $resourceType, int|string $identifier): self
    {
        return new self(
            message: "{$resourceType} not found",
            context: [
                'resource_type' => $resourceType,
                'identifier' => $identifier,
            ]
        );
    }
}
