<?php

namespace WeclappClient\Exception;

use RuntimeException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * Wird geworfen bei Fehlern in der Kommunikation mit der Weclapp API.
 */
class WeclappApiException extends WeclappException
{
    private ?array $apiResponse = null;
    private ?ResponseInterface $httpResponse = null;

    /**
     * Erstellt eine Exception mit optionaler Guzzle-Response oder API-Daten.
     */
    public function __construct(string $message, WeclappErrorCode $errorCode, ?array $apiResponse = null, ?ResponseInterface $httpResponse = null)
    {
        parent::__construct($message, $errorCode);
        $this->apiResponse = $apiResponse;
        $this->httpResponse = $httpResponse;
    }


    /**
     * Erzeugt eine Exception aus einer Guzzle-RequestException.
     */
    public static function fromRequestException(RequestException $e): self
    {
        $response = $e->getResponse();
        $code = $response?->getStatusCode() ?? 0;
        $body = $response ? (string) $response->getBody() : null;
        $data = null;

        if ($body and str_starts_with(trim($body), '{')) {
            $data = json_decode($body, true);
        }

        $message = $data['message'] ?? $e->getMessage();
        $errorCode = match ($code) {
            401 => WeclappErrorCode::Unauthorized,
            404 => WeclappErrorCode::NotFound,
            408 => WeclappErrorCode::Timeout,
            default => WeclappErrorCode::ApiRequestFailed
        };

        return new self($message, $errorCode, $data, $response);
    }


    public function getApiResponse(): ?array
    {
        return $this->apiResponse;
    }

    public function getHttpResponse(): ?ResponseInterface
    {
        return $this->httpResponse;
    }
}
