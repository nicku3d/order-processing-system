<?php

namespace App\tests\Controller;

use App\Entity\Order;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OrderApiControllerTest extends WebTestCase
{
    public function testCreateOrder(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/orders', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'customerName' => 'Jan Kowalski',
            'email' => 'jan.kowalski@example.com',
            'products' => [
                ['id' => 1, 'quantity' => 2],
                ['id' => 3, 'quantity' => 1]
            ],
            'address' => 'ul. Warszawska 10, Kraków'
        ]));

        $this->assertResponseStatusCodeSame(201);
        $this->assertJson($client->getResponse()->getContent());

        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Order created successfully', $responseData['message']);
        $this->assertArrayHasKey('orderId', $responseData);
    }

    public function testGetOrder(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/orders', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'customerName' => 'Jan Kowalski',
            'email' => 'jan.kowalski@example.com',
            'products' => [
                ['id' => 1, 'quantity' => 2],
                ['id' => 3, 'quantity' => 1]
            ],
            'address' => 'ul. Warszawska 10, Kraków'
        ]));
        $this->assertResponseStatusCodeSame(201);

        // Pobierz ID zamówienia
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $orderId = $responseData['orderId'];

        // Pobierz szczegóły zamówienia
        $client->request('GET', '/api/orders/' . $orderId);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJson($client->getResponse()->getContent());

        $orderData = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals($orderId, $orderData['id']);
        $this->assertEquals('Jan Kowalski', $orderData['customerName']);
        $this->assertEquals('jan.kowalski@example.com', $orderData['email']);
    }

    public function testGetOrders(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/orders');
        $this->assertResponseStatusCodeSame(200);
        $this->assertJson($client->getResponse()->getContent());

        $orders = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($orders);

        if (count($orders) > 0) {
            $this->assertArrayHasKey('id', $orders['data'][0]);
            $this->assertArrayHasKey('customerName', $orders['data'][0]);
            $this->assertArrayHasKey('email', $orders['data'][0]);
            $this->assertArrayHasKey('status', $orders['data'][0]);
        }
    }

    public function testGetOrdersWithPagination(): void
    {
        $client = static::createClient();

        // Use the container to get the Doctrine EntityManager
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Count existing orders in the database
        $existingOrderCount = $entityManager->getRepository(Order::class)->count([]);

        // Add 15 new orders to the database
        for ($i = 1; $i <= 15; $i++) {
            $order = new Order();
            $order->setCustomerName("Customer $i");
            $order->setEmail("customer$i@example.com");
            $order->setProducts([['id' => $i, 'quantity' => 1]]);
            $order->setAddress("Address $i");
            $order->setStatus('pending');
            $entityManager->persist($order);
        }
        $entityManager->flush();

        // Calculate the total number of orders
        $totalOrders = $existingOrderCount + 15;

        // Calculate expected pagination values
        $limit = 5; // Items per page
        $totalPages = (int) ceil($totalOrders / $limit);

        // Test the first page
        $client->request('GET', '/api/orders?page=1&limit=' . $limit);
        $this->assertResponseStatusCodeSame(200);

        $responseData = json_decode($client->getResponse()->getContent(), true);

        // Verify response structure and pagination data
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('meta', $responseData);
        $this->assertCount(min($limit, $totalOrders), $responseData['data']); // Limit orders if fewer than $limit exist

        $this->assertEquals(1, $responseData['meta']['currentPage']);
        $this->assertEquals($totalPages, $responseData['meta']['totalPages']);
        $this->assertEquals($totalOrders, $responseData['meta']['totalItems']);
        $this->assertEquals($limit, $responseData['meta']['itemsPerPage']);

        // Test the second page (if applicable)
        if ($totalPages > 1) {
            $client->request('GET', '/api/orders?page=2&limit=' . $limit);
            $this->assertResponseStatusCodeSame(200);

            $responseData = json_decode($client->getResponse()->getContent(), true);

            $this->assertArrayHasKey('data', $responseData);
            $this->assertCount(
                $totalOrders > $limit ? $limit : 0,
                $responseData['data']
            ); // Ensure correct number of items on the second page
            $this->assertEquals(2, $responseData['meta']['currentPage']);
        }

        // Test the last page
        $lastPage = $totalPages;
        $client->request('GET', '/api/orders?page=' . $lastPage . '&limit=' . $limit);
        $this->assertResponseStatusCodeSame(200);

        $responseData = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('data', $responseData);
        $remainingItems = $totalOrders % $limit;
        $this->assertCount($remainingItems === 0 ? $limit : $remainingItems, $responseData['data']);
        $this->assertEquals($lastPage, $responseData['meta']['currentPage']);
    }
}
