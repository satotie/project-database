<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public ?Product $product = null;

    public string $name = '';

    public string $slug = '';

    public ?int $brand_id = null;

    public ?int $category_id = null;

    public string $gender = 'unisex';

    public string $price = '';

    public string $description = '';

    public bool $is_active = true;

    /** @var array<int, array{id: ?int, size: string, stock: int|string, sku: string}> */
    public array $variants = [];

    public array $uploads = [];

    public function mount(?Product $product = null): void
    {
        if ($product?->exists) {
            $this->product = $product;
            $this->fill($product->only(['name', 'slug', 'brand_id', 'category_id', 'gender', 'description', 'is_active']));
            $this->price = (string) (float) $product->price;
            $this->variants = $product->variants->map(fn ($v) => $v->only(['id', 'size', 'stock', 'sku']))->all();
        } else {
            $this->variants = [['id' => null, 'size' => '', 'stock' => 0, 'sku' => '']];
        }
    }

    public function rendering($view): void
    {
        $view->title($this->product ? 'แก้ไขสินค้า' : 'เพิ่มสินค้า');
    }

    #[Computed]
    public function brands()
    {
        return Brand::orderBy('name')->get();
    }

    #[Computed]
    public function categories()
    {
        return Category::orderBy('name')->get();
    }

    #[Computed]
    public function images()
    {
        return $this->product?->images()->get() ?? collect();
    }

    public function updatedName(string $value): void
    {
        if (! $this->product) {
            $this->slug = Str::slug($value);
        }
    }

    public function addVariant(): void
    {
        $this->variants[] = ['id' => null, 'size' => '', 'stock' => 0, 'sku' => ''];
    }

    public function removeVariant(int $index): void
    {
        $id = $this->variants[$index]['id'] ?? null;

        if ($id && $this->product->variants()->whereKey($id)->whereHas('orderItems')->exists()) {
            Flux::toast(variant: 'warning', text: 'ไซซ์นี้มีประวัติการสั่งซื้อ ไม่สามารถลบได้ (ตั้งสต็อกเป็น 0 แทน)');

            return;
        }

        unset($this->variants[$index]);
        $this->variants = array_values($this->variants);
    }

    protected function rules(): array
    {
        $variantIds = array_filter(array_column($this->variants, 'id'));

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('products', 'slug')->ignore($this->product?->id)],
            'brand_id' => ['required', 'exists:brands,id'],
            'category_id' => ['required', 'exists:categories,id'],
            'gender' => ['required', Rule::in(array_keys(Product::GENDERS))],
            'price' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'description' => ['required', 'string', 'max:5000'],
            'is_active' => ['boolean'],
            'variants' => ['array', 'min:1'],
            'variants.*.size' => ['required', 'string', 'max:10', 'distinct'],
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'variants.*.sku' => ['required', 'string', 'max:100', 'distinct', Rule::unique('product_variants', 'sku')->whereNotIn('id', $variantIds)],
            'uploads' => ['array', 'max:10'],
            'uploads.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    protected function messages(): array
    {
        return [
            'variants.min' => 'ต้องมีอย่างน้อย 1 ไซซ์',
            'variants.*.size.required' => 'กรุณากรอกไซซ์',
            'variants.*.size.distinct' => 'ไซซ์ซ้ำกัน',
            'variants.*.stock.*' => 'สต็อกต้องเป็นจำนวนเต็มตั้งแต่ 0',
            'variants.*.sku.required' => 'กรุณากรอก SKU',
            'variants.*.sku.distinct' => 'SKU ซ้ำกัน',
            'variants.*.sku.unique' => 'SKU นี้ถูกใช้แล้ว',
            'uploads.*.image' => 'ไฟล์ต้องเป็นรูปภาพ',
            'uploads.*.max' => 'รูปภาพต้องมีขนาดไม่เกิน 4MB',
            'uploads.*.mimes' => 'รองรับไฟล์ JPG, PNG, WEBP',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'ชื่อสินค้า', 'slug' => 'slug', 'brand_id' => 'แบรนด์', 'category_id' => 'หมวดหมู่',
            'gender' => 'เพศ', 'price' => 'ราคา', 'description' => 'รายละเอียด',
        ];
    }

    public function fillSkus(): void
    {
        $brand = Brand::find($this->brand_id)?->name ?? 'SKU';
        $prefix = strtoupper(Str::substr(Str::slug($brand, ''), 0, 3)).'-'.strtoupper(Str::substr(Str::slug($this->name ?: 'item', ''), 0, 6));

        foreach ($this->variants as $i => $variant) {
            if (blank($variant['sku']) && filled($variant['size'])) {
                $this->variants[$i]['sku'] = $prefix.'-'.$variant['size'];
            }
        }
    }

    public function save()
    {
        $this->slug = Str::slug($this->slug ?: $this->name);
        $data = $this->validate();
        $isNew = ! $this->product;

        DB::transaction(function () use ($data) {
            $attributes = collect($data)->only(['name', 'slug', 'brand_id', 'category_id', 'gender', 'price', 'description', 'is_active'])->all();
            $this->product = $this->product
                ? tap($this->product)->update($attributes)
                : Product::create($attributes);

            $keep = [];
            foreach ($data['variants'] as $variant) {
                $model = $this->product->variants()->updateOrCreate(
                    ['id' => $variant['id'] ?? null],
                    ['size' => trim($variant['size']), 'stock' => (int) $variant['stock'], 'sku' => trim($variant['sku'])],
                );
                $keep[] = $model->id;
            }
            $this->product->variants()->whereNotIn('id', $keep)->whereDoesntHave('orderItems')->delete();

            $order = (int) $this->product->images()->max('sort_order');
            foreach ($this->uploads as $upload) {
                $this->product->images()->create([
                    'path' => $upload->store('products', 'public'),
                    'sort_order' => ++$order,
                ]);
            }
        });

        $this->uploads = [];
        $this->variants = $this->product->variants()->get()->map(fn ($v) => $v->only(['id', 'size', 'stock', 'sku']))->all();
        unset($this->images);

        Flux::toast(variant: 'success', text: 'บันทึกสินค้าเรียบร้อย');

        return $isNew ? $this->redirectRoute('staff.products.edit', $this->product, navigate: true) : null;
    }

    public function deleteImage(ProductImage $image): void
    {
        abort_unless($image->product_id === $this->product?->id, 403);
        Storage::disk('public')->delete($image->path);
        $image->delete();
        unset($this->images);
    }

    public function makeMainImage(ProductImage $image): void
    {
        abort_unless($image->product_id === $this->product?->id, 403);
        $ordered = $this->product->images()->get()->sortBy(fn ($i) => $i->id === $image->id ? -1 : $i->sort_order)->values();
        foreach ($ordered as $position => $item) {
            $item->update(['sort_order' => $position]);
        }
        unset($this->images);
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:link :href="route('staff.products.index')" wire:navigate class="text-sm">← รายการสินค้า</flux:link>
            <flux:heading size="xl" level="1">{{ $product ? 'แก้ไขสินค้า: '.$product->name : 'เพิ่มสินค้าใหม่' }}</flux:heading>
        </div>
        @if ($product)
            <flux:button icon="eye" :href="route('products.show', $product)" target="_blank">ดูหน้าร้าน</flux:button>
        @endif
    </div>

    <form wire:submit="save" class="grid gap-6 xl:grid-cols-[1fr_420px]">
        <div class="space-y-6">
            <section class="shop-card space-y-4 p-5">
                <flux:heading size="lg">ข้อมูลสินค้า</flux:heading>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model.blur="name" label="ชื่อสินค้า" />
                    <flux:input wire:model="slug" label="Slug (URL)" description:trailing="ใช้ a-z, 0-9 และ -" />
                    <flux:select wire:model="brand_id" label="แบรนด์">
                        <flux:select.option value="">— เลือกแบรนด์ —</flux:select.option>
                        @foreach ($this->brands as $b)
                            <flux:select.option value="{{ $b->id }}">{{ $b->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="category_id" label="หมวดหมู่">
                        <flux:select.option value="">— เลือกหมวดหมู่ —</flux:select.option>
                        @foreach ($this->categories as $c)
                            <flux:select.option value="{{ $c->id }}">{{ $c->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="gender" label="เพศ">
                        @foreach (Product::GENDERS as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="price" type="number" step="0.01" min="0" label="ราคา (บาท)" />
                </div>
                <flux:textarea wire:model="description" label="รายละเอียด" rows="4" />
                <flux:switch wire:model="is_active" label="เปิดขายสินค้านี้" align="left" />
            </section>

            <section class="shop-card space-y-4 p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:heading size="lg">ไซซ์และสต็อก</flux:heading>
                    <div class="flex gap-2">
                        <flux:button size="sm" variant="ghost" wire:click="fillSkus">สร้าง SKU อัตโนมัติ</flux:button>
                        <flux:button size="sm" icon="plus" wire:click="addVariant">เพิ่มไซซ์</flux:button>
                    </div>
                </div>
                <flux:error name="variants" />
                <div class="space-y-2">
                    <div class="grid grid-cols-[90px_110px_1fr_40px] gap-2 px-1 text-xs font-medium text-zinc-500">
                        <span>ไซซ์ (EU)</span><span>สต็อก</span><span>SKU</span><span></span>
                    </div>
                    @foreach ($variants as $i => $variant)
                        <div class="grid grid-cols-[90px_110px_1fr_40px] items-start gap-2" wire:key="variant-{{ $variant['id'] ?? 'new-'.$i }}">
                            <div><flux:input wire:model="variants.{{ $i }}.size" size="sm" placeholder="42" /><flux:error name="variants.{{ $i }}.size" /></div>
                            <div><flux:input wire:model="variants.{{ $i }}.stock" size="sm" type="number" min="0" /><flux:error name="variants.{{ $i }}.stock" /></div>
                            <div><flux:input wire:model="variants.{{ $i }}.sku" size="sm" placeholder="NIK-PEG-42" /><flux:error name="variants.{{ $i }}.sku" /></div>
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeVariant({{ $i }})" aria-label="ลบไซซ์" />
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="space-y-6">
            <section class="shop-card space-y-4 p-5">
                <flux:heading size="lg">รูปภาพสินค้า</flux:heading>
                @if ($this->images->isNotEmpty())
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ($this->images as $image)
                            <div class="group relative overflow-hidden rounded-xl border border-zinc-200" wire:key="img-{{ $image->id }}">
                                <img src="{{ $image->url }}" alt="" class="aspect-square w-full object-cover">
                                @if ($loop->first)
                                    <span class="absolute start-1 top-1 rounded bg-gold px-1.5 text-[10px] font-medium text-brown-dark">รูปหลัก</span>
                                @endif
                                <div class="absolute inset-x-0 bottom-0 flex justify-center gap-1 bg-black/40 p-1 opacity-0 transition group-hover:opacity-100">
                                    @unless ($loop->first)
                                        <flux:button size="xs" wire:click="makeMainImage({{ $image->id }})">ตั้งเป็นรูปหลัก</flux:button>
                                    @endunless
                                    <flux:button size="xs" variant="danger" icon="trash" wire:click="deleteImage({{ $image->id }})" wire:confirm="ลบรูปนี้?" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                <flux:input type="file" wire:model="uploads" multiple accept="image/*" label="อัปโหลดรูป (หลายไฟล์ได้, ไม่เกิน 4MB/รูป)" />
                <flux:error name="uploads.*" />
                <div wire:loading wire:target="uploads" class="text-sm text-zinc-500">กำลังอัปโหลด...</div>
                @if ($uploads)
                    <div class="grid grid-cols-4 gap-2">
                        @foreach ($uploads as $upload)
                            @if (method_exists($upload, 'isPreviewable') && $upload->isPreviewable())
                                <img src="{{ $upload->temporaryUrl() }}" class="aspect-square rounded-lg object-cover" alt="">
                            @endif
                        @endforeach
                    </div>
                    <flux:text size="sm">รูปใหม่จะถูกบันทึกเมื่อกด "บันทึกสินค้า"</flux:text>
                @endif
            </section>

            <div class="shop-card sticky top-4 flex gap-2 p-4">
                <flux:button type="submit" variant="primary" class="flex-1">บันทึกสินค้า</flux:button>
                <flux:button :href="route('staff.products.index')" wire:navigate variant="ghost">ยกเลิก</flux:button>
            </div>
        </div>
    </form>
</div>
