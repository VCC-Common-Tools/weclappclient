<?php

namespace WeclappClient\Exception;

use RuntimeException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * Wird geworfen bei Fehlern in der Kommunikation mit der Weclapp API.
 * Unterstützt RFC 7807 Problem Details for HTTP APIs.
 */
class WeclappApiException extends WeclappException
{
    private ?array $apiResponse = null;
    private ?ResponseInterface $httpResponse = null;
    private ?string $type = null;
    private ?string $title = null;
    private ?string $detail = null;
    private ?string $instance = null;
    private ?array $validationErrors = null;

    /**
     * Erstellt eine Exception mit optionaler Guzzle-Response oder API-Daten.
     */
    public function __construct(string $message, WeclappErrorCode $errorCode, ?array $apiResponse = null, ?ResponseInterface $httpResponse = null)
    {
        parent::__construct($message, $errorCode);
        $this->apiResponse = $apiResponse;
        $this->httpResponse = $httpResponse;

        // Parse RFC 7807 Felder aus der API-Response
        if ($apiResponse) {
            $this->type = $apiResponse['type'] ?? null;
            $this->title = $apiResponse['title'] ?? null;
            $this->detail = $apiResponse['detail'] ?? null;
            $this->instance = $apiResponse['instance'] ?? null;
            $this->validationErrors = $apiResponse['validationErrors'] ?? null;
        }
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

    /**
     * RFC 7807: URI-Referenz des Fehlertyps
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * RFC 7807: Kurze Zusammenfassung des Problemtyps
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * RFC 7807: Detaillierte Erklärung des Problems
     */
    public function getDetail(): ?string
    {
        return $this->detail;
    }

    /**
     * RFC 7807: URI-Referenz zur betroffenen Entität
     */
    public function getInstance(): ?string
    {
        return $this->instance;
    }

    /**
     * RFC 7807: Array von Validierungsfehlern
     * 
     * @return WeclappValidationError[]
     */
    public function getValidationErrors(): array
    {
        if (!$this->validationErrors) {
            return [];
        }

        return array_map(
            fn(array $error) => WeclappValidationError::fromArray($error),
            $this->validationErrors
        );
    }

    /**
     * Gibt den Fehlertyp-Suffix zurück (nur der Teil nach dem letzten '/')
     */
    public function getTypeSuffix(): ?string
    {
        return $this->type ? basename($this->type) : null;
    }
}
