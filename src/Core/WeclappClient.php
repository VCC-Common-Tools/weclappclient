<?php

namespace WeclappClient\Core;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use WeclappClient\Exception\WeclappApiException;
use WeclappClient\Exception\WeclappErrorCode;

/**
 * Der zentrale WeclappClient zur Kommunikation mit der REST-API.
 * Stellt Funktionen für GET, POST, PUT, DELETE sowie Binär-Downloads bereit.
 * Arbeitet direkt mit Arrays – ohne eigene Response-Klassen.
 */
class WeclappClient
{
    /**
     * Basis-URL zur API, inklusive Subdomain
     */
    private string $apiBaseUrl;

    /**
     * Weclapp Authentication Token
     */
    private string $accessToken;

    /**
     * Guzzle HTTP-Client-Instanz
     */
    private ClientInterface $client;

    /**
     * API-Version (1 oder 2)
     */
    private int $apiVersion;

    /**
     * Letzter aufgerufener Endpunkt (inkl. Methode)
     */
    private string $lastUrl = '';

    /**
     * Letzte Antwort der API – bestehend aus body und meta
     */
    private array $lastResponse = [];

    /**
     * Konstruktor
     *
     * @param string $subdomain Die Weclapp-Subdomain
     * @param string $accessToken Der API-Token
     * @param ClientInterface|null $client Optionaler eigener Guzzle-Client
     * @param int $apiVersion API-Version (1 oder 2, Standard: 2)
     * 
     * @throws \InvalidArgumentException wenn eine ungültige API-Version angegeben wird
     */
    public function __construct(string $subdomain, string $accessToken, ClientInterface $client = null, int $apiVersion = 2)
    {
        // Validiere API-Version
        if (!in_array($apiVersion, [1, 2], true))
        {
            throw new \InvalidArgumentException("Ungültige API-Version: {$apiVersion}. Erlaubt sind 1 oder 2.");
        }

        $this->apiVersion = $apiVersion;
        $this->apiBaseUrl = "https://{$subdomain}.weclapp.com/webapp/api/v{$apiVersion}/";
        $this->accessToken = $accessToken;
        $this->client = $client ?? new Client();
    }

    /**
     * Allgemeine HTTP-Anfrage an die Weclapp API
     *
     * @param string $endpoint z. B. /customer
     * @param string $method HTTP-Methode (GET, POST, etc.)
     * @param array $queryParams Query-Parameter als Array
     * @param array $bodyParams Body-Daten als Array (bei POST/PUT)
     * @return array Enthält ['body' => …, 'meta' => …]
     */
    public function request(string $endpoint, string $method = 'GET', array $queryParams = [], array $bodyParams = []): array
    {
        $url = "{$this->apiBaseUrl}{$endpoint}";
        $this->lastUrl = "$method $url";

        $options = [
            'headers' => [
                'AuthenticationToken' => $this->accessToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'query' => $queryParams
        ];

        if (!empty($bodyParams)) {
            $options['json'] = $bodyParams;
        }

        try 
        {
            $response = $this->client->request($method, $url, $options);

            $body = json_decode($response->getBody()->getContents(), true) ?? [];

            $meta = [
                'status_code' => $response->getStatusCode(),
                'headers' => $response->getHeaders()
            ];

            return $this->lastResponse = ['body' => $body, 'meta' => $meta];
        } 
        catch (RequestException $e) 
        {
            // Schlägt fehlende Verbindung, 4xx/5xx, ungültige Token etc. ab
            throw WeclappApiException::fromRequestException($e);
        }
    }

    /**
     * Einstiegspunkt für Abfragen via QueryBuilder
     *
     * @param string $endpoint z. B. /article, /salesOrder
     * @return Query\QueryBuilder
     */
    public function query(string $endpoint): QueryBuilder
    {
        $endpoint = trim($endpoint, '/');
        return new QueryBuilder($this, $endpoint);
    }

    /**
     * Gibt den letzten angefragten Endpunkt (mit Methode) zurück
     *
     * @return string
     */
    public function getLastUrl(): string
    {
        return $this->lastUrl;
    }

    /**
     * Gibt die letzte empfangene API-Antwort zurück
     *
     * @return array
     */
    public function getLastResponse(): array
    {
        return $this->lastResponse;
    }

    /**
     * Gibt die letzte Fehlermeldung der API-Antwort zurück, falls vorhanden.
     */
    public function getLastErrorMessage(): ?string
    {
        $body = $this->lastResponse['body'] ?? [];
        $status = $this->lastResponse['meta']['status_code'] ?? null;

        $msg = $body['message'] ?? $body['detail'] ?? null;

        return $msg && $status
            ? "[HTTP $status] $msg"
            : $msg;
    }

    /**
     * Gibt die aktuelle API-Version zurück
     *
     * @return int
     */
    public function getApiVersion(): int
    {
        return $this->apiVersion;
    }


    /**
     * Spezielle Methode zum Abruf binärer Daten (z. B. Dateien, Bilder)
     *
     * @param string $endpoint z. B. /customerImage
     * @param array $queryParams Optional: Query-Parameter
     * @param bool $asBase64 Optional: Base64-kodierte Rückgabe
     * @return string Binärdaten oder Base64-String
     *
     * @throws WeclappApiException bei Kommunikationsfehlern
     */
    public function binaryRequest(string $endpoint, array $queryParams = [], bool $asBase64 = false): string
    {
        $url = "{$this->apiBaseUrl}{$endpoint}";

        $options = [
            'headers' => [
                'AuthenticationToken' => $this->accessToken,
                'Accept' => '*/*' // wichtig für Binärdaten
            ],
            'query' => $queryParams
        ];

        try 
        {
            $response = $this->client->request('GET', $url, $options);
            $binaryData = $response->getBody()->getContents();

            return $asBase64 ? base64_encode($binaryData) : $binaryData;
        } catch (RequestException $e) 
        {
            // Zentrale Fehlerauswertung via Exception-Fabrik
            throw WeclappApiException::fromRequestException($e);
        }
    }

        /**
         * GET-Request an API-Endpunkt
         */
        public function get(string $endpoint, array $params = []): array
        {
            return $this->request($endpoint, 'GET', $params)['body'] ?? [];
        }

        /**
         * POST-Request mit Daten
         */
        public function post(string $endpoint, array $data): array
        {
            return $this->request($endpoint, 'POST', [], $data)['body'] ?? [];
        }

        /**
         * PUT-Request mit Daten
         */
        public function put(string $endpoint, array $data): array
        {
            return $this->request($endpoint, 'PUT', [], $data)['body'] ?? [];
        }

        /**
         * DELETE-Request
         */
        public function delete(string $endpoint, int|string $id): bool
        {
            $uri = rtrim($endpoint, '/') . '/id/' . $id;

            try
            {
                $response = $this->request($uri, 'DELETE');

                // Erfolg nur bei 204 No Content
                return ($response['meta']['status_code'] ?? 0) === 204;
            }
            catch (WeclappApiException $e)
            {
                if ($e->getErrorCode() === WeclappErrorCode::NotFound)
                {
                    return false;
                }

                throw $e; // alle anderen weiterreichen
            }

        }


}
