# WeclappClient

Ein schlanker, flexibler PHP-Client zur Anbindung der Weclapp-REST-API (v1).  
Er unterstützt strukturierte Abfragen, Pagination, Sortierung und grundlegende CRUD-Operationen.  
Die Antwortdaten werden als native PHP-Arrays zurückgegeben – ideal für einfache Integrationen oder eigene Modellierung.

## 📦 Installation

```bash
composer require voipcompetencecenter/weclappclient
```

## 🔧 Einrichtung

```php
use WeclappClient\Core\WeclappClient;

$client = new WeclappClient('mein-subdomain', 'mein-api-key');
```

## 🔍 Abfragen mit dem QueryBuilder

```php
$results = $client->query('/customer')
    ->whereEq('customerType', 'CUSTOMER')
    ->whereLike('company', '%GmbH%')
    ->orderBy('lastModifiedDate', 'desc')
    ->limit(10)
    ->all();
```

### Unterstützte Filtermethoden

| Methode                         | Beschreibung                        |
| ------------------------------- | ----------------------------------- |
| `where($field, $op, $val)`      | Freie Filterbedingung               |
| `whereEq('feld', 'val')`        | Gleichheit                          |
| `whereNe('feld', 'val')`        | Ungleichheit                        |
| `whereGt`, `whereGe`            | Größer als, größer/gleich           |
| `whereLt`, `whereLe`            | Kleiner als, kleiner/gleich         |
| `whereLike`, `whereILike`       | (i)LIKE-Suchmuster                  |
| `whereNotLike`, `whereNotILike` | Negierte LIKE-Suchmuster            |
| `whereIn('feld', [...])`        | Feldwert in Liste enthalten         |
| `whereNull('feld')`             | Feld ist leer/ungesetzt (`IS NULL`) |

### Sortierung

```php
->orderAsc('fieldName')
->orderDesc('fieldName')
->orderBy('fieldName', 'desc') // 'asc' ist Standard
```

### Limitierung und Paging

```php
->limit(50)          // holt max. 50 Ergebnisse über mehrere Seiten hinweg
->page(2, 25)        // Seite 2 mit 25 Einträgen (klassische Paginierung)
```

## 🔢 Zähler

```php
$count = $client->query('/customer')->whereEq('customerType', 'CUSTOMER')->count();
```

## 🎯 Einzelne Datensätze abrufen

```php
// Ersten passenden Datensatz holen
$customer = $client->query('/customer')
    ->whereEq('customerType', 'CUSTOMER')
    ->first();

// Spezifischen Datensatz per ID laden
$customer = $client->query('/customer')->get($id);
```

## 🛠️ CRUD-Operationen

```php
// Einzelobjekt laden
$customer = $client->query('/customer')->get($id);

// Neuer Eintrag
$created = $client->query('/customer')->create([
    'company' => 'Test GmbH',
    'customerType' => 'CUSTOMER',
    'partyType' => 'ORGANIZATION'
]);

// Update
$updated = $client->query('/customer')->update([
    'id' => $created['id'],
    'company' => 'Geändert GmbH'
]);

// Delete
$success = $client->query('/customer')->delete($created['id']);
```

## 📄 Binäre Daten (Downloads & Uploads)

### Downloads

```php
// PDF-Dokument herunterladen
$pdfData = $client->binaryRequest('/document', 'GET', [
    'entityName' => 'salesOrder',
    'entityId' => '123456'
]);

// Als Base64-kodierte Zeichenkette
$base64Data = $client->binaryRequest('/document', 'GET', [
    'entityName' => 'salesOrder', 
    'entityId' => '123456'
], asBase64: true);
```

### Uploads

```php
// PDF-Dokument hochladen (automatische Dateiendung)
$result = $client->binaryUpload('document/upload', $pdfData, 'POST', 'application/pdf', [
    'entityName' => 'salesOrder',
    'entityId' => '123456'
    // name wird automatisch zu "uploaded-file.pdf"
]);

// Mit explizitem Dateinamen
$result = $client->binaryUpload('document/upload', $imageData, 'POST', 'image/jpeg', [
    'entityName' => 'salesOrder',
    'entityId' => '123456',
    'name' => 'rechnung.jpg',
    'description' => 'Rechnungsbeleg'
]);

// Direkt mit binaryRequest
$result = $client->binaryRequest('document/upload', 'POST', [
    'entityName' => 'salesOrder',
    'entityId' => '123456',
    'name' => 'dokument.pdf'
], $binaryData, 'application/pdf');
```

**Unterstützte MIME-Types:**
- `application/pdf` → `.pdf`
- `image/jpeg` → `.jpg`
- `image/png` → `.png`

## 📄 Rückgabeformat

Alle Abfragen liefern Arrays im Weclapp-Format zurück (entspricht `response['body']['result']`).
Beispiel:

```php
[
    'id' => 123456,
    'company' => 'Musterfirma GmbH',
    'customerNumber' => 'C-1000',
    // ...
]
```

## 🧪 Tests

Das Paket enthält Test-Skripte im `tests/` Verzeichnis:

```bash
# Query-Parameter testen (ohne API-Aufrufe)
php tests/test_query_params.php

# first() Methode testen (mit API-Aufrufen)
php tests/test_first_method.php

# Binäre Uploads testen
php tests/test_binary_upload.php
```

Erstellen Sie eine `.env` Datei im `tests/` Verzeichnis mit Ihren Weclapp-Zugangsdaten:

```env
WCLP_TEST_SUBDOMAIN=ihr-subdomain
WCLP_TEST_API_KEY=ihr-api-key
```

---

## 🧩 Für Entwickler: Wiederverwendung des QueryBuilders

Die Klasse `AbstractBaseQueryBuilder` bildet die technische Grundlage für den QueryBuilder.
Sie kapselt API-spezifische Logik für Filter, Pagination, Sortierung und Zählerfunktionen.

Du kannst diese Klasse als Basis verwenden, um eigene API-Clients auf vergleichbarer Architektur aufzubauen – unabhängig von Weclapp.

```php
class MyApiQueryBuilder extends AbstractBaseQueryBuilder
{
    public function all(): array
    {
        // eigene Logik zur vollständigen Abfrage
    }
}
```

## 🧱 Hinweis zu Ressourcenklassen

Für umfangreichere Projekte mit Modellklassen (z. B. `Customer`, `Article`) und objektorientiertem Zugriff
kann dieses Projekt durch ein erweitertes Paket mit **ResourceClient** und **ResourceQueryBuilder** ergänzt werden.
Der Basis-Client funktioniert aber bewusst ohne diese Erweiterung – einfach, schnell, direkt.

## 📋 Changelog

Siehe [CHANGELOG.md](CHANGELOG.md) für detaillierte Versionshistorie.

## 📄 Lizenz

MIT Lizenz - siehe [LICENSE](LICENSE) Datei für Details.