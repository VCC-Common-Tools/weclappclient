<?php

namespace WeclappClient\Tests\Unit;

use GuzzleHttp\Psr7\Response;

final class QueryBuilderTest extends MockClientTestCase
{
    public function testFirstReturnsFirstElement(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode(['result' => [['id' => 1], ['id' => 2]]])),
        ]);

        $first = $client->query('/customer')->whereEq('customerNumber', 'C-1')->first();

        $this->assertSame(['id' => 1], $first);

        // first() begrenzt auf eine Seite mit pageSize=1.
        $query = $this->lastRequest()->getUri()->getQuery();
        $this->assertStringContainsString('pageSize=1', $query);
        $this->assertStringContainsString('customerNumber-eq=C-1', $query);
    }

    public function testGetLoadsSingleEntityById(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode(['id' => 55, 'company' => 'ACME'])),
        ]);

        $entity = $client->query('/customer')->get(55);

        $this->assertSame(55, $entity['id']);
        $this->assertStringContainsString('/customer/id/55', (string) $this->lastRequest()->getUri());
    }

    public function testCountIncludesOrFilters(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode(['result' => 17])),
        ]);

        $count = $client->query('/customer')
            ->whereEq('status', 'ACTIVE')
            ->orWhereEq('company', 'ACME')
            ->count();

        $this->assertSame(17, $count);

        $uri = (string) $this->lastRequest()->getUri();
        $this->assertStringContainsString('/customer/count', $uri);

        $query = $this->lastRequest()->getUri()->getQuery();
        // Der frühere Bug: OR-Filter fehlten in der count()-Abfrage.
        $this->assertStringContainsString('status-eq=ACTIVE', $query);
        $this->assertStringContainsString('or-company-eq=ACME', $query);
    }

    public function testLimitCapsPageSize(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode(['result' => $this->makeItems(5)])),
        ]);

        $result = $client->query('/customer')->limit(5)->all();

        $this->assertCount(5, $result);
        $this->assertSame(1, $this->requestCount(), 'Bei limit(5) darf nur eine Seite angefragt werden.');

        $query = $this->lastRequest()->getUri()->getQuery();
        $this->assertStringContainsString('pageSize=5', $query);
    }

    public function testAllAutoPaginatesUntilShortPage(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode(['result' => $this->makeItems(100)])),
            new Response(200, [], json_encode(['result' => $this->makeItems(30)])),
        ]);

        $result = $client->query('/customer')->all();

        $this->assertCount(130, $result);
        $this->assertSame(2, $this->requestCount(), 'Es sollten genau zwei Seiten abgefragt werden.');
    }

    public function testLimitStopsAcrossPages(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode(['result' => $this->makeItems(100)])),
            new Response(200, [], json_encode(['result' => $this->makeItems(100)])),
        ]);

        // limit(150) -> pageSize bleibt 100, aber nach 2 Seiten wird auf 150 gekürzt.
        $result = $client->query('/customer')->limit(150)->all();

        $this->assertCount(150, $result);
        $this->assertSame(2, $this->requestCount());
    }

    public function testSortAndPropertiesInQuery(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode(['result' => []])),
        ]);

        $client->query('/customer')
            ->orderDesc('createdDate')
            ->properties(['id', 'company'])
            ->getResult();

        $query = $this->lastRequest()->getUri()->getQuery();
        $this->assertStringContainsString('sort=-createdDate', $query);
        $this->assertStringContainsString('properties=id%2Ccompany', $query); // "id,company" URL-kodiert
    }

    /**
     * Erzeugt eine Liste von Dummy-Datensätzen.
     */
    private function makeItems(int $n): array
    {
        return array_map(static fn (int $i): array => ['id' => $i], range(1, $n));
    }
}
