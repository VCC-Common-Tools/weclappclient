# WeclappClient

A lightweight and flexible PHP client for interacting with the Weclapp REST API (v1).  
It supports structured queries, pagination, sorting, and basic CRUD operations.  
Responses are returned as native PHP arrays – ideal for lightweight integrations or custom modeling.

## 📦 Installation

```bash
composer require voipcompetencecenter/weclappclient
````

## 🔧 Setup

```php
use WeclappClient\Core\WeclappClient;

$client = new WeclappClient('your-subdomain', 'your-api-key');
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
| `whereIsNull('field')`          | Field is null / not set (`IS NULL`) |

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

// Update
$updated = $client->query('/customer')->update([
    'id' => $created['id'],
    'company' => 'Updated GmbH'
]);

// Delete
$success = $client->query('/customer')->delete($created['id']);
```

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
