<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShippingMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $attributes = ['status' => 'PENDING_PAYMENT', 'discount_amount' => 0];

    protected $fillable = [
        'code', 'user_id', 'status', 'coupon_id', 'subtotal', 'discount_amount', 'shipping_fee', 'total',
        'shipping_method', 'ship_name', 'ship_phone', 'ship_address', 'tracking_no', 'note',
        'paid_at', 'shipped_at', 'delivered_at', 'cancelled_at', 'created_at', 'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'shipping_method' => ShippingMethod::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /** e.g. SH-260101-AB12 */
    public static function generateCode(): string
    {
        do {
            $code = 'SH-'.now()->format('ymd').'-'.strtoupper(Str::random(4));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /** Customer may upload a slip while unpaid and no slip is waiting for review. */
    public function canUploadSlip(): bool
    {
        return $this->status === OrderStatus::PendingPayment
            && ! $this->payments->contains(fn (Payment $p) => $p->status === PaymentStatus::Pending);
    }

    public function customerCanCancel(): bool
    {
        return $this->status === OrderStatus::PendingPayment;
    }

    /** Thai notification text staff can copy and send to the customer. */
    public function notificationMessage(): string
    {
        $name = $this->ship_name;

        return match ($this->status) {
            OrderStatus::PendingPayment => "สวัสดีค่ะคุณ{$name} คำสั่งซื้อ {$this->code} ยอดชำระ ฿".number_format((float) $this->total, 2).' กรุณาชำระเงินและแนบสลิปในระบบ ขอบคุณที่ใช้บริการค่ะ',
            OrderStatus::Paid => "สวัสดีค่ะคุณ{$name} เราได้รับชำระเงินคำสั่งซื้อ {$this->code} เรียบร้อยแล้ว ขอบคุณที่ใช้บริการค่ะ",
            OrderStatus::Preparing => "สวัสดีค่ะคุณ{$name} คำสั่งซื้อ {$this->code} กำลังจัดเตรียมสินค้า ขอบคุณที่ใช้บริการค่ะ",
            OrderStatus::Shipped => $this->shipping_method === ShippingMethod::Pickup
                ? "สวัสดีค่ะคุณ{$name} คำสั่งซื้อ {$this->code} พร้อมให้รับสินค้าที่ร้านแล้ว ขอบคุณที่ใช้บริการค่ะ"
                : "สวัสดีค่ะคุณ{$name} คำสั่งซื้อ {$this->code} จัดส่งแล้ว เลขพัสดุ {$this->tracking_no} ขอบคุณที่ใช้บริการค่ะ",
            OrderStatus::Delivered => "สวัสดีค่ะคุณ{$name} คำสั่งซื้อ {$this->code} จัดส่งสำเร็จแล้ว หากพอใจสินค้าอย่าลืมรีวิวให้เราด้วยนะคะ ขอบคุณที่ใช้บริการค่ะ",
            OrderStatus::Cancelled => "สวัสดีค่ะคุณ{$name} คำสั่งซื้อ {$this->code} ถูกยกเลิกแล้ว หากมีข้อสงสัยกรุณาติดต่อร้านค่ะ",
        };
    }
}
