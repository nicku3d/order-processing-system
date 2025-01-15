<?php

namespace App\MessageHandler;

use App\Entity\Order;
use App\Message\OrderMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class OrderMessageHandler
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function __invoke(OrderMessage $message)
    {
        $orderId = $message->getOrderId();
        $order = $this->entityManager->getRepository(Order::class)->find($orderId);

        if (!$order) {
            throw new \Exception('Order not found');
        }

        $order->setStatus('processed');
        $this->entityManager->flush();
    }
}
