<?php

namespace WeclappClient\Core;

use WeclappClient\Core\WeclappClient;
use \WeclappClient\Core\AbstractBaseQueryBuilder;

/**
 * Der QueryBuilder dient zur flexiblen Abfrage von Weclapp-Ressourcen.
 * Unterstützt Filter, Sortierung, Pagination und Mehrseitenabfragen.
 * Gibt rohe Datenarrays zurück – keine Response-Wrapper.
 */
class QueryBuilder extends AbstractBaseQueryBuilder
{
    

    /**
     * Konstruktor
     */
    public function __construct(WeclappClient $client, string $endpoint)
    {
        $this->client = $client;
        $this->endpoint = $endpoint;
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
        // Nutze pageSize statt maxTotal für einzelne API-Anfragen
        $this->page(1, 1);
        $result = $this->getResult();

        return $result[0] ?? null;
    }

    /**
     * Holt alle Ergebnisse – mit Paginierung bei aktiviertem nolimit()
     */
    public function all(): array
    {
        // Wenn forceAll nicht gesetzt ist, wird die normale Paginierung verwendet
        if (!$this->forceAll)
        {
            return $this->getResult() ?? [];
        }

        $all = [];
        $page = 1;
        $pageSize = $this->options['pageSize'] ?? 100;
        $maxTotal = $this->maxTotal;

        do
        {
            // Setze aktuelle Seite
            $this->page($page, $pageSize);

            // Hole Ergebnisse der aktuellen Seite
            $result = $this->getResult() ?? [];

            // Wenn leer: Abbruch
            if (empty($result)) {
                break;
            }

            // Füge zur Gesamtliste hinzu
            $all = array_merge($all, $result);

            // Bei gesetztem Maximal-Limit: ggf. abbrechen
            if ($maxTotal !== null and count($all) >= $maxTotal) {
                return array_slice($all, 0, $maxTotal);
            }

            $page++;

        } while (count($result) === $pageSize);

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


}
