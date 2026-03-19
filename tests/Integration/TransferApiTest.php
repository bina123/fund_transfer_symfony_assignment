<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TransferApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Clean tables
        $this->em->getConnection()->executeStatement('DELETE FROM transfers');
        $this->em->getConnection()->executeStatement('DELETE FROM accounts');
        $this->em->getConnection()->executeStatement('ALTER TABLE accounts AUTO_INCREMENT = 1');
    }

    private function createAccount(string $currency, int $balance): Account
    {
        $account = new Account($currency, $balance);
        $this->em->persist($account);
        $this->em->flush();

        return $account;
    }

    public function testSuccessfulTransfer(): void
    {
        $from = $this->createAccount('EUR', 100_00);
        $to = $this->createAccount('EUR', 50_00);

        $this->client->jsonRequest('POST', '/api/v1/transfers', [
            'from_account_id' => $from->getId(),
            'to_account_id' => $to->getId(),
            'amount' => 30_00,
            'currency' => 'EUR',
            'idempotency_key' => 'test-transfer-001',
        ]);

        $response = $this->client->getResponse();
        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('completed', $data['status']);
        $this->assertEquals(30_00, $data['amount']);
        $this->assertEquals('EUR', $data['currency']);

        // Verify balances
        $this->em->clear();
        $fromRefreshed = $this->em->find(Account::class, $from->getId());
        $toRefreshed = $this->em->find(Account::class, $to->getId());

        $this->assertEquals(70_00, $fromRefreshed->getBalance());
        $this->assertEquals(80_00, $toRefreshed->getBalance());
    }

    public function testInsufficientFunds(): void
    {
        $from = $this->createAccount('EUR', 10_00);
        $to = $this->createAccount('EUR', 50_00);

        $this->client->jsonRequest('POST', '/api/v1/transfers', [
            'from_account_id' => $from->getId(),
            'to_account_id' => $to->getId(),
            'amount' => 50_00,
            'currency' => 'EUR',
            'idempotency_key' => 'test-insufficient',
        ]);

        $response = $this->client->getResponse();
        $this->assertEquals(422, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('insufficient_funds', $data['error']);
    }

    public function testCurrencyMismatch(): void
    {
        $from = $this->createAccount('EUR', 100_00);
        $to = $this->createAccount('USD', 50_00);

        $this->client->jsonRequest('POST', '/api/v1/transfers', [
            'from_account_id' => $from->getId(),
            'to_account_id' => $to->getId(),
            'amount' => 30_00,
            'currency' => 'EUR',
            'idempotency_key' => 'test-currency-mismatch',
        ]);

        $response = $this->client->getResponse();
        $this->assertEquals(422, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('currency_mismatch', $data['error']);
    }

    public function testSelfTransfer(): void
    {
        $account = $this->createAccount('EUR', 100_00);

        $this->client->jsonRequest('POST', '/api/v1/transfers', [
            'from_account_id' => $account->getId(),
            'to_account_id' => $account->getId(),
            'amount' => 30_00,
            'currency' => 'EUR',
            'idempotency_key' => 'test-self-transfer',
        ]);

        $response = $this->client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('validation_failed', $data['error']);
    }

    public function testIdempotency(): void
    {
        $from = $this->createAccount('EUR', 100_00);
        $to = $this->createAccount('EUR', 50_00);

        $payload = [
            'from_account_id' => $from->getId(),
            'to_account_id' => $to->getId(),
            'amount' => 25_00,
            'currency' => 'EUR',
            'idempotency_key' => 'test-idempotent',
        ];

        // First request
        $this->client->jsonRequest('POST', '/api/v1/transfers', $payload);
        $this->assertEquals(201, $this->client->getResponse()->getStatusCode());
        $first = json_decode($this->client->getResponse()->getContent(), true);

        // Second request with same idempotency key
        $this->client->jsonRequest('POST', '/api/v1/transfers', $payload);
        $this->assertEquals(201, $this->client->getResponse()->getStatusCode());
        $second = json_decode($this->client->getResponse()->getContent(), true);

        // Same transfer returned
        $this->assertEquals($first['id'], $second['id']);

        // Balance only changed once
        $this->em->clear();
        $fromRefreshed = $this->em->find(Account::class, $from->getId());
        $this->assertEquals(75_00, $fromRefreshed->getBalance());
    }

    public function testAccountNotFound(): void
    {
        $from = $this->createAccount('EUR', 100_00);

        $this->client->jsonRequest('POST', '/api/v1/transfers', [
            'from_account_id' => $from->getId(),
            'to_account_id' => 99999,
            'amount' => 10_00,
            'currency' => 'EUR',
            'idempotency_key' => 'test-not-found',
        ]);

        $response = $this->client->getResponse();
        $this->assertEquals(404, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('account_not_found', $data['error']);
    }

    public function testInvalidPayload(): void
    {
        $this->client->jsonRequest('POST', '/api/v1/transfers', []);

        $response = $this->client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('validation_failed', $data['error']);
        $this->assertNotEmpty($data['details']);
    }

    public function testNegativeAmount(): void
    {
        $from = $this->createAccount('EUR', 100_00);
        $to = $this->createAccount('EUR', 50_00);

        $this->client->jsonRequest('POST', '/api/v1/transfers', [
            'from_account_id' => $from->getId(),
            'to_account_id' => $to->getId(),
            'amount' => -10_00,
            'currency' => 'EUR',
            'idempotency_key' => 'test-negative',
        ]);

        $response = $this->client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testZeroAmount(): void
    {
        $from = $this->createAccount('EUR', 100_00);
        $to = $this->createAccount('EUR', 50_00);

        $this->client->jsonRequest('POST', '/api/v1/transfers', [
            'from_account_id' => $from->getId(),
            'to_account_id' => $to->getId(),
            'amount' => 0,
            'currency' => 'EUR',
            'idempotency_key' => 'test-zero',
        ]);

        $response = $this->client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testInvalidJson(): void
    {
        $this->client->request('POST', '/api/v1/transfers', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'not-json');

        $response = $this->client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());
    }
}
