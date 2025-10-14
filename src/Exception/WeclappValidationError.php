<?php

namespace WeclappClient\Exception;

/**
 * Repräsentiert einen einzelnen Validierungsfehler gemäß RFC 7807.
 * Wird verwendet für strukturierte Validierungsfehler aus der weclapp API.
 */
class WeclappValidationError
{
    /**
     * URI-Referenz, die den Problemtyp identifiziert
     */
    public string $type;

    /**
     * Kurze, menschenlesbare Zusammenfassung des Problemtyps
     */
    public string $title;

    /**
     * Menschenlesbare Erklärung, spezifisch für dieses Vorkommen des Problems
     */
    public string $detail;

    /**
     * Eindeutiger Bezeichner des konkreten Business-Fehlers
     */
    public ?string $errorCode;

    /**
     * URI-Referenz, die das spezifische Vorkommen des Problems identifiziert
     */
    public ?string $instance;

    /**
     * JsonPath-Position der betroffenen Eigenschaft
     */
    public ?string $location;

    /**
     * Liste der erlaubten Werte (abhängig vom konkreten Validierungsfehler)
     */
    public ?array $allowed;

    public function __construct(
        string $type,
        string $title,
        string $detail,
        ?string $errorCode = null,
        ?string $instance = null,
        ?string $location = null,
        ?array $allowed = null
    )
    {
        $this->type = $type;
        $this->title = $title;
        $this->detail = $detail;
        $this->errorCode = $errorCode;
        $this->instance = $instance;
        $this->location = $location;
        $this->allowed = $allowed;
    }

    /**
     * Erstellt eine WeclappValidationError aus einem API-Response-Array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['type'] ?? '',
            $data['title'] ?? '',
            $data['detail'] ?? '',
            $data['errorCode'] ?? null,
            $data['instance'] ?? null,
            $data['location'] ?? null,
            $data['allowed'] ?? null
        );
    }

    /**
     * Gibt den Fehlertyp zurück (nur der Teil nach dem letzten '/')
     */
    public function getTypeSuffix(): string
    {
        return basename($this->type);
    }

    /**
     * Konvertiert zu einem WeclappErrorCode
     */
    public function toErrorCode(): WeclappErrorCode
    {
        return WeclappErrorCode::fromApiType($this->getTypeSuffix());
    }
}
