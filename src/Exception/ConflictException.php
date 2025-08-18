<?php

namespace App\Exception;

/**
 * Exception thrown when there's a conflict with an existing resource.
 */
class ConflictException extends AppException
{
    /**
     * @param string $message Error message
     * @param array $errors Additional error details
     */
    public function __construct(string $message = 'Resource conflict', array $errors = [])
    {
        parent::__construct($message, 409, $errors);
    }

    /**
     * Create an exception for a duplicate email.
     */
    public static function duplicateEmail(string $email): self
    {
        return new self(
            sprintf('Email "%s" is already in use', $email),
            ['email' => 'This email is already in use.']
        );
    }
}