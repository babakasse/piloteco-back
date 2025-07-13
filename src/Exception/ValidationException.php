<?php

namespace App\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Exception thrown when validation fails.
 */
class ValidationException extends AppException
{
    /**
     * Create a validation exception from constraint violations.
     */
    public static function fromConstraintViolations(ConstraintViolationListInterface $violations): self
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()] = $violation->getMessage();
        }

        return new self('Validation failed', 400, $errors);
    }
}