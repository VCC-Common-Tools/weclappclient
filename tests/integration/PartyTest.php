<?php

namespace WeclappClient\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WeclappClient\Core\WeclappClient;
use Dotenv\Dotenv;

/**
 * Integrationstests gegen ein echtes Weclapp-Testsystem.
 *
 * Hinweis: In der API v2 existiert der frühere Endpunkt /customer nicht mehr –
 * Kunden sind "parties" mit dem Flag customer = true. Diese Tests laufen daher
 * gegen /party. Sie benötigen gültige Credentials in tests/.env
 * (WCLP_TEST_SUBDOMAIN, WCLP_TEST_API_KEY) und werden nur in der
 * "integration"-Testsuite ausgeführt.
 */
final class PartyTest extends TestCase
{
    private WeclappClient $client;

    protected function setUp(): void
    {
        $envPath = __DIR__ . '/..';
        if (!file_exists($envPath . '/.env'))
        {
            $this->markTestSkipped('tests/.env mit Testsystem-Credentials nicht vorhanden.');
        }

        $dotenv = Dotenv::createImmutable($envPath);
        $dotenv->load();

        $this->client = new WeclappClient(
            $_ENV['WCLP_TEST_SUBDOMAIN'],
            $_ENV['WCLP_TEST_API_KEY']
        );
    }

    public function testCountAndLimit(): void
    {
        $count = $this->client->query('/party')
            ->whereEq('customer', 'true')
            ->whereNe('customerNumber', 'ANONYMOUS_DEBITOR')
            ->count();
        $this->assertGreaterThan(5, $count, 'Das Testsystem sollte mehr als 5 Kunden enthalten.');

        $result = $this->client->query('/party')
            ->whereEq('customer', 'true')
            ->whereNe('customerNumber', 'ANONYMOUS_DEBITOR')
            ->limit(5)
            ->all();
        $this->assertIsArray($result);
        $this->assertCount(5, $result, 'limit(5) muss exakt 5 Elemente liefern.');
    }

    public function testGetSingleParty(): void
    {
        // ANONYMOUS_DEBITOR ist ein System-Dummy und wird bewusst ausgeschlossen.
        $first = $this->client->query('/party')
            ->whereEq('customer', 'true')
            ->whereNe('customerNumber', 'ANONYMOUS_DEBITOR')
            ->first();
        $this->assertIsArray($first);
        $this->assertArrayHasKey('id', $first);

        $party = $this->client->query('/party')->get($first['id']);
        $this->assertEquals($first['id'], $party['id']);
    }

    public function testCreateUpdateAndDeleteParty(): void
    {
        // --- CREATE ---
        $data = [
            'partyType' => 'ORGANIZATION',
            'company' => 'Weclapp-Client Integrationstest',
            'customer' => true,
        ];

        $created = $this->client->query('/party')->create($data);
        $this->assertArrayHasKey('id', $created, 'Create muss eine ID liefern.');
        $this->assertTrue((bool) $created['customer']);

        $id = $created['id'];

        try
        {
            // --- UPDATE ---
            // Vollständigen Datensatz laden (wegen Optimistic Locking / version).
            $full = $this->client->query('/party')->get($id);
            $full['company'] = 'Weclapp-Client Integrationstest (aktualisiert)';

            $updated = $this->client->query('/party')->update($full);
            $this->assertEquals('Weclapp-Client Integrationstest (aktualisiert)', $updated['company']);
        }
        finally
        {
            // --- DELETE (immer aufräumen) ---
            $success = $this->client->query('/party')->delete($id);
            $this->assertTrue($success, 'Der Testdatensatz muss wieder gelöscht werden.');
        }
    }
}
