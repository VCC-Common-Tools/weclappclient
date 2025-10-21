# Changelog

Alle wichtigen Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
und dieses Projekt folgt [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.1] - 2025-01-15

### Added

#### 🏷️ Custom Attributes (v2.0.0)
- **Type-safe Custom Attribute Filtering**: Vollständige Unterstützung für weclapp Custom Attributes
  - `whereCustomAttributeString()` - String-Werte mit automatischer Typ-Erkennung
  - `whereCustomAttributeBoolean()` - Boolean-Werte mit Typ-Sicherheit
  - `whereCustomAttributeNumber()` - Numerische Werte (int/float)
  - `whereCustomAttributeDate()` - Datum-Werte mit automatischer String→Timestamp Konvertierung
  - `orWhereCustomAttribute()` - OR-Filter für Custom Attributes
  - Unterstützt alle Custom Attribute Typen: `stringValue`, `booleanValue`, `numberValue`, `dateValue`, `selectedValueId`
  - Spezielle Unterstützung für Entity-Referenzen: `customAttribute{ID}.entityReferences.entityId-eq=VALUE`
  - Unterstützung für LIST/MULTISELECT_LIST: `customAttribute{ID}.value-eq=VALUE`

#### 🔧 Special Parameters (v2.0.0)
- **Endpoint-specific Parameters**: Elegante Behandlung von Parametern ohne Operator-Suffix
  - `entityName()` - Setzt Entity-Namen für document/comment Endpunkte
  - `entityId()` - Setzt Entity-ID für document/comment Endpunkte
  - `param()` - Generische Methode für beliebige Parameter
  - Konsistente Integration in das bestehende `$options` Array (wie `page`, `pageSize`, `sort`)
  - Method Chaining für bessere Lesbarkeit: `->entityName('party')->entityId('12345')`

### Fixed

#### 🔧 Custom Attribute Filter
- **Kritischer Bugfix**: `whereCustomAttribute()` verwendet jetzt das korrekte Filter-Format für weclapp API v2
  - **Vorher**: `customAttributes-eq=JSON` (falsch)
  - **Nachher**: `customAttribute{ID}-eq=VALUE` (korrekt)
  - Behebt den Fehler "unexpected filter property" bei Custom Attribute Filtern

#### 🔍 Debugging-Verbesserung
- **getLastUrl() Enhancement**: Zeigt jetzt alle Query-Parameter in der URL an
  - Vorher: Nur Basis-URL ohne Parameter
  - Nachher: Vollständige URL mit allen Query-Parametern
  - Sehr nützlich für Debugging und API-Testing

### Technical Details
- Keine Breaking Changes
- Vollständig rückwärtskompatibel
- Verbesserte API-Konformität mit weclapp v2
- Konsistente Architektur mit bestehenden `$options` Array

---

## [2.0.0] - 2025-01-15

### 🚀 Major Release: Migration auf weclapp API v2

Dieses Major-Release migriert den WeclappClient auf die weclapp API v2 und implementiert alle bisher fehlenden Features für einen vollständigen, modernen API-Client.

### Added

#### 🔧 API-Version-Unterstützung
- **Flexible API-Version**: Konstruktor-Parameter `$apiVersion` (Standard: v2, Legacy: v1)
- **Automatische URL-Generierung**: Dynamische Basis-URL basierend auf API-Version
- **Getter-Methode**: `getApiVersion()` für Debugging und Logging

#### 🔍 Erweiterte Filter-Operatoren
- **Neue Operatoren**: `whereNotNull()`, `whereNotIn()` für vollständige Filter-Abdeckung
- **OR-Filterung**: `orWhere()`, `orWhereEq()`, `orWhereNe()`, etc. für OR-Bedingungen
- **OR-Gruppierung**: `orWhereGroup()` für komplexe gruppierte OR-Ausdrücke
- **Filter-Ausdrücke**: `whereRaw()` für Beta-Feature komplexer Filter-Ausdrücke

#### 🛡️ Vollständiges Error Handling (RFC 7807)
- **40+ Error Codes**: Vollständige Abdeckung aller weclapp API-Fehlertypen
- **RFC 7807 Mapping**: `getType()`, `getTitle()`, `getDetail()`, `getInstance()`
- **Validierungsfehler**: Strukturierte `WeclappValidationError` Klasse
- **Automatische Typ-Erkennung**: `fromApiType()` für API-Fehlertyp-Mapping

#### ⚡ Performance-Optimierungen
- **Referenced Entities**: `includeReferencedEntities()` - Lädt referenzierte Entitäten in einer Anfrage
- **Properties-Filter**: `properties()` - Selektive Feldauswahl für Bandbreiten-Optimierung
- **Additional Properties**: `additionalProperties()` - Optionale berechnete Eigenschaften

#### 🔄 Erweiterte Update-Funktionen
- **Partial Updates**: `partialUpdate()` und `update($data, true)` mit `ignoreMissingProperties`
- **Dry-Run-Modus**: `dryRun()` für sichere Validierung ohne Ausführung
- **Null-Serialisierung**: `serializeNulls()` für explizite Null-Werte

### Changed

#### ⚠️ Breaking Changes (Minimal)
- **Standard-API-Version**: Ändert sich von v1 auf v2 (Legacy v1 weiter nutzbar)
- **Update-Methode**: Neue Signatur `update(array $data, bool $ignoreMissingProperties = false)`

#### 🔄 Verbesserungen
- **QueryBuilder**: Erweiterte `buildQueryParams()` für alle neuen Features
- **Error Handling**: Verbesserte RFC 7807 Konformität
- **Dokumentation**: Vollständige PHPDoc-Kommentare für alle neuen Methoden

### Migration Guide

#### Von v1.x zu v2.0.0

**1. API-Version (Optional)**
```php
// v1.x (alt)
$client = new WeclappClient('tenant', 'token');

// v2.0.0 (Standard: v2)
$client = new WeclappClient('tenant', 'token'); // Nutzt automatisch v2

// Explizit v1 (Legacy)
$client = new WeclappClient('tenant', 'token', null, 1);
```

**2. Update-Methode (Optional)**
```php
// v1.x (alt)
$client->query('party')->update($data);

// v2.0.0 (kompatibel)
$client->query('party')->update($data); // Funktioniert weiterhin

// v2.0.0 (neue Features)
$client->query('party')->partialUpdate($data); // Partielles Update
$client->query('party')->dryRun()->update($data); // Dry-Run
```

**3. Neue Features nutzen**
```php
// Erweiterte Filter
$client->query('party')
    ->whereNotNull('email')
    ->orWhere('firstName', 'eq', 'Max')
    ->orWhereGroup('group1', fn($q) => $q->orWhere('lastName', 'eq', 'Mustermann'))
    ->whereRaw('(age > 18) and (city = "Berlin")')
    ->getResult();

// Custom Attributes (ersetzt Legacy customField)
$client->query('party')
    ->whereCustomAttributeString('customer-note', 'like', '%VIP%')
    ->whereCustomAttributeBoolean('is-premium', 'eq', true)
    ->whereCustomAttributeDate('last-contact', 'gt', '2024-01-01')
    ->getResult();

// Special Parameters für document/comment Endpunkte
$client->query('document')
    ->entityName('party')
    ->entityId('12345')
    ->whereLike('name', '%Rechnung%')
    ->getResult();

// Performance-Optimierung
$client->query('article')
    ->properties(['id', 'name', 'unitId'])
    ->includeReferencedEntities(['unitId', 'articleCategoryId'])
    ->additionalProperties('currentSalesPrice')
    ->getResult();

// Vollständiges Error Handling
try {
    $result = $client->query('party')->create($data);
} catch (WeclappApiException $e) {
    echo $e->getTitle(); // RFC 7807 Titel
    echo $e->getDetail(); // Detaillierte Erklärung
    foreach ($e->getValidationErrors() as $error) {
        echo $error->getLocation(); // JsonPath der betroffenen Eigenschaft
    }
}
```

### Technical Details
- **PHP 8.2+**: Unveränderte Mindestanforderung
- **Guzzle HTTP**: Weiterhin für robuste API-Kommunikation
- **PSR-4 Autoloading**: Unverändert
- **MIT Lizenz**: Unverändert

---

## [1.0.1] - 2025-01-15

### Fixed
- **Kritischer Bugfix**: `maxTotal` Parameter wird nicht mehr als Query-Parameter an die Weclapp API gesendet
  - Die `first()` Methode verwendet jetzt `page(1, 1)` statt `limit(1)`
  - `maxTotal` wird nur noch intern für die Limitierung über mehrere Seiten verwendet
  - Behebt den Fehler "unexpected filter property" bei API-Aufrufen

### Changed
- `whereIsNull()` Methode wurde zu `whereNull()` umbenannt für bessere Konsistenz

### Added
- Test-Skripte für die Validierung der Query-Parameter
- Detaillierte Kommentare zur Unterscheidung zwischen `limit` und `pageSize`

## [1.0.0] - 2025-01-15

### Added
- Erste stabile Version des WeclappClient
- Vollständige QueryBuilder-Implementierung mit Filter, Sortierung und Paginierung
- Unterstützung für alle CRUD-Operationen (Create, Read, Update, Delete)
- Automatische Mehrseitenabfragen mit `all()` Methode
- Binäre Datenabfragen für Dateien und Bilder
- Umfassende Fehlerbehandlung mit spezifischen Weclapp-Exception-Klassen
- Bootstrap-System für einfache Test-Konfiguration
- Unit-Tests mit PHPUnit

### Features
- **QueryBuilder**: Flexible API-Abfragen mit Method-Chaining
- **Filter**: Unterstützung für alle Weclapp-Filter-Operatoren (eq, ne, gt, ge, lt, le, like, ilike, etc.)
- **Paginierung**: Automatische Mehrseitenabfragen oder manuelle Seitensteuerung
- **Sortierung**: Mehrfache Sortierung mit `orderBy()`, `orderAsc()`, `orderDesc()`
- **Custom Fields**: Spezielle Unterstützung für Weclapp Custom Fields
- **Batch-Operationen**: Effiziente Abfragen großer Datenmengen

### Technical Details
- PHP 8.2+ Kompatibilität
- Guzzle HTTP Client für robuste API-Kommunikation
- PSR-4 Autoloading
- MIT Lizenz
