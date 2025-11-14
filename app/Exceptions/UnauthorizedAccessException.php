<?php

namespace App\Exceptions;

/**
 * Exception thrown when a user attempts to access a resource they don't own
 * 
 * Examples:
 * - Accessing another user's transaction
 * - Modifying another user's category
 * - Viewing another user's budget
 */
class UnauthorizedAccessException extends FinTrackException
{
    protected int $statusCode = 403;
    protected string $errorCode = 'UNAUTHORIZED_ACCESS';

    /**
     * Create a new unauthorized access exception
     */
    public static function make(string $resourceType, int|string $identifier): self
    {
        return new self(
            message: "You do not have permission to access this {$resourceType}",
            context: [
                'resource_type' => $resourceType,
                'identifier' => $identifier,
            ]
        );
    }
}
