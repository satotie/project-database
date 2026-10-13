<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'รอตรวจสอบ',
            self::Approved => 'อนุมัติแล้ว',
            self::Rejected => 'ไม่อนุมัติ',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'green',
            self::Rejected => 'red',
        };
    }
}
