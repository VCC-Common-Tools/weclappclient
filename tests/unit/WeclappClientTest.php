<?php

namespace WeclappClient\Tests\Unit;

use GuzzleHttp\Psr7\Response;
use WeclappClient\Core\WeclappClient;
use WeclappClient\Exception\WeclappApiException;
use WeclappClient\Exception\WeclappErrorCode;

final class WeclappClientTest extends MockClientTestCase
{
    public function testConstructorRejectsInvalidApiVersion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WeclappClient('sub', 'token', null, 3);
    }

    public function testRequestReturnsBodyAndMeta(): void
    {
        $client = $this->makeClient([
            new Response(200, ['X-Test' => 'yes'], json_encode(['result' => [['id' => 1]]])),
        ]);

        $response = $client->request('customer');

        $this->assertSame([['id' => 1]], $response['body']['result']);
        $this->assertSame(200, $response['meta']['status_code']);
        $this->assertArrayHasKey('X-Test', $response['meta']['headers']);
    }

    public function testGetReturnsOnlyBody(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode(['result' => [['id' => 42]]])),
        ]);

        $body = $client->get('customer');

        $this->assertSame([['id' => 42]], $body['result']);
    }

    public function testPostSendsJsonBodyAndAuthHeader(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode(['id' => 7])),
        ]);

        $client->post('customer', ['company' => 'ACME']);

        $request = $this->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('token', $request->getHeaderLine('AuthenticationToken'));
        $this->assertSame(['company' => 'ACME'], json_decode((string) $request->getBody(), true));
    }

    public function testDeleteReturnsTrueOn204(): void
    {
        $client = $this->makeClient([
            new Response(204, [], ''),
        ]);

        $this->assertTrue($client->delete('customer', 5));
    }

    public function testDeleteReturnsFalseOn404(): void
    {
        $client = $this->makeClient([
            new Response(404, [], json_encode(['message' => 'not found'])),
        ]);

        $this->assertFalse($client->delete('customer', 999));
    }

    public function testApiExceptionCarriesErrorCodeAndLastResponse(): void
    {
        $client = $this->makeClient([
            new Response(401, [], json_encode(['message' => 'Invalid token'])),
        ]);

        try
        {
            $client->get('customer');
            $this->fail('Expected WeclappApiException was not thrown.');
        }
        catch (WeclappApiException $e)
        {
            $this->assertSame(WeclappErrorCode::Unauthorized, $e->getErrorCode());
        }

        // Fehler-Response muss nun über getLastResponse()/getLastErrorMessage() sichtbar sein.
        $this->assertSame(401, $client->getLastResponse()['meta']['status_code']);
        $this->assertSame('[HTTP 401] Invalid token', $client->getLastErrorMessage());
    }

    public function testRetriesOn429ThenSucceeds(): void
    {
        $client = $this->makeClient([
            new Response(429, [], json_encode(['message' => 'rate limited'])),
            new Response(429, [], json_encode(['message' => 'rate limited'])),
            new Response(200, [], json_encode(['result' => 'ok'])),
        ], ['max_retries' => 3]);

        $body = $client->get('customer');

        $this->assertSame('ok', $body['result']);
        $this->assertSame(3, $this->requestCount(), 'Es sollten zwei Wiederholungen stattgefunden haben.');
    }

    public function testGivesUpAfterMaxRetriesOn429(): void
    {
        $client = $this->makeClient([
            new Response(429, [], json_encode(['message' => 'rate limited'])),
            new Response(429, [], json_encode(['message' => 'rate limited'])),
        ], ['max_retries' => 1]);

        try
        {
            $client->get('customer');
            $this->fail('Expected WeclappApiException was not thrown.');
        }
        catch (WeclappApiException $e)
        {
            $this->assertSame(WeclappErrorCode::TooManyRequests, $e->getErrorCode());
        }

        $this->assertSame(2, $this->requestCount(), 'Ein Versuch plus genau eine Wiederholung.');
    }

    public function testDoesNotRetryOn500(): void
    {
        // 5xx wird bewusst nicht wiederholt, um doppelte Schreibvorgänge zu vermeiden.
        $client = $this->makeClient([
            new Response(500, [], json_encode(['message' => 'server error'])),
        ], ['max_retries' => 3]);

        $this->expectException(WeclappApiException::class);

        try
        {
            $client->get('customer');
        }
        finally
        {
            $this->assertSame(1, $this->requestCount(), '5xx darf nicht wiederholt werden.');
        }
    }

    public function testBinaryRequestReturnsRawBytes(): void
    {
        $client = $this->makeClient([
            new Response(200, [], "\x89PNG\r\n binary"),
        ]);

        $data = $client->binaryRequest('document/id/1/download');

        $this->assertSame("\x89PNG\r\n binary", $data);
    }

    public function testGetLastUrlIncludesQuery(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode(['result' => []])),
        ]);

        $client->request('customer', 'GET', ['status-eq' => 'A']);

        $this->assertStringContainsString('GET ', $client->getLastUrl());
        $this->assertStringContainsString('status-eq=A', $client->getLastUrl());
    }
}
