<?php

if (! function_exists('baht')) {
    /** Format an amount as Thai Baht, e.g. ฿2,500 (decimals only when needed). */
    function baht(float|int|string|null $amount): string
    {
        $amount = (float) $amount;
        $decimals = fmod($amount, 1.0) == 0.0 ? 0 : 2;

        return '฿'.number_format($amount, $decimals);
    }
}

if (! function_exists('thai_date')) {
    /** e.g. 13 ต.ค. 2569 14:30 (Buddhist year). */
    function thai_date(?DateTimeInterface $date, bool $withTime = true): string
    {
        if (! $date) {
            return '-';
        }

        $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        $text = $date->format('j').' '.$months[(int) $date->format('n') - 1].' '.((int) $date->format('Y') + 543);

        return $withTime ? $text.' '.$date->format('H:i') : $text;
    }
}
