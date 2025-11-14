<?php

namespace App\Exceptions;

/**
 * Exception thrown when a business rule validation fails
 * 
 * Examples:
 * - Budget limit exceeded
 * - Invalid date range
 * - Category has dependent transactions
 */
class BusinessRuleException extends FinTrackException
{
    protected int $statusCode = 422;
    protected string $errorCode = 'BUSINESS_RULE_VIOLATION';

    /**
     * Create a new business rule exception
     */
    public static function make(string $rule, string $message, array $context = []): self
    {
        return new self(
            message: $message,
            context: array_merge(['rule' => $rule], $context)
        );
    }
}
