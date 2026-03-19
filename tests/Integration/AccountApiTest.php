<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AccountApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->getConnection()->executeStatement('DELETE FROM transfers');
        $this->em->getConnection()->executeStatement('DELETE FROM accounts');
    }

    public function testGetAccount(): void
    {
        $account = new Account('EUR', 100_00);
        $this->em->persist($account);
        $this->em->flush();

        $this->client->request('GET', '/api/v1/accounts/' . $account->getId());

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals($account->getId(), $data['id']);
        $this->assertEquals('EUR', $data['currency']);
        $this->assertEquals(100_00, $data['balance']);
        $this->assertEquals('100.00', $data['balanceFormatted']);
    }

    public function testAccountNotFound(): void
    {
        $this->client->request('GET', '/api/v1/accounts/99999');

        $response = $this->client->getResponse();
        $this->assertEquals(404, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('account_not_found', $data['error']);
    }

    public function testBalanceAfterTransfer(): void
    {
        $from = new Account('EUR', 100_00);
        $to = new Account('EUR', 50_00);
        $this->em->persist($from);
        $this->em->persist($to);
        $this->em->flush();

        // Perform transfer
        $this->client->jsonRequest('POST', '/api/v1/transfers', [
            'from_account_id' => $from->getId(),
            'to_account_id' => $to->getId(),
            'amount' => 25_00,
            'currency' => 'EUR',
            'idempotency_key' => 'account-test-transfer',
        ]);
        $this->assertEquals(201, $this->client->getResponse()->getStatusCode());

        // Check from account
        $this->client->request('GET', '/api/v1/accounts/' . $from->getId());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals(75_00, $data['balance']);
        $this->assertEquals('75.00', $data['balanceFormatted']);

        // Check to account
        $this->client->request('GET', '/api/v1/accounts/' . $to->getId());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals(75_00, $data['balance']);
        $this->assertEquals('75.00', $data['balanceFormatted']);
    }
}
