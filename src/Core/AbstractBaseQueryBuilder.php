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

    /**
     * Sollen alle Seiten automatisch abgefragt werden?
     * Standardmäßig aktiviert – klassische Pagination nur bei page().
     */
    protected bool $forceAll = true;

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

    public function whereIn(string $field, array $values): static
    {
        $this->filters["{$field}-in"] = json_encode($values);
        return $this;
    }

    public function whereIsNull(string $field): static
    {
        $this->filters["{$field}-null"] = 'true';
        return $this;
    }

    /**
     * Setzt die maximale Anzahl von Ergebnissen, die insgesamt zurückgegeben werden sollen.
     * Wirkt auch seitenübergreifend (Pagination bleibt aktiv).
     */
    public function limit(int $limit): static
    {
        $this->options['maxTotal'] = max(1, $limit);
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

    protected function buildQueryParams(): array
    {
        $params = array_merge($this->filters, $this->options);

        if (!empty($this->orderFields)) {
            $params['sort'] = implode(',', $this->orderFields);
        }

        return $params;
    }

    public function count(): int
    {
        $uri = rtrim($this->endpoint, '/') . '/count';
        $response = $this->client->request($uri, 'GET', $this->filters);
        return (int) ($response['body']['result'] ?? 0);
    }
}
