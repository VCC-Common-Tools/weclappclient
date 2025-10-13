# Changelog

Alle wichtigen Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
und dieses Projekt folgt [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
