<?php

use PHPUnit\Framework\TestCase;
use WeclappClient\Core\WeclappClient;
use Dotenv\Dotenv;

final class CustomerTest extends TestCase
{
    private WeclappClient $client;

    protected function setUp(): void
    {
        // .env laden
        $dotenv = Dotenv::createImmutable(__DIR__."/..");
        $dotenv->load();

        // WeclappClient initialisieren
        $this->client = new WeclappClient(
            $_ENV['WCLP_TEST_SUBDOMAIN'],
            $_ENV['WCLP_TEST_API_KEY']
        );

    }

    public function testGetAllCustomers(): void
    {
        // Prüfe zuerst die Gesamtanzahl
        $count = $this->client->query('/customer')->count();
        $this->assertGreaterThan(5, $count, '❌ Es müssen mindestens 6 Kunden vorhanden sein, um den Test sinnvoll auszuführen.');

        // Begrenzte Abfrage mit Limit
        $result = $this->client->query('/customer')->limit(5)->all();

        $this->assertIsArray($result);
        $this->assertCount(5, $result, '❌ Die Abfrage mit limit(5) hat nicht exakt 5 Elemente zurückgegeben.');
    }


    public function testGetSingleCustomer(): void
    {
        $result = $this->client->query('/customer')->first();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);

        $customer = $this->client->query('/customer')->get($result['id']);
        $this->assertEquals($result['id'], $customer['id']);
    }

    private function findCustomerByNumber(string $customerNumber): ?array
    {
        return $this->client
            ->query('/customer')
            ->whereEq('customerNumber', $customerNumber)
            ->first();
    }


    public function testCreateUpdateAndDeleteCustomer(): void
    {
        $customerNumber = 'C-TEST-1000';

        // Prüfen, ob der Kunde schon existiert
        $existing = $this->findCustomerByNumber($customerNumber);

        // --- CREATE ---
        if ($existing) {
            echo "\n⚠️  Kunde {$customerNumber} existiert bereits. Create wird übersprungen.\n";
            $created = $existing;
        } else {
            $data = [
                'customerNumber' => $customerNumber,
                'company' => 'Testfirma GmbH',
                'partyType' => 'ORGANIZATION',
                'customerType' => 'CUSTOMER',
                'companyLegalForm' => 'GMBH'
            ];
            $created = $this->client->query('/customer')->create($data);
            $this->assertArrayHasKey('id', $created);
            echo "\n✅ Kunde {$customerNumber} wurde erstellt.\n";
        }

        // --- UPDATE ---
        // Wichtig: zuerst vollständigen Datensatz laden
        $full = $this->client->query('/customer')->get($created['id']);
        $this->assertIsArray($full);

        $full['company'] = 'Updated GmbH';
        $updated = $this->client->query('/customer')->update($full);
        $this->assertEquals('Updated GmbH', $updated['company']);
        echo "\n✅ Kunde {$customerNumber} wurde aktualisiert.\n";

        // --- DELETE ---
        if (!$created || !isset($created['id'])) {
            echo "\n⚠️  Kein gültiger Kunde zum Löschen gefunden. Delete wird übersprungen.\n";
            return;
        }

        $success = $this->client->query('/customer')->delete($created['id']);
        $this->assertTrue($success);
        echo "\n✅ Kunde {$customerNumber} wurde gelöscht.\n";
    }

}
