<?php

namespace WeclappClient\Query;

use WeclappClient\WeclappClient;

/**
 * Der QueryBuilder dient zur flexiblen Abfrage von Weclapp-Ressourcen.
 * Unterstützt Filter, Sortierung, Pagination und Mehrseitenabfragen.
 * Gibt rohe Datenarrays zurück – keine Response-Wrapper.
 */
class QueryBuilder
{
    /**
     * WeclappClient-Instanz
     */
    private WeclappClient $client;

    /**
     * Endpunkt-URI (z. B. /article)
     */
    private string $endpoint;

    /**
     * Gespeicherte Filter
     */
    private array $filters = [];

    /**
     * Optionen wie pageSize, page
     */
    private array $options = [];


    /**
     * Aktiviert Mehrseitenabfrage für all()
     */
    private bool $forceAll = false;

    /**
     * Sortierreihenfolge
     */
    private array $orderFields = [];

    /**
     * Konstruktor
     */
    public function __construct(WeclappClient $client, string $endpoint)
    {
        $this->client = $client;
        $this->endpoint = $endpoint;
    }

    /**
     * Einfache WHERE-Bedingung
     */
    public function where(string $field, string $operator, string $value): self
    {
        $key = $operator !== '' ? "{$field}-{$operator}" : $field;
        $this->filters[$key] = $value;

        return $this;
    }

    // Kurzformen für gängige Filtertypen
    public function whereEq(string $field, string $value): self { return $this->where($field, 'eq', $value); }
    public function whereNe(string $field, string $value): self { return $this->where($field, 'ne', $value); }
    public function whereGt(string $field, string $value): self { return $this->where($field, 'gt', $value); }
    public function whereGe(string $field, string $value): self { return $this->where($field, 'ge', $value); }
    public function whereLt(string $field, string $value): self { return $this->where($field, 'lt', $value); }
    public function whereLe(string $field, string $value): self { return $this->where($field, 'le', $value); }

    public function whereLike(string $field, string $value): self { return $this->where($field, 'like', $value); }
    public function whereILike(string $field, string $value): self { return $this->where($field, 'ilike', $value); }
    public function whereNotLike(string $field, string $value): self { return $this->where($field, 'notlike', $value); }
    public function whereNotILike(string $field, string $value): self { return $this->where($field, 'notilike', $value); }


    /**
     * WHERE field IN (...)
     */
    public function whereIn(string $field, array $values): self
    {
        $this->filters["{$field}-in"] = json_encode($values);

        return $this;
    }

    /**
     * WHERE field IS NULL
     */
    public function whereIsNull(string $field): self
    {
        $this->filters["{$field}-null"] = 'true';

        return $this;
    }

    /**
     * Setzt die Anzahl der Ergebnisse pro Seite (1–100 erlaubt)
     */
    public function limit(int $limit): self
    {
        // Begrenzung laut Weclapp-API
        $this->options['pageSize'] = min(100, max(1, $limit));

        return $this;
    }

    /**
     * Setzt die aktuelle Seite (≥1) und optional die pageSize
     */
    public function page(int $page, int $pageSize = 100): self
    {
        $this->options['page'] = max(1, $page);

        return $this->limit($pageSize);
    }

    /**
     * Aktiviert automatische Pagination bei all()
     */
    public function nolimit(): self
    {
        $this->forceAll = true;

        return $this;
    }

    /**
     * Kombiniere Filter, Optionen und Sortierung zu Query-Parametern
     */
    private function buildQueryParams(): array
    {
        $params = array_merge($this->filters, $this->options);

        if (!empty($this->orderFields)) {
            $params['sort'] = implode(',', $this->orderFields);
        }

        return $params;
    }

    /**
     * Einzelnes Objekt über /resource/{id} laden
     *
     * @param int|string $id
     * @return array
     */
    public function get(int|string $id): array
    {
        $uri = rtrim($this->endpoint, '/') . '/id/' . $id;
        $response = $this->client->request($uri, 'GET');

        return $response['body'] ?? [];
    }

    /**
     * Liefert nur das result[] aus der API-Antwort
     */
    public function getResult(): ?array
    {
        $response = $this->client->request($this->endpoint, 'GET', $this->buildQueryParams());

        return $response['body']['result'] ?? null;
    }

    /**
     * Holt das erste Element einer gefilterten Liste
     */
    public function first(): ?array
    {
        $result = $this->limit(1)->getResult();

        return $result[0] ?? null;
    }

    /**
     * Holt alle Ergebnisse – mit Paginierung bei aktiviertem nolimit()
     */
    public function all(callable $progressCallback = null): array
    {
        if (!$this->forceAll)
        {
            return $this->getResult() ?? [];
        }

        $all = [];
        $page = 1;
        $pageSize = 100;    //Maximale Seitengröße laut Weclapp-API

        do
        {
            $this->page($page, $pageSize);
            $response = $this->client->request($this->endpoint, 'GET', $this->buildQueryParams());
            $result = $response['body']['result'] ?? [];

            $all = array_merge($all, $result);

            if ($progressCallback !== null)
            {
                $progressCallback($page, count($result));
            }

            $page++;
        }
        while (!empty($result));

        return $all;
    }

    /**
     * Führt eine /count-Abfrage durch und gibt die Anzahl zurück
     *
     * @return int
     */
    public function count(): int
    {
        $uri = rtrim($this->endpoint, '/') . '/count';

        $response = $this->client->request($uri, 'GET', $this->filters);

        return (int) ($response['body']['result'] ?? 0);
    }

    /**
     * Löscht ein Objekt per ID
     */
    public function delete(int $id): bool
    {
        return $this->client->delete($this->endpoint, $id);
    }

    /**
     * Legt ein neues Objekt an
     */
    public function create(array $data): array
    {
        return $this->client->post($this->endpoint, $data);
    }

    /**
     * Aktualisiert ein Objekt – benötigt 'id' im Array
     */
    public function update(array $data): array
    {
        if (!isset($data['id']))
        {
            throw new \InvalidArgumentException("Fehlende ID im Datenarray für Update.");
        }

        $uri = "{$this->endpoint}/id/{$data['id']}";
        return $this->client->put($uri, $data);
    }

    /**
     * Speichert ein Objekt: update bei ID, sonst create
     */
    public function save(array $data): array
    {
        return isset($data['id'])
            ? $this->update($data)
            : $this->create($data);
    }

    /**
     * Fügt ein Feld zur Sortierung aufsteigend hinzu.
     *
     * @param string $field
     * @return static
     */
    public function orderAsc(string $field): static
    {
        $this->orderFields[] = $field;
        return $this;
    }

    /**
     * Fügt ein Feld zur Sortierung absteigend hinzu.
     *
     * @param string $field
     * @return static
     */
    public function orderDesc(string $field): static
    {
        $this->orderFields[] = '-' . $field;
        return $this;
    }

    /**
     * Setzt eine manuelle Reihenfolge mit beliebigen Feldern.
     * Beispiel: orderBy('lastModifiedDate', 'desc'])
     *
     * @param string $field
     * optional @param string $name
     * @return static
     */
    public function orderBy(string $field, $direction = 'asc'): static
    {
        if (strtolower($direction) === 'desc') 
        {
            return $this->orderDesc ($field);
        } 
        else 
        {
            return $this->orderAsc($field);
        }
    }

}
