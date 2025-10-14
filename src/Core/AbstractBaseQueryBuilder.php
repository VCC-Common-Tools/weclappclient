<?php

namespace WeclappClient\Core;

use WeclappClient\Core\WeclappClient;

/**
 * Abstrakte Basisklasse für API-Abfragen.
 * Enthält Filter, Paginierung, Sortierung und Zählerfunktionen.
 */
abstract class AbstractBaseQueryBuilder
{
    protected WeclappClient $client;
    protected string $endpoint;

    protected array $filters = [];
    protected array $options = [];
    protected array $orFilters = [];
    protected array $orGroups = [];
    protected ?array $referencedEntities = null;
    protected ?array $properties = null;
    protected ?array $additionalProperties = null;
    protected bool $dryRun = false;

    /**
     * Sollen alle Seiten automatisch abgefragt werden?
     * Standardmäßig aktiviert – klassische Pagination nur bei page().
     */
    protected bool $forceAll = true;

    protected ?int $maxTotal = null;

    protected array $orderFields = [];

    public function __construct(WeclappClient $client, string $endpoint)
    {
        $this->client = $client;
        $this->endpoint = $endpoint;
    }


    public function where(string $field, string $operator, mixed $value): static
    {
        $key = $operator !== '' ? "{$field}-{$operator}" : $field;
        $this->filters[$key] = $value;
        return $this;
    }

    public function whereEq(string $field, mixed $value): static     { return $this->where($field, 'eq', $value); }
    public function whereNe(string $field, mixed $value): static     { return $this->where($field, 'ne', $value); }
    public function whereGt(string $field, mixed $value): static     { return $this->where($field, 'gt', $value); }
    public function whereGe(string $field, mixed $value): static     { return $this->where($field, 'ge', $value); }
    public function whereLt(string $field, mixed $value): static     { return $this->where($field, 'lt', $value); }
    public function whereLe(string $field, mixed $value): static     { return $this->where($field, 'le', $value); }
    public function whereLike(string $field, mixed $value): static   { return $this->where($field, 'like', $value); }
    public function whereILike(string $field, mixed $value): static  { return $this->where($field, 'ilike', $value); }
    public function whereNotLike(string $field, mixed $value): static { return $this->where($field, 'notlike', $value); }
    public function whereNotILike(string $field, mixed $value): static { return $this->where($field, 'notilike', $value); }

    public function whereCustomField(int $customFieldId, string $operator, mixed $value): static
    {
        return $this->where("customField{$customFieldId}", $operator, $value);
    }
    
    public function whereIn(string $field, array $values): static
    {
        $this->filters["{$field}-in"] = json_encode($values);
        return $this;
    }

    public function whereNull(string $field): static
    {
        $this->filters["{$field}-null"] = 'true';
        return $this;
    }

    public function whereNotNull(string $field): static
    {
        $this->filters["{$field}-notnull"] = 'true';
        return $this;
    }

    public function whereNotIn(string $field, array $values): static
    {
        $this->filters["{$field}-notin"] = json_encode($values);
        return $this;
    }

    /**
     * OR-Filterung: Fügt eine OR-Bedingung hinzu
     */
    public function orWhere(string $field, string $operator, mixed $value): static
    {
        $key = $operator !== '' ? "or-{$field}-{$operator}" : "or-{$field}";
        $this->orFilters[$key] = $value;
        return $this;
    }

    public function orWhereEq(string $field, mixed $value): static     { return $this->orWhere($field, 'eq', $value); }
    public function orWhereNe(string $field, mixed $value): static     { return $this->orWhere($field, 'ne', $value); }
    public function orWhereGt(string $field, mixed $value): static     { return $this->orWhere($field, 'gt', $value); }
    public function orWhereGe(string $field, mixed $value): static     { return $this->orWhere($field, 'ge', $value); }
    public function orWhereLt(string $field, mixed $value): static     { return $this->orWhere($field, 'lt', $value); }
    public function orWhereLe(string $field, mixed $value): static     { return $this->orWhere($field, 'le', $value); }
    public function orWhereLike(string $field, mixed $value): static   { return $this->orWhere($field, 'like', $value); }
    public function orWhereILike(string $field, mixed $value): static  { return $this->orWhere($field, 'ilike', $value); }
    public function orWhereNotLike(string $field, mixed $value): static { return $this->orWhere($field, 'notlike', $value); }
    public function orWhereNotILike(string $field, mixed $value): static { return $this->orWhere($field, 'notilike', $value); }
    public function orWhereIn(string $field, array $values): static
    {
        $this->orFilters["or-{$field}-in"] = json_encode($values);
        return $this;
    }
    public function orWhereNotIn(string $field, array $values): static
    {
        $this->orFilters["or-{$field}-notin"] = json_encode($values);
        return $this;
    }
    public function orWhereNull(string $field): static
    {
        $this->orFilters["or-{$field}-null"] = 'true';
        return $this;
    }
    public function orWhereNotNull(string $field): static
    {
        $this->orFilters["or-{$field}-notnull"] = 'true';
        return $this;
    }

    /**
     * OR-Gruppierung: Erstellt eine gruppierte OR-Bedingung
     */
    public function orWhereGroup(string $groupName, callable $callback): static
    {
        $groupBuilder = new class($this->client, $this->endpoint) extends AbstractBaseQueryBuilder {
            public function getOrFilters(): array { return $this->orFilters; }
        };
        
        $callback($groupBuilder);
        $groupFilters = $groupBuilder->getOrFilters();
        
        foreach ($groupFilters as $key => $value) {
            $newKey = str_replace('or-', "or{$groupName}-", $key);
            $this->orGroups[$newKey] = $value;
        }
        
        return $this;
    }

    /**
     * Filter-Ausdrücke (Beta-Feature): Rohe Filter-Ausdrücke
     * 
     * @warning Dies ist ein Beta-Feature der weclapp API
     */
    public function whereRaw(string $filterExpression): static
    {
        $this->options['filter'] = $filterExpression;
        return $this;
    }

    /**
     * Lädt referenzierte Entitäten in derselben Anfrage
     * 
     * @param string|array $references Primärschlüssel-Referenzen (z.B. 'unitId' oder ['unitId', 'articleCategoryId'])
     */
    public function includeReferencedEntities(string|array $references): static
    {
        $this->referencedEntities = is_array($references) ? $references : [$references];
        return $this;
    }

    /**
     * Selektive Rückgabe spezifischer Felder (Bandbreiten-Optimierung)
     * 
     * @param string|array $fields Feldnamen oder Eigenschaftspfade (z.B. 'id' oder ['id', 'customerNumber', 'contacts.lastName'])
     */
    public function properties(string|array $fields): static
    {
        $this->properties = is_array($fields) ? $fields : [$fields];
        return $this;
    }

    /**
     * Lädt optionale berechnete Eigenschaften
     * 
     * @param string|array $properties Namen der zusätzlichen Eigenschaften (z.B. 'currentSalesPrice')
     */
    public function additionalProperties(string|array $properties): static
    {
        $this->additionalProperties = is_array($properties) ? $properties : [$properties];
        return $this;
    }

    /**
     * Aktiviert Null-Serialisierung für explizite Null-Werte
     */
    public function serializeNulls(): static
    {
        $this->options['serializeNulls'] = 'true';
        return $this;
    }

    /**
     * Aktiviert Dry-Run-Modus für Operationen
     * 
     * @warning Im Dry-Run-Modus werden Operationen validiert aber nicht ausgeführt
     */
    public function dryRun(): static
    {
        $this->dryRun = true;
        return $this;
    }

    /**
     * Prüft ob Dry-Run aktiviert ist
     */
    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    /**
     * Setzt die maximale Anzahl von Ergebnissen, die insgesamt zurückgegeben werden sollen.
     * Wirkt auch seitenübergreifend (Pagination bleibt aktiv).
     */
    public function limit(int $limit): static
    {
        $this->maxTotal = max(1, $limit);
        return $this;
    }

    /**
     * Aktiviert explizite Seitenpaginierung (setzt forceAll auf false).
     */
    public function page(int $page, int $pageSize = 100): static
    {
        $this->forceAll = false;
        $this->options['page'] = max(1, $page);
        $this->options['pageSize'] = min(100, max(1, $pageSize));
        return $this;
    }

    /**
     * Behält Kompatibilität – ist standardmäßig bereits aktiv.
     */
    public function nolimit(): static
    {
        $this->forceAll = true;
        $this->maxTotal = null; // Setzt maxTotal zurück, um alle Ergebnisse
        return $this;
    }

    public function orderAsc(string $field): static
    {
        $this->orderFields[] = $field;
        return $this;
    }

    public function orderDesc(string $field): static
    {
        $this->orderFields[] = '-' . $field;
        return $this;
    }

    public function orderBy(string $field, string $direction = 'asc'): static
    {
        return strtolower($direction) === 'desc'
            ? $this->orderDesc($field)
            : $this->orderAsc($field);
    }

    public function buildQueryParams(): array
    {
        $params = array_merge($this->filters, $this->options, $this->orFilters, $this->orGroups);

        if (!empty($this->orderFields)) {
            $params['sort'] = implode(',', $this->orderFields);
        }

        // Referenced Entities
        if ($this->referencedEntities) {
            $params['includeReferencedEntities'] = implode(',', $this->referencedEntities);
        }

        // Properties
        if ($this->properties) {
            $params['properties'] = implode(',', $this->properties);
        }

        // Additional Properties
        if ($this->additionalProperties) {
            $params['additionalProperties'] = implode(',', $this->additionalProperties);
        }

        // Dry-Run
        if ($this->dryRun) {
            $params['dryRun'] = 'true';
        }

        // maxTotal wird NICHT als Query-Parameter gesendet, da es nur intern für Limitierung verwendet wird
        // Die API erwartet keine maxTotal-Parameter

        return $params;
    }

    public function count(): int
    {
        $uri = rtrim($this->endpoint, '/') . '/count';
        $response = $this->client->request($uri, 'GET', $this->filters);
        return (int) ($response['body']['result'] ?? 0);
    }
}
