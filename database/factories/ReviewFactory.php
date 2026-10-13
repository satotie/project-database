<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'rating' => fake()->numberBetween(3, 5),
            'comment' => fake()->randomElement([
                'ใส่สบายมาก ไซซ์ตรง แนะนำเลยค่ะ',
                'คุณภาพดีสมราคา จัดส่งไว',
                'สีสวยตรงปก ใส่วิ่งทุกวันไม่เจ็บเท้า',
                'พื้นนุ่ม เดินทั้งวันไม่เมื่อย',
                'โอเคครับ แต่ไซซ์ค่อนข้างเล็ก แนะนำเผื่อครึ่งไซซ์',
                'แพ็คของมาดีมาก ของแท้แน่นอน',
                'ดีไซน์สวย ใส่ได้หลายโอกาส',
                null,
            ]),
        ];
    }
}
