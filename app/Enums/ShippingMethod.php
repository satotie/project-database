<?php

namespace App\Enums;

enum ShippingMethod: string
{
    case Standard = 'STANDARD';
    case Express = 'EXPRESS';
    case Pickup = 'PICKUP';

    public const int FREE_STANDARD_THRESHOLD = 3000;

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'จัดส่งแบบปกติ (2-4 วัน)',
            self::Express => 'จัดส่งด่วน (1-2 วัน)',
            self::Pickup => 'รับสินค้าที่ร้าน',
        };
    }

    public function baseFee(): float
    {
        return match ($this) {
            self::Standard => 50,
            self::Express => 100,
            self::Pickup => 0,
        };
    }

    /** STANDARD is free when the subtotal reaches the threshold. */
    public function feeFor(float $subtotal): float
    {
        if ($this === self::Standard && $subtotal >= self::FREE_STANDARD_THRESHOLD) {
            return 0;
        }

        return $this->baseFee();
    }

    public function requiresAddress(): bool
    {
        return $this !== self::Pickup;
    }
}
