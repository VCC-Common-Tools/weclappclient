<?php

namespace WeclappClient;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

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
     */
    public function __construct(string $subdomain, string $accessToken, ClientInterface $client = null)
    {
        $this->apiBaseUrl = "https://{$subdomain}.weclapp.com/webapp/api/v1/";
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

        // HTTP-Optionen vorbereiten
        $options = [
            'headers' => [
                'AuthenticationToken' => $this->accessToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'query' => $queryParams
        ];

        if (!empty($bodyParams))
        {
            $options['json'] = $bodyParams;
        }

        try
        {
            // Anfrage senden
            $response = $this->client->request($method, $url, $options);

            // Antwort verarbeiten
            $body = json_decode($response->getBody()->getContents(), true) ?? [];

            $meta = [
                'status_code' => $response->getStatusCode(),
                'headers' => $response->getHeaders()
            ];

            return $this->lastResponse = ['body' => $body, 'meta' => $meta];
        }
        catch (RequestException $e)
        {
            $apiResponse = $e->getResponse();
            $statusCode = $apiResponse?->getStatusCode() ?? 500;
            $url = $e->getRequest()->getUri()->__toString();

            $body = $apiResponse
                ? json_decode($apiResponse->getBody()->getContents(), true)
                : [];

            $message = $body['message'] ?? $body['detail'] ?? $e->getMessage();

            $error = [
                'error' => true,
                'status_code' => $statusCode,
                'url' => $url,
                'message' => $message
            ] + $body;

            throw new \WeclappClient\Exception\WeclappApiException(
                "Weclapp API Error [{$statusCode}]: {$message}",
                $statusCode,
                $error
            );
        }

    }

    /**
     * Einstiegspunkt für Abfragen via QueryBuilder
     *
     * @param string $endpoint z. B. /article, /salesOrder
     * @return Query\QueryBuilder
     */
    public function query(string $endpoint): Query\QueryBuilder
    {
        $endpoint = trim($endpoint, '/');
        return new Query\QueryBuilder($this, $endpoint);
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
     * Spezielle Methode zum Abruf binärer Daten (z. B. Dateien, Bilder)
     *
     * @param string $endpoint z. B. /customerImage
     * @param array $queryParams Optional: Query-Parameter
     * @param bool $asBase64 Optional: Base64-kodierte Rückgabe
     * @return string Binärdaten oder Base64-String
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
        }
        catch (RequestException $e)
        {
            // Fehler beim Abruf ignorieren, Rückgabe ist leer
            return '';
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
            catch (\WeclappClient\Exception\WeclappApiException $e)
            {
                return false;
            }
        }


}
