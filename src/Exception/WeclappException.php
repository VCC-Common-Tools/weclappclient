<?php

namespace WeclappClient\Exception;

use Exception;

/**
 * Basisklasse für alle Weclapp-spezifischen Fehler.
 * Unterstützt optionale Fehlercodes über Enum.
 */
class WeclappException extends Exception
{
    protected ?WeclappErrorCode $errorCode = null;

    public function __construct(
        string $message,
        WeclappErrorCode $errorCode = WeclappErrorCode::Unknown,
        \Throwable $previous = null
    )
    {
        $this->errorCode = $errorCode;
        parent::__construct($message, $errorCode->value, $previous);
    }

    public function getErrorCode(): ?WeclappErrorCode
    {
        return $this->errorCode;
    }
}
