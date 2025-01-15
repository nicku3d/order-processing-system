<?php

namespace App\Message;

final class OrderMessage
{
     public function __construct(
         private readonly int $orderId,
     ) {}

    public function getOrderId(): int
    {
        return $this->orderId;
    }
}
