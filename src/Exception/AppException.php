<?php

namespace App\Exception;

/**
 * Base exception class for application-specific exceptions.
 */
class AppException extends \Exception
{
    private array $errors = [];

    /**
     * @param string $message Error message
     * @param int $code HTTP status code
     * @param array $errors Additional error details
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(string $message = '', int $code = 0, array $errors = [], \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Get additional error details.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Create a response array from the exception.
     */
    public function toResponseArray(): array
    {
        $response = [
            'error' => $this->getMessage(),
        ];

        if (!empty($this->errors)) {
            $response['details'] = $this->errors;
        }

        return $response;
    }
}