<?php

namespace WeclappClient\Exception;

use RuntimeException;

/**
 * Wird geworfen bei Fehlern in der Kommunikation mit der Weclapp API.
 */
class WeclappApiException extends RuntimeException
{
    private ?array $apiResponse = null;

    public function __construct(string $message, int $code = 0, ?array $apiResponse = null)
    {
        parent::__construct($message, $code);
        $this->apiResponse = $apiResponse;
    }

    public function getApiResponse(): ?array
    {
        return $this->apiResponse;
    }
}
