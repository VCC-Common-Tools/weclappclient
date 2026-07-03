<?php

namespace WeclappClient\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use WeclappClient\Core\WeclappClient;

/**
 * Basisklasse für Unit-Tests mit gemocktem Guzzle-HTTP-Client.
 * Erlaubt das Vorgeben von Responses und das Inspizieren der gesendeten Requests,
 * ohne die echte Weclapp-API zu kontaktieren.
 */
abstract class MockClientTestCase extends TestCase
{
    /**
     * Aufzeichnung aller gesendeten Requests/Responses (Guzzle-History).
     */
    protected array $history = [];

    /**
     * Erstellt einen WeclappClient mit gemocktem Client.
     *
     * @param array $responses Vorgegebene Responses/Exceptions (in Reihenfolge)
     * @param array $options Optionen für den WeclappClient (Standard: retry_delay_ms = 0)
     */
    protected function makeClient(array $responses, array $options = []): WeclappClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);

        $this->history = [];
        $stack->push(Middleware::history($this->history));

        $guzzle = new Client(['handler' => $stack]);

        // retry_delay_ms = 0 hält Tests schnell (kein echtes Warten beim Backoff).
        return new WeclappClient('test', 'token', $guzzle, 2, array_merge(['retry_delay_ms' => 0], $options));
    }

    /**
     * Gibt den zuletzt gesendeten Request zurück.
     */
    protected function lastRequest(): RequestInterface
    {
        return end($this->history)['request'];
    }

    /**
     * Anzahl der insgesamt gesendeten Requests.
     */
    protected function requestCount(): int
    {
        return count($this->history);
    }
}
