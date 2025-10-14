# WeclappClient v2.0.0

A comprehensive and flexible PHP client for interacting with the Weclapp REST API v2.  
It supports structured queries, pagination, sorting, CRUD operations, and all advanced API features.  
Responses are returned as native PHP arrays with full RFC 7807 error handling.

## 🚀 What's New in v2.0.0

- **API v2 Migration**: Full support for weclapp API v2 with legacy v1 compatibility
- **Advanced Filtering**: OR conditions, grouping, raw filter expressions (Beta)
- **Performance Optimization**: Referenced entities, selective properties, additional properties
- **Enhanced Updates**: Partial updates, dry-run mode, null serialization
- **Complete Error Handling**: RFC 7807 compliant with 40+ error types
- **Backward Compatible**: Minimal breaking changes, easy migration from v1.x

## 📦 Installation

```bash
composer require voipcompetencecenter/weclappclient
```

## 🔧 Setup

```php
use WeclappClient\Core\WeclappClient;

// Default: API v2
$client = new WeclappClient('your-subdomain', 'your-api-key');

// Legacy: API v1 (if needed)
$client = new WeclappClient('your-subdomain', 'your-api-key', null, 1);
```

## 🔍 Querying with the QueryBuilder

```php
$results = $client->query('/customer')
    ->whereEq('customerType', 'CUSTOMER')
    ->whereLike('company', '%GmbH%')
    ->orderBy('lastModifiedDate', 'desc')
    ->limit(10)
    ->all();
```

### Supported Filter Methods

| Method                          | Description                         |
| ------------------------------- | ----------------------------------- |
| `where($field, $op, $val)`      | Custom filter condition             |
| `whereEq('field', 'val')`       | Equals                              |
| `whereNe('field', 'val')`       | Not equal                           |
| `whereGt`, `whereGe`            | Greater than, greater or equal      |
| `whereLt`, `whereLe`            | Less than, less or equal            |
| `whereLike`, `whereILike`       | (i)LIKE search pattern              |
| `whereNotLike`, `whereNotILike` | NOT LIKE pattern                    |
| `whereIn('field', [...])`       | Field value in given list           |
| `whereNotIn('field', [...])`    | Field value NOT in given list       |
| `whereNull('field')`            | Field is null                       |
| `whereNotNull('field')`         | Field is not null                   |

### Advanced Filtering (v2.0.0)

```php
// OR Conditions
$results = $client->query('/party')
    ->whereEq('firstName', 'Max')
    ->orWhere('lastName', 'eq', 'Mustermann')
    ->orWhere('email', 'like', '%@example.com')
    ->getResult();

// OR Grouping
$results = $client->query('/party')
    ->orWhereGroup('location', function($q) {
        $q->orWhere('city', 'eq', 'Berlin')
          ->orWhere('city', 'eq', 'München');
    })
    ->orWhereGroup('status', function($q) {
        $q->orWhere('active', 'eq', true)
          ->orWhere('verified', 'eq', true);
    })
    ->getResult();

// Raw Filter Expressions (Beta)
$results = $client->query('/party')
    ->whereRaw('(age >= 18) and (customer = true)')
    ->getResult();
```

### Sorting

```php
->orderAsc('fieldName')
->orderDesc('fieldName')
->orderBy('fieldName', 'desc') // 'asc' is default
```

### Limiting and Pagination

```php
->limit(50)          // fetches up to 50 records across pages
->page(2, 25)        // page 2 with 25 entries (classic pagination)
```

## 🔢 Counting

```php
$count = $client->query('/customer')->whereEq('customerType', 'CUSTOMER')->count();
```

## 🎯 Getting Single Records

```php
// Get first matching record
$customer = $client->query('/customer')
    ->whereEq('customerType', 'CUSTOMER')
    ->first();

// Get specific record by ID
$customer = $client->query('/customer')->get($id);
```

## ⚡ Performance Optimization (v2.0.0)

```php
// Selective Properties - Only fetch needed fields
$results = $client->query('/article')
    ->properties(['id', 'name', 'unitId', 'articleCategoryId'])
    ->getResult();

// Referenced Entities - Load related data in one request
$results = $client->query('/article')
    ->includeReferencedEntities(['unitId', 'articleCategoryId'])
    ->getResult();

// Additional Properties - Computed fields
$results = $client->query('/article')
    ->additionalProperties('currentSalesPrice')
    ->getResult();

// Combined optimization
$results = $client->query('/article')
    ->properties(['id', 'name', 'unitId'])
    ->includeReferencedEntities(['unitId'])
    ->additionalProperties('currentSalesPrice')
    ->serializeNulls() // Include null values explicitly
    ->getResult();
```

## 🛠️ CRUD Operations

```php
// Fetch single object by ID
$customer = $client->query('/customer')->get($id);

// Create
$created = $client->query('/customer')->create([
    'company' => 'Test GmbH',
    'customerType' => 'CUSTOMER',
    'partyType' => 'ORGANIZATION'
]);

// Update (Traditional)
$updated = $client->query('/customer')->update([
    'id' => $created['id'],
    'company' => 'Updated GmbH'
]);

// Partial Update (v2.0.0) - Only update specified fields
$updated = $client->query('/customer')->partialUpdate([
    'id' => $created['id'],
    'version' => $created['version'],
    'email' => 'new.email@example.com'
]);

// Dry-Run Mode (v2.0.0) - Validate without executing
$validation = $client->query('/customer')
    ->dryRun()
    ->create([
        'company' => 'Test Corp',
        'customerType' => 'CUSTOMER'
    ]);

// Delete
$success = $client->query('/customer')->delete($created['id']);
```

## 🛡️ Error Handling (v2.0.0)

The client now provides comprehensive RFC 7807 compliant error handling:

```php
use WeclappClient\Exception\WeclappApiException;
use WeclappClient\Exception\WeclappValidationError;

try {
    $result = $client->query('/party')->create($data);
} catch (WeclappApiException $e) {
    // RFC 7807 compliant error information
    echo "Error Type: " . $e->getType();           // URI reference to error type
    echo "Title: " . $e->getTitle();               // Short summary
    echo "Detail: " . $e->getDetail();             // Detailed explanation
    echo "Instance: " . $e->getInstance();         // URI to affected entity
    
    // Validation errors (if any)
    foreach ($e->getValidationErrors() as $error) {
        echo "Field: " . $error->location;         // JsonPath to field
        echo "Message: " . $error->detail;         // Field-specific error
        echo "Allowed: " . implode(', ', $error->allowed ?? []); // Valid values
    }
}
```

### Error Types

The client supports 40+ error types including:
- **Main Errors**: `context`, `forbidden`, `optimistic_lock`, `validation`, etc.
- **Validation Errors**: `email`, `pattern`, `size`, `reference`, `enum`, etc.

## 📄 Response Format

All queries return Weclapp-formatted arrays (i.e. `response['body']['result']`).
Example:

```php
[
    'id' => 123456,
    'company' => 'Sample Company GmbH',
    'customerNumber' => 'C-1000',
    // ...
]
```

## 🔄 Migration from v1.x

### Breaking Changes (Minimal)

1. **Default API Version**: Now uses v2 by default (v1 still available)
2. **Update Method**: New signature `update(array $data, bool $ignoreMissingProperties = false)`

### Migration Steps

```php
// v1.x (old)
$client = new WeclappClient('tenant', 'token');

// v2.0.0 (automatic migration)
$client = new WeclappClient('tenant', 'token'); // Uses v2 by default

// Legacy v1 (if needed)
$client = new WeclappClient('tenant', 'token', null, 1);
```

### New Features Available

All new features are **optional** and **backward compatible**:

```php
// Use new advanced filtering
$results = $client->query('/party')
    ->whereNotNull('email')
    ->orWhere('firstName', 'eq', 'Max')
    ->getResult();

// Use performance optimizations
$results = $client->query('/article')
    ->properties(['id', 'name'])
    ->includeReferencedEntities(['unitId'])
    ->getResult();

// Use enhanced updates
$client->query('/party')->partialUpdate($data);
$client->query('/party')->dryRun()->create($data);
```

## 🧪 Testing

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test
vendor/bin/phpunit tests/unit/CustomerTest.php
```

---

## 🧩 For Developers: Reusing the QueryBuilder

The class `AbstractBaseQueryBuilder` provides the technical foundation for the QueryBuilder.
It encapsulates reusable API logic for filters, pagination, sorting, and counting.

You can use this class as a base to implement your own API clients in a similar architecture – independent of Weclapp.

```php
class MyApiQueryBuilder extends AbstractBaseQueryBuilder
{
    public function all(): array
    {
        // custom logic to retrieve all data
    }
}
```

## 📋 Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

## 📄 License

MIT License - see [LICENSE](LICENSE) file for details.