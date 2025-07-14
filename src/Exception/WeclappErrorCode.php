<?php

namespace WeclappClient\Exception;

/**
 * Enum für standardisierte, interne Fehlercodes des Weclapp-Clients.
 */
enum WeclappErrorCode: int
{
    // Allgemeine Fehler
    case Unknown               = 1000;
    case InvalidField          = 1001;
    case MissingId             = 1002;
    case InvalidEndpoint       = 1003;

    // API-/Kommunikationsfehler
    case ApiRequestFailed      = 2000;
    case Unauthorized          = 2001;
    case NotFound              = 2002;
    case Timeout               = 2003;

    // Datenvalidierung
    case ValidationFailed      = 3000;
    case CustomFieldInvalid    = 3001;
    case MissingField          = 3002;
    case MissingRequiredField  = 3003;
}
