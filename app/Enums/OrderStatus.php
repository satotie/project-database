<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'PENDING_PAYMENT';
    case Paid = 'PAID';
    case Preparing = 'PREPARING';
    case Shipped = 'SHIPPED';
    case Delivered = 'DELIVERED';
    case Cancelled = 'CANCELLED';

    /** Statuses that count as a sale (used by reports and dashboard revenue). */
    public const array REVENUE = [self::Paid, self::Preparing, self::Shipped, self::Delivered];

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'รอชำระเงิน',
            self::Paid => 'ชำระเงินแล้ว',
            self::Preparing => 'กำลังเตรียมสินค้า',
            self::Shipped => 'จัดส่งแล้ว',
            self::Delivered => 'ได้รับสินค้าแล้ว',
            self::Cancelled => 'ยกเลิก',
        };
    }

    /** Flux badge color. */
    public function color(): string
    {
        return match ($this) {
            self::PendingPayment => 'amber',
            self::Paid => 'sky',
            self::Preparing => 'indigo',
            self::Shipped => 'violet',
            self::Delivered => 'green',
            self::Cancelled => 'red',
        };
    }

    /**
     * The single place where allowed status transitions are defined.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Paid, self::Cancelled],
            self::Paid => [self::Preparing, self::Cancelled],
            self::Preparing => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /** Timestamp column set when an order enters this status. */
    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::Paid => 'paid_at',
            self::Shipped => 'shipped_at',
            self::Delivered => 'delivered_at',
            self::Cancelled => 'cancelled_at',
            default => null,
        };
    }

    /** @return array<int, self> the normal (non-cancelled) flow, for the timeline */
    public static function flow(): array
    {
        return [self::PendingPayment, self::Paid, self::Preparing, self::Shipped, self::Delivered];
    }

    public static function revenueValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::REVENUE);
    }
}
