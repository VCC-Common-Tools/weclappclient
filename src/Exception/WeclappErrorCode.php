<?php

namespace WeclappClient\Exception;

/**
 * Enum für standardisierte, interne Fehlercodes des Weclapp-Clients.
 * Deckt alle RFC 7807 konformen Fehlertypen der weclapp API ab.
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

    // RFC 7807 Hauptfehler-Typen
    case Context               = 4000;  // Operation in diesem Kontext nicht möglich
    case Conversation          = 4001;  // Bestehende Konversation nicht erlaubt
    case EntityNotFound        = 4002;  // (Sub-)Entität nicht gefunden
    case Forbidden             = 4003;  // Unzureichende Berechtigungen
    case InvalidJson           = 4004;  // Ungültiges JSON
    case OptimisticLock        = 4005;  // Optimistic Lock Fehler
    case Persistence           = 4006;  // Persistenzfehler
    case Unexpected            = 4007;  // Unerwarteter Fehler
    case UnsupportedMimeType   = 4008;  // Nicht unterstützter MIME-Typ
    case Validation            = 4009;  // Validierung fehlgeschlagen

    // RFC 7807 Validierungsfehler-Typen
    case Authorization         = 5000;  // Keine Autorisierung für Eigenschaft
    case Blocked               = 5001;  // Operation wurde blockiert
    case Consistency           = 5002;  // Werte sind inkonsistent
    case Digits                = 5003;  // Maximale Anzahl von Ziffern überschritten
    case Duplicate             = 5004;  // Entität ist ein Duplikat
    case Email                 = 5005;  // Keine wohlgeformte E-Mail
    case EmailOrDomain         = 5006;  // Keine wohlgeformte E-Mail oder Domain
    case Empty                 = 5007;  // Wert muss leer sein
    case Enum                  = 5008;  // Nicht unterstützter Wert
    case Future                = 5009;  // Zeitstempel muss in der Zukunft liegen
    case GreaterThan           = 5010;  // Wert muss über dem erlaubten Limit liegen
    case LessThan              = 5011;  // Wert muss unter dem erlaubten Limit liegen
    case Max                   = 5012;  // Wert liegt über dem erlaubten Maximum
    case Min                   = 5013;  // Wert liegt unter dem erlaubten Minimum
    case NotEmpty              = 5014;  // Wert darf nicht leer sein
    case Past                  = 5015;  // Zeitstempel muss in der Vergangenheit liegen
    case Pattern               = 5016;  // Wert muss einem bestimmten Muster entsprechen
    case Reference             = 5017;  // Referenzierte Entität nicht gefunden
    case Size                  = 5018;  // Größe liegt außerhalb des erlaubten Bereichs
    case Syntax                = 5019;  // Ausdruck kann nicht interpretiert werden
    case Type                  = 5020;  // Unerwarteter Datentyp

    /**
     * Konvertiert einen API-Fehlertyp (URI-Suffix) zu einem WeclappErrorCode
     */
    public static function fromApiType(string $type): self
    {
        $typeMap = [
            'context' => self::Context,
            'conversation' => self::Conversation,
            'entity_not_found' => self::EntityNotFound,
            'forbidden' => self::Forbidden,
            'invalid_json' => self::InvalidJson,
            'optimistic_lock' => self::OptimisticLock,
            'persistence' => self::Persistence,
            'unauthorized' => self::Unauthorized,
            'unexpected' => self::Unexpected,
            'unsupported_mime_type' => self::UnsupportedMimeType,
            'validation' => self::Validation,
            // Validierungsfehler
            'authorization' => self::Authorization,
            'blocked' => self::Blocked,
            'consistency' => self::Consistency,
            'digits' => self::Digits,
            'duplicate' => self::Duplicate,
            'email' => self::Email,
            'email_or_domain' => self::EmailOrDomain,
            'empty' => self::Empty,
            'enum' => self::Enum,
            'future' => self::Future,
            'greater_than' => self::GreaterThan,
            'less_than' => self::LessThan,
            'max' => self::Max,
            'min' => self::Min,
            'not_empty' => self::NotEmpty,
            'past' => self::Past,
            'pattern' => self::Pattern,
            'reference' => self::Reference,
            'size' => self::Size,
            'syntax' => self::Syntax,
            'type' => self::Type,
        ];

        return $typeMap[$type] ?? self::Unknown;
    }
}
