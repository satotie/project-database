<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    private const array PLACES = [
        ['เขตจตุจักร', 'กรุงเทพมหานคร', '10900'],
        ['เขตบางกะปิ', 'กรุงเทพมหานคร', '10240'],
        ['อำเภอเมืองเชียงใหม่', 'เชียงใหม่', '50000'],
        ['อำเภอเมืองขอนแก่น', 'ขอนแก่น', '40000'],
        ['อำเภอหาดใหญ่', 'สงขลา', '90110'],
        ['อำเภอเมืองนนทบุรี', 'นนทบุรี', '11000'],
        ['อำเภอบางพลี', 'สมุทรปราการ', '10540'],
        ['อำเภอเมืองชลบุรี', 'ชลบุรี', '20000'],
    ];

    public function definition(): array
    {
        [$district, $province, $postal] = fake()->randomElement(self::PLACES);

        return [
            'user_id' => User::factory(),
            'recipient_name' => fake()->name(),
            'phone' => '08'.fake()->numerify('########'),
            'address_line' => fake()->numberBetween(1, 299).'/'.fake()->numberBetween(1, 99).' ซอย'.fake()->numberBetween(1, 50).' ถนน'.fake()->randomElement(['สุขุมวิท', 'พหลโยธิน', 'รามคำแหง', 'นิมมานเหมินท์', 'มิตรภาพ', 'เพชรเกษม']),
            'district' => $district,
            'province' => $province,
            'postal_code' => $postal,
            'is_default' => false,
        ];
    }
}
