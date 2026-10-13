<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShippingMethod;
use App\Models\Address;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /** [brand, category, name, gender, price, color, description] */
    private const array PRODUCTS = [
        ['Nike', 'Running', 'Nike Pegasus 41', 'unisex', 4500, '#E4572E', 'รองเท้าวิ่งรุ่นยอดนิยม พื้น ReactX นุ่มเด้ง เหมาะกับการวิ่งทุกวันทั้งระยะสั้นและระยะยาว'],
        ['Nike', 'Running', 'Nike Vomero 18', 'men', 5900, '#2E86AB', 'เน้นความนุ่มสูงสุด รองรับแรงกระแทกได้ดีเยี่ยม เหมาะสำหรับนักวิ่งระยะไกล'],
        ['Nike', 'Basketball', 'Nike LeBron Witness 8', 'men', 4200, '#6A4C93', 'รองเท้าบาสเกตบอลซัพพอร์ตข้อเท้าแน่นหนา พื้นยึดเกาะดีบนคอร์ทในร่ม'],
        ['Nike', 'Sneakers', 'Nike Air Force 1 \'07', 'unisex', 3900, '#F4F1EA', 'สนีกเกอร์คลาสสิกตลอดกาล หนังแท้สีขาว ใส่ได้กับทุกลุค'],
        ['Adidas', 'Running', 'Adidas Ultraboost Light', 'women', 6900, '#3A7D44', 'พื้น Light BOOST เบากว่าเดิม คืนพลังทุกก้าว อัปเปอร์ Primeknit กระชับเท้า'],
        ['Adidas', 'Running', 'Adidas Adizero SL', 'unisex', 3800, '#F2C14E', 'รองเท้าวิ่งน้ำหนักเบาสำหรับซ้อมความเร็ว พื้น Lightstrike Pro'],
        ['Adidas', 'Sneakers', 'Adidas Samba OG', 'unisex', 3700, '#1B1B1E', 'ดีไซน์วินเทจจากรองเท้าฟุตบอลในร่ม หนังกลับคู่หนังเรียบ พื้นยางกัม'],
        ['Adidas', 'Sandals', 'Adidas Adilette Comfort', 'unisex', 1290, '#264653', 'รองเท้าแตะสวมพื้นนุ่ม Cloudfoam ใส่สบายทั้งวัน แห้งเร็ว'],
        ['New Balance', 'Running', 'New Balance Fresh Foam 1080v14', 'men', 5900, '#8D99AE', 'พื้น Fresh Foam X นุ่มแน่น ให้ความสบายสูงสุดสำหรับการวิ่งระยะไกล'],
        ['New Balance', 'Sneakers', 'New Balance 530', 'women', 3500, '#C0C0C0', 'สนีกเกอร์สไตล์ Y2K เรโทรรันนิ่ง ตาข่ายระบายอากาศ ใส่เที่ยวได้ทุกวัน'],
        ['New Balance', 'Sneakers', 'New Balance 574', 'unisex', 3200, '#5C6B73', 'รุ่นไอคอนิกของ New Balance พื้น ENCAP ซัพพอร์ตดี ทนทาน'],
        ['Converse', 'Sneakers', 'Converse Chuck 70 Hi', 'unisex', 2900, '#2B2D42', 'ผ้าแคนวาสหนา พื้นยางสีครีมวินเทจ เสริมพื้นรองในนุ่มกว่าเดิม'],
        ['Converse', 'Sneakers', 'Converse Run Star Hike', 'women', 3600, '#EDEDE9', 'พื้นแพลตฟอร์มหยักทรงสูง เพิ่มความสูงและความโดดเด่นให้ทุกลุค'],
        ['Converse', 'Basketball', 'Converse All Star Pro BB', 'men', 4900, '#D62828', 'รองเท้าบาสสมรรถนะสูงจาก Converse พื้น React นุ่มรับแรงกระแทก'],
        ['Vans', 'Sneakers', 'Vans Old Skool', 'unisex', 2600, '#111111', 'สเก็ตชูส์คลาสสิกพร้อมแถบ Sidestripe พื้นวาฟเฟิลยึดเกาะดี'],
        ['Vans', 'Sneakers', 'Vans Slip-On Checkerboard', 'unisex', 2300, '#FFFFFF', 'ลายตารางหมากรุกสุดคลาสสิก ใส่ง่ายไม่ต้องผูกเชือก'],
        ['Vans', 'Sandals', 'Vans La Costa Slide-On', 'unisex', 1490, '#7F5539', 'รองเท้าแตะแบบสวม พื้นนุ่มเบา เหมาะกับวันพักผ่อนสบาย ๆ'],
        ['Asics', 'Running', 'Asics Gel-Kayano 31', 'women', 6200, '#4361EE', 'รองเท้าวิ่งซัพพอร์ตสำหรับเท้าแบน ระบบ 4D Guidance ช่วยให้ก้าววิ่งมั่นคง'],
        ['Asics', 'Running', 'Asics Gel-Nimbus 26', 'men', 5900, '#06D6A0', 'พื้น FF BLAST PLUS ECO นุ่มเป็นพิเศษ เหมาะกับการวิ่งยาวและฟื้นฟู'],
        ['Asics', 'Basketball', 'Asics Gel-Hoop V16', 'unisex', 3900, '#F77F00', 'รองเท้าบาสน้ำหนักเบา เจล GEL ลดแรงกระแทก เปลี่ยนทิศทางได้คล่องตัว'],
    ];

    public function run(): void
    {
        fake()->seed(2026);
        mt_srand(2026);

        Storage::disk('public')->deleteDirectory('products');
        Storage::disk('public')->deleteDirectory('slips');
        Storage::disk('public')->deleteDirectory('brands');

        // --- Users -----------------------------------------------------------
        User::factory()->staff()->create([
            'name' => 'พนักงาน ร้านรองเท้า',
            'email' => 'staff@shop.test',
            'phone' => '0811111111',
        ]);

        $customer = User::factory()->create([
            'name' => 'สมชาย ใจดี',
            'email' => 'customer@shop.test',
            'phone' => '0899999999',
        ]);
        Address::factory()->for($customer)->create(['recipient_name' => 'สมชาย ใจดี', 'phone' => '0899999999', 'is_default' => true]);
        Address::factory()->for($customer)->create(['recipient_name' => 'สมชาย ใจดี (ที่ทำงาน)', 'phone' => '0899999999']);

        $customers = collect([$customer])->merge(
            User::factory(8)->create()->each(function (User $user) {
                Address::factory()->for($user)->create(['recipient_name' => $user->name, 'is_default' => true]);
                if (fake()->boolean()) {
                    Address::factory()->for($user)->create();
                }
            })
        );

        // --- Catalog ---------------------------------------------------------
        $brands = collect(['Nike', 'Adidas', 'New Balance', 'Converse', 'Vans', 'Asics'])
            ->mapWithKeys(fn ($name) => [$name => Brand::create(['name' => $name])]);

        $categories = collect(['Running' => 'รองเท้าวิ่ง', 'Sneakers' => 'สนีกเกอร์', 'Basketball' => 'รองเท้าบาสเกตบอล', 'Sandals' => 'รองเท้าแตะ'])
            ->mapWithKeys(fn ($label, $name) => [$name => Category::create(['name' => $name, 'slug' => Str::slug($name)])]);

        $products = collect();
        foreach (self::PRODUCTS as $i => [$brand, $category, $name, $gender, $price, $color, $description]) {
            $product = Product::create([
                'brand_id' => $brands[$brand]->id,
                'category_id' => $categories[$category]->id,
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => $description,
                'price' => $price,
                'gender' => $gender,
                'is_active' => true,
                'created_at' => now()->subDays(90 - $i * 4),
            ]);

            foreach ([0, 1] as $n) {
                $path = "products/{$product->slug}-{$n}.svg";
                Storage::disk('public')->put($path, $this->shoeSvg($color, $n === 1));
                $product->images()->create(['path' => $path, 'sort_order' => $n]);
            }

            $sizes = range(36, 45);
            $count = mt_rand(4, 7);
            $start = $gender === 'women' ? mt_rand(0, 2) : mt_rand(2, 10 - $count);
            foreach (array_slice($sizes, $start, $count) as $size) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'size' => (string) $size,
                    // About 1 in 5 sizes is out of stock to demo the disabled size buttons.
                    'stock' => mt_rand(1, 5) === 1 ? 0 : mt_rand(1, 15),
                    'sku' => strtoupper(Str::substr(Str::slug($brand, ''), 0, 3)).'-'.str_pad((string) $product->id, 3, '0', STR_PAD_LEFT).'-'.$size,
                ]);
            }

            $products->push($product);
        }

        // --- Coupons ---------------------------------------------------------
        $welcome = Coupon::create([
            'code' => 'WELCOME10', 'description' => 'ส่วนลด 10% สำหรับสินค้าทุกชิ้น',
            'discount_percent' => 10, 'applies_to_all' => true,
        ]);
        $run = Coupon::create([
            'code' => 'RUN20', 'description' => 'ส่วนลด 20% เฉพาะรองเท้าวิ่ง',
            'discount_percent' => 20, 'applies_to_all' => false, 'min_order_amount' => 1000,
        ]);
        $run->products()->attach($products->where('category_id', $categories['Running']->id)->pluck('id'));
        Coupon::create([
            'code' => 'EXPIRED50', 'description' => 'คูปองหมดอายุแล้ว (สำหรับทดสอบ)',
            'discount_percent' => 50, 'applies_to_all' => true,
            'starts_at' => now()->subMonths(3), 'expires_at' => now()->subMonth(),
        ]);

        // --- Orders ----------------------------------------------------------
        $this->seedOrders($customers, $customer, [$welcome, $run]);

        // --- Reviews ---------------------------------------------------------
        $this->seedReviews();
    }

    /**
     * ~40 historical orders over the last 60 days covering every status.
     * They are inserted directly (not through checkout) so stock is not touched.
     */
    private function seedOrders(Collection $customers, User $mainCustomer, array $coupons): void
    {
        $staff = User::where('role', 'staff')->first();
        $pricing = app(CheckoutService::class);
        $variants = ProductVariant::with('product')->get();

        Storage::disk('public')->put('slips/demo-slip.svg', $this->slipSvg());

        $statuses = array_merge(
            array_fill(0, 15, OrderStatus::Delivered),
            array_fill(0, 5, OrderStatus::Shipped),
            array_fill(0, 4, OrderStatus::Preparing),
            array_fill(0, 5, OrderStatus::Paid),
            array_fill(0, 6, OrderStatus::PendingPayment),
            array_fill(0, 5, OrderStatus::Cancelled),
        );
        shuffle($statuses);

        // Guarantee demo data for customer@shop.test.
        $statuses[0] = $statuses[1] = $statuses[2] = OrderStatus::Delivered;
        $statuses[3] = OrderStatus::Shipped;
        $statuses[4] = OrderStatus::PendingPayment;

        foreach ($statuses as $i => $status) {
            $user = $i < 5 ? $mainCustomer : $customers->random();
            $address = $user->addresses()->first();
            $created = $status === OrderStatus::PendingPayment
                ? Carbon::now()->subHours(mt_rand(1, 60))
                : Carbon::now()->subDays(mt_rand(1, 59))->setTime(mt_rand(8, 22), mt_rand(0, 59));

            $lines = $variants->random(mt_rand(1, 3))->map(fn ($v) => ['variant' => $v, 'quantity' => mt_rand(1, 10) > 8 ? 2 : 1]);
            $method = fake()->randomElement([ShippingMethod::Standard, ShippingMethod::Standard, ShippingMethod::Express, ShippingMethod::Pickup]);
            $coupon = mt_rand(1, 4) === 1 ? fake()->randomElement($coupons) : null;
            $summary = $pricing->calculate($lines, $coupon, $method);

            $order = Order::create([
                'code' => 'SH-'.$created->format('ymd').'-'.strtoupper(Str::random(4)),
                'user_id' => $user->id,
                'status' => $status,
                'coupon_id' => $summary['coupon']?->id,
                'subtotal' => $summary['subtotal'],
                'discount_amount' => $summary['discount'],
                'shipping_fee' => $summary['shipping_fee'],
                'total' => $summary['total'],
                'shipping_method' => $method,
                'ship_name' => $address->recipient_name,
                'ship_phone' => $address->phone,
                'ship_address' => $method->requiresAddress() ? $address->fullAddress() : null,
                'created_at' => $created,
                'updated_at' => $created,
            ]);

            foreach ($summary['lines'] as $line) {
                $order->items()->create([
                    'product_variant_id' => $line['variant']->id,
                    'product_name' => $line['variant']->product->name,
                    'size' => $line['variant']->size,
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'discount_amount' => $line['discount'],
                    'line_total' => $line['line_total'],
                ]);
            }

            $this->applyHistory($order, $status, $created, $staff);
        }
    }

    private function applyHistory(Order $order, OrderStatus $status, Carbon $created, User $staff): void
    {
        $paidAt = $created->copy()->addHours(mt_rand(1, 12));
        $reached = fn (OrderStatus $s) => in_array($s, array_slice(OrderStatus::flow(), 0, array_search($status, OrderStatus::flow(), true) + 1), true);

        if ($status === OrderStatus::Cancelled) {
            $order->update(['cancelled_at' => $created->copy()->addHours(mt_rand(2, 30))]);

            return;
        }

        if ($status === OrderStatus::PendingPayment) {
            // Some unpaid orders already have a slip waiting, one was rejected.
            if (mt_rand(0, 1)) {
                $order->payments()->create(['slip_path' => 'slips/demo-slip.svg', 'amount' => $order->total, 'status' => PaymentStatus::Pending, 'created_at' => $paidAt, 'updated_at' => $paidAt]);
            } elseif (mt_rand(0, 1)) {
                $order->payments()->create(['slip_path' => 'slips/demo-slip.svg', 'amount' => $order->total, 'status' => PaymentStatus::Rejected, 'verified_by' => $staff->id, 'verified_at' => $paidAt, 'reject_reason' => 'ยอดเงินในสลิปไม่ตรงกับยอดคำสั่งซื้อ']);
            }

            return;
        }

        $order->payments()->create(['slip_path' => 'slips/demo-slip.svg', 'amount' => $order->total, 'status' => PaymentStatus::Approved, 'verified_by' => $staff->id, 'verified_at' => $paidAt, 'created_at' => $paidAt, 'updated_at' => $paidAt]);

        $data = ['paid_at' => $paidAt];
        if ($reached(OrderStatus::Shipped)) {
            $data['shipped_at'] = $paidAt->copy()->addDay();
            $data['tracking_no'] = $order->shipping_method === ShippingMethod::Pickup ? null : 'TH'.fake()->numerify('##########');
        }
        if ($reached(OrderStatus::Delivered)) {
            $data['delivered_at'] = $paidAt->copy()->addDays(mt_rand(2, 4));
        }
        $order->update($data);
    }

    /** About 30 reviews written by customers on products from their delivered orders. */
    private function seedReviews(): void
    {
        $pairs = Order::where('status', OrderStatus::Delivered)->with('items.variant')->get()
            ->flatMap(fn (Order $o) => $o->items->map(fn ($item) => [$o->user_id, $item->variant->product_id, $o->delivered_at]))
            ->unique(fn ($p) => $p[0].'-'.$p[1])
            ->values();

        // customer@shop.test keeps one delivered product un-reviewed to demo writing a review.
        $mainId = User::where('email', 'customer@shop.test')->value('id');
        $skip = $pairs->firstWhere(0, $mainId);

        foreach ($pairs->take(31) as $pair) {
            if ($pair === $skip) {
                continue;
            }
            Review::factory()->create([
                'user_id' => $pair[0],
                'product_id' => $pair[1],
                'created_at' => $pair[2]->copy()->addDays(mt_rand(1, 5)),
            ]);
        }
    }

    /** A simple sneaker illustration so the catalog has images without external files. */
    private function shoeSvg(string $color, bool $alt): string
    {
        $bg = $alt ? '#F3E9DA' : '#FAF5EE';
        $sole = $color === '#FFFFFF' || $color === '#F4F1EA' || $color === '#EDEDE9' ? '#C9A063' : '#FFFFFF';
        $flip = $alt ? 'transform="translate(400 0) scale(-1 1)"' : '';

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300">
  <rect width="400" height="300" fill="{$bg}"/>
  <ellipse cx="205" cy="238" rx="150" ry="12" fill="#000" opacity="0.08"/>
  <g {$flip}>
    <path d="M58 205 C56 176 80 166 112 158 L176 126 C196 114 214 104 232 110 L252 136 C272 156 312 164 342 172 C364 178 368 198 362 212 L58 212 Z" fill="{$color}" stroke="#3b2f2f" stroke-width="3" stroke-linejoin="round"/>
    <path d="M52 208 L366 208 C368 222 360 230 346 230 L70 230 C58 230 50 222 52 208 Z" fill="{$sole}" stroke="#3b2f2f" stroke-width="3"/>
    <path d="M196 132 L214 150 M184 140 L202 158 M172 148 L190 166" stroke="#FAF5EE" stroke-width="5" stroke-linecap="round"/>
    <path d="M120 196 C170 186 240 160 300 178" fill="none" stroke="#C9A063" stroke-width="8" stroke-linecap="round"/>
    <circle cx="236" cy="118" r="5" fill="#3b2f2f"/>
  </g>
</svg>
SVG;
    }

    private function slipSvg(): string
    {
        return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 420">
  <rect width="300" height="420" rx="16" fill="#ffffff" stroke="#e5d9c6"/>
  <rect width="300" height="70" rx="16" fill="#4E2A84"/>
  <text x="150" y="44" font-size="20" text-anchor="middle" fill="#fff" font-family="sans-serif">โอนเงินสำเร็จ</text>
  <text x="30" y="120" font-size="14" fill="#555" font-family="sans-serif">จาก: ลูกค้า</text>
  <text x="30" y="150" font-size="14" fill="#555" font-family="sans-serif">ไปยัง: บจก. ชูส์ ช็อป</text>
  <text x="30" y="180" font-size="14" fill="#555" font-family="sans-serif">เลขที่บัญชี: xxx-x-x4567-x</text>
  <text x="150" y="260" font-size="34" text-anchor="middle" fill="#222" font-family="sans-serif">฿ ****.**</text>
  <text x="150" y="380" font-size="12" text-anchor="middle" fill="#999" font-family="sans-serif">สลิปตัวอย่างสำหรับการสาธิต</text>
</svg>
SVG;
    }
}
