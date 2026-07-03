<?php

namespace WeclappClient\Core;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
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
     * Maximale Anzahl automatischer Wiederholungen bei Rate-Limits (429)
     * und Verbindungsfehlern.
     */
    private int $maxRetries;

    /**
     * Basis-Wartezeit (Millisekunden) für den exponentiellen Backoff zwischen
     * Wiederholungsversuchen.
     */
    private int $retryDelayMs;

    /**
     * Konstruktor
     *
     * @param string $subdomain Die Weclapp-Subdomain
     * @param string $accessToken Der API-Token
     * @param ClientInterface|null $client Optionaler eigener Guzzle-Client
     * @param int $apiVersion API-Version (1 oder 2, Standard: 2)
     * @param array $options Optionale HTTP-Einstellungen:
     *                       - timeout (float, Sekunden, Standard 30) – Gesamt-Timeout einer Anfrage
     *                       - connect_timeout (float, Sekunden, Standard 10) – Verbindungs-Timeout
     *                       - max_retries (int, Standard 3) – Wiederholungen bei 429/Verbindungsfehler
     *                       - retry_delay_ms (int, Standard 1000) – Basis-Wartezeit für Backoff
     *                       Hinweis: timeout/connect_timeout gelten nur für den intern
     *                       erzeugten Client, nicht für einen injizierten $client.
     *
     * @throws \InvalidArgumentException wenn eine ungültige API-Version angegeben wird
     */
    public function __construct(string $subdomain, string $accessToken, ?ClientInterface $client = null, int $apiVersion = 2, array $options = [])
    {
        // Validiere API-Version
        if (!in_array($apiVersion, [1, 2], true))
        {
            throw new \InvalidArgumentException("Ungültige API-Version: {$apiVersion}. Erlaubt sind 1 oder 2.");
        }

        $this->apiVersion = $apiVersion;
        $this->apiBaseUrl = "https://{$subdomain}.weclapp.com/webapp/api/v{$apiVersion}/";
        $this->accessToken = $accessToken;

        $this->maxRetries = max(0, (int) ($options['max_retries'] ?? 3));
        $this->retryDelayMs = max(0, (int) ($options['retry_delay_ms'] ?? 1000));

        $this->client = $client ?? new Client([
            'timeout' => $options['timeout'] ?? 30,
            'connect_timeout' => $options['connect_timeout'] ?? 10,
        ]);
    }

    /**
     * Führt eine HTTP-Anfrage über den Guzzle-Client aus und wiederholt sie bei
     * Rate-Limits (HTTP 429) und Verbindungsfehlern mit exponentiellem Backoff.
     *
     * Bewusst werden NUR 429 und reine Verbindungsfehler wiederholt – generische
     * 5xx-Fehler werden nicht automatisch erneut gesendet, um bei nicht-idempotenten
     * Operationen (z. B. POST) keine doppelten Datensätze zu erzeugen.
     *
     * @throws ConnectException wenn nach allen Versuchen die Verbindung scheitert
     * @throws RequestException bei nicht wiederholbaren bzw. endgültigen Fehlern
     */
    private function sendWithRetry(string $method, string $url, array $options): ResponseInterface
    {
        $attempt = 0;

        while (true)
        {
            try
            {
                return $this->client->request($method, $url, $options);
            }
            catch (ConnectException $e)
            {
                if ($attempt >= $this->maxRetries)
                {
                    throw $e;
                }

                $this->waitBeforeRetry(null, $attempt);
                $attempt++;
            }
            catch (RequestException $e)
            {
                $status = $e->getResponse()?->getStatusCode() ?? 0;

                // Nur echte Rate-Limits (429) werden wiederholt.
                if ($status !== 429 || $attempt >= $this->maxRetries)
                {
                    throw $e;
                }

                $this->waitBeforeRetry($e->getResponse(), $attempt);
                $attempt++;
            }
        }
    }

    /**
     * Wartet vor einem erneuten Versuch. Berücksichtigt den Retry-After-Header
     * (Sekunden) falls vorhanden, andernfalls exponentiellen Backoff.
     */
    private function waitBeforeRetry(?ResponseInterface $response, int $attempt): void
    {
        $delayMs = $this->retryDelayMs * (2 ** $attempt);

        if ($response !== null && $response->hasHeader('Retry-After'))
        {
            $retryAfter = (int) $response->getHeaderLine('Retry-After');
            if ($retryAfter > 0)
            {
                // Auf sinnvolle Obergrenze begrenzen (60s)
                $delayMs = min($retryAfter, 60) * 1000;
            }
        }

        if ($delayMs > 0)
        {
            usleep($delayMs * 1000);
        }
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
        
        // Füge Query-Parameter zur URL hinzu für Debugging
        if (!empty($queryParams)) {
            $urlWithParams = $url . '?' . http_build_query($queryParams);
            $this->lastUrl = "$method $urlWithParams";
        } else {
            $this->lastUrl = "$method $url";
        }

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
            $response = $this->sendWithRetry($method, $url, $options);

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
            $this->captureErrorResponse($e->getResponse());
            throw WeclappApiException::fromRequestException($e);
        }
    }

    /**
     * Speichert eine Fehler-Response in $lastResponse, damit getLastResponse()
     * und getLastErrorMessage() auch nach einer geworfenen Exception den
     * tatsächlichen Fehler widerspiegeln (statt der letzten Erfolgs-Antwort).
     */
    private function captureErrorResponse(?ResponseInterface $response): void
    {
        if ($response === null)
        {
            return;
        }

        $stream = $response->getBody();
        if ($stream->isSeekable())
        {
            $stream->rewind();
        }

        $body = json_decode((string) $stream, true) ?: [];

        // Stream zurückspulen, damit nachgelagerte Leser (z. B. die Exception-Fabrik)
        // den Inhalt weiterhin lesen können.
        if ($stream->isSeekable())
        {
            $stream->rewind();
        }

        $this->lastResponse = [
            'body' => $body,
            'meta' => [
                'status_code' => $response->getStatusCode(),
                'headers' => $response->getHeaders()
            ]
        ];
    }

    /**
     * Einstiegspunkt für Abfragen via QueryBuilder
     *
     * @param string $endpoint z. B. /article, /salesOrder
     * @return QueryBuilder
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
    public function binaryRequest(string $endpoint, string $method = 'GET', array $queryParams = [], ?string $binaryData = null, ?string $contentType = null, bool $asBase64 = false): string|array
    {
        $url = "{$this->apiBaseUrl}{$endpoint}";
        
        // Setze letzte URL für Debugging
        $this->lastUrl = "$method $url";

        $options = [
            'headers' => [
                'AuthenticationToken' => $this->accessToken
            ],
            'query' => $queryParams
        ];

        // Konfiguriere Header je nach Methode
        if ($method === 'GET')
        {
            $options['headers']['Accept'] = '*/*'; // wichtig für Binärdaten
        }
        else
        {
            // Für Uploads
            if ($contentType)
            {
                $options['headers']['Content-Type'] = $contentType;
            }
            else
            {
                // Fallback für binäre Uploads ohne spezifischen Content-Type
                $options['headers']['Content-Type'] = '*/*';
            }
            
            if ($binaryData !== null)
            {
                $options['body'] = $binaryData;
            }
        }

        try
        {
            $response = $this->sendWithRetry($method, $url, $options);

            if ($method === 'GET')
            {
                // Download: Binärdaten zurückgeben
                $binaryContent = $response->getBody()->getContents();
                return $asBase64 ? base64_encode($binaryContent) : $binaryContent;
            }
            else
            {
                // Upload: JSON-Response zurückgeben
                $body = json_decode($response->getBody()->getContents(), true) ?? [];
                
                $meta = [
                    'status_code' => $response->getStatusCode(),
                    'headers' => $response->getHeaders()
                ];
                
                return $this->lastResponse = ['body' => $body, 'meta' => $meta];
            }
        } 
        catch (RequestException $e)
        {
            // Zentrale Fehlerauswertung via Exception-Fabrik
            $this->captureErrorResponse($e->getResponse());
            throw WeclappApiException::fromRequestException($e);
        }
    }

    /**
     * Hilfsfunktion: Ermittelt die Dateiendung basierend auf dem MIME-Type
     * Unterstützt nur die von Weclapp erlaubten MIME-Types
     *
     * @param string $mimeType MIME-Type (z. B. application/pdf)
     * @return string Dateiendung ohne Punkt (z. B. pdf)
     */
    private function getFileExtensionFromMimeType(string $mimeType): string
    {
        $mimeToExtension = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png'
        ];
        
        return $mimeToExtension[$mimeType] ?? 'bin';
    }

    /**
     * Spezielle Methode für binäre Uploads (POST/PUT)
     *
     * @param string $endpoint z. B. /customerImage
     * @param string $binaryData Binärdaten zum Upload
     * @param string $method HTTP-Methode (POST oder PUT)
     * @param string|null $contentType Optional: Content-Type (z. B. image/jpeg)
     * @param array $queryParams Optional: Query-Parameter
     * @param string|null $fileName Optional: Dateiname (wird automatisch generiert wenn nicht angegeben)
     * @return array Upload-Response mit body und meta
     *
     * @throws WeclappApiException bei Kommunikationsfehlern
     */
    public function binaryUpload(string $endpoint, string $binaryData, string $method = 'POST', ?string $contentType = null, array $queryParams = [], ?string $fileName = null): array
    {
        // Generiere automatisch Dateinamen wenn nicht angegeben
        if ($fileName === null && $contentType) 
        {
            $extension = $this->getFileExtensionFromMimeType($contentType);
            $fileName = "uploaded-file.{$extension}";
        }
        
        // Füge Dateinamen zu Query-Parametern hinzu wenn vorhanden
        if ($fileName && !isset($queryParams['name'])) 
        {
            $queryParams['name'] = $fileName;
        }
        
        $result = $this->binaryRequest($endpoint, $method, $queryParams, $binaryData, $contentType);
        
        // binaryRequest gibt bei Uploads bereits ein Array zurück
        return is_array($result) ? $result : ['body' => [], 'meta' => ['status_code' => 200]];
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
