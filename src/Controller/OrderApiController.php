<?php

namespace App\Controller;

use App\Entity\Order;
use App\Message\OrderMessage;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class OrderApiController extends AbstractController
{

    private EntityManagerInterface $entityManager;
    private MessageBusInterface $bus;

    public function __construct(EntityManagerInterface $entityManager, MessageBusInterface $bus)
    {
        $this->entityManager = $entityManager;
        $this->bus = $bus;
    }

    #[Route('/api/orders', name: 'create_order', methods: ['POST'])]
    public function createOrder(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['customerName'], $data['email'], $data['products'], $data['address'])) {
            return new JsonResponse(['error' => 'Invalid input data'], 400);
        }

        $order = new Order();
        $order->setCustomerName($data['customerName']);
        $order->setEmail($data['email']);
        $order->setProducts($data['products']);
        $order->setAddress($data['address']);

        $errors = $validator->validate($order);
        if (count($errors) > 0) {
            return new JsonResponse(['error' => (string) $errors], 400);
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->bus->dispatch(new OrderMessage($order->getId()));

        return new JsonResponse(['message' => 'Order created successfully', 'orderId' => $order->getId()], 201);
    }

    #[Route('/api/orders/{id}', name: 'get_order', methods: ['GET'])]
    public function getOrder(int $id): JsonResponse
    {
        $order = $this->entityManager->getRepository(Order::class)->find($id);

        if (!$order) {
            return new JsonResponse(['error' => 'Order not found'], 404);
        }

        return new JsonResponse([
            'id' => $order->getId(),
            'customerName' => $order->getCustomerName(),
            'email' => $order->getEmail(),
            'products' => $order->getProducts(),
            'address' => $order->getAddress(),
            'status' => $order->getStatus(),
            'createdAt' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $order->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/api/orders', name: 'get_orders', methods: ['GET'])]
    public function getOrders(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, (int) $request->query->get('limit', 10));

        $queryBuilder = $this->entityManager->getRepository(Order::class)->createQueryBuilder('o');
        $adapter = new QueryAdapter($queryBuilder);
        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage($limit);
        $pager->setCurrentPage($page);

        $orders = array_map(function (Order $order) {
            return [
                'id' => $order->getId(),
                'customerName' => $order->getCustomerName(),
                'email' => $order->getEmail(),
                'products' => $order->getProducts(),
                'address' => $order->getAddress(),
                'status' => $order->getStatus(),
                'createdAt' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }, iterator_to_array($pager->getCurrentPageResults()));

        return new JsonResponse([
            'data' => $orders,
            'meta' => [
                'currentPage' => $pager->getCurrentPage(),
                'totalPages' => $pager->getNbPages(),
                'totalItems' => $pager->getNbResults(),
                'itemsPerPage' => $pager->getMaxPerPage(),
            ],
        ]);
    }

}
