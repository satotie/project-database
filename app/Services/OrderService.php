<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShippingMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * Move an order to a new status, enforcing OrderStatus::canTransitionTo().
     */
    public function transition(Order $order, OrderStatus $next, ?string $trackingNo = null): Order
    {
        if (! $order->status->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => "ไม่สามารถเปลี่ยนสถานะจาก \"{$order->status->label()}\" เป็น \"{$next->label()}\" ได้",
            ]);
        }

        if ($next === OrderStatus::Cancelled) {
            return $this->cancel($order);
        }

        if ($next === OrderStatus::Shipped && $order->shipping_method !== ShippingMethod::Pickup) {
            $trackingNo = trim((string) ($trackingNo ?? $order->tracking_no));
            if ($trackingNo === '') {
                throw ValidationException::withMessages(['tracking_no' => 'กรุณากรอกเลขพัสดุก่อนเปลี่ยนสถานะเป็นจัดส่งแล้ว']);
            }
            $order->tracking_no = $trackingNo;
        }

        $order->status = $next;
        if ($column = $next->timestampColumn()) {
            $order->{$column} = now();
        }
        $order->save();

        return $order;
    }

    /** Cancel and put the stock back, atomically. */
    public function cancel(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $order->status->canTransitionTo(OrderStatus::Cancelled)) {
                throw ValidationException::withMessages(['status' => 'คำสั่งซื้อนี้ไม่สามารถยกเลิกได้แล้ว']);
            }

            foreach ($order->items as $item) {
                ProductVariant::whereKey($item->product_variant_id)->lockForUpdate()->increment('stock', $item->quantity);
            }

            $order->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);

            return $order;
        });
    }

    public function approvePayment(Payment $payment, User $staff): void
    {
        DB::transaction(function () use ($payment, $staff) {
            if ($payment->status !== PaymentStatus::Pending) {
                throw ValidationException::withMessages(['payment' => 'รายการชำระเงินนี้ถูกตรวจสอบไปแล้ว']);
            }

            $payment->update(['status' => PaymentStatus::Approved, 'verified_by' => $staff->id, 'verified_at' => now()]);
            $this->transition($payment->order, OrderStatus::Paid);
        });
    }

    public function rejectPayment(Payment $payment, User $staff, string $reason): void
    {
        if ($payment->status !== PaymentStatus::Pending) {
            throw ValidationException::withMessages(['payment' => 'รายการชำระเงินนี้ถูกตรวจสอบไปแล้ว']);
        }

        // Order stays PENDING_PAYMENT so the customer can upload a new slip.
        $payment->update([
            'status' => PaymentStatus::Rejected,
            'verified_by' => $staff->id,
            'verified_at' => now(),
            'reject_reason' => $reason,
        ]);
    }
}
