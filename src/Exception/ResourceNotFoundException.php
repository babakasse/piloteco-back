<?php

namespace App\Exception;

/**
 * Exception thrown when a requested resource is not found.
 */
class ResourceNotFoundException extends AppException
{
    /**
     * @param string $resourceType Type of resource (e.g., 'User', 'Company')
     * @param string|int $identifier Identifier used to look up the resource
     */
    public function __construct(string $resourceType, string|int $identifier)
    {
        parent::__construct(
            sprintf('%s with identifier "%s" not found', $resourceType, $identifier),
            404
        );
    }
}