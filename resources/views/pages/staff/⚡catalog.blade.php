<?php

use App\Models\Brand;
use App\Models\Category;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('แบรนด์และหมวดหมู่')] class extends Component {
    use WithFileUploads;

    public ?int $brandId = null;

    public string $brandName = '';

    public $brandLogo;

    public ?int $categoryId = null;

    public string $categoryName = '';

    public string $categorySlug = '';

    #[Computed]
    public function brands()
    {
        return Brand::withCount('products')->orderBy('name')->get();
    }

    #[Computed]
    public function categories()
    {
        return Category::withCount('products')->orderBy('name')->get();
    }

    // Brands -------------------------------------------------------------

    public function editBrand(Brand $brand): void
    {
        $this->brandId = $brand->id;
        $this->brandName = $brand->name;
        $this->brandLogo = null;
        $this->resetErrorBag();
        Flux::modal('brand-form')->show();
    }

    public function newBrand(): void
    {
        $this->reset('brandId', 'brandName', 'brandLogo');
        $this->resetErrorBag();
        Flux::modal('brand-form')->show();
    }

    public function saveBrand(): void
    {
        $this->validate([
            'brandName' => ['required', 'string', 'max:100', Rule::unique('brands', 'name')->ignore($this->brandId)],
            'brandLogo' => ['nullable', 'image', 'max:2048'],
        ], [], ['brandName' => 'ชื่อแบรนด์', 'brandLogo' => 'โลโก้']);

        $brand = Brand::findOrNew($this->brandId);
        $brand->name = trim($this->brandName);

        if ($this->brandLogo) {
            if ($brand->logo_path) {
                Storage::disk('public')->delete($brand->logo_path);
            }
            $brand->logo_path = $this->brandLogo->store('brands', 'public');
        }

        $brand->save();
        $this->reset('brandId', 'brandName', 'brandLogo');
        unset($this->brands);
        Flux::modal('brand-form')->close();
        Flux::toast(variant: 'success', text: 'บันทึกแบรนด์แล้ว');
    }

    public function deleteBrand(Brand $brand): void
    {
        if ($brand->products()->exists()) {
            Flux::toast(variant: 'danger', text: 'ไม่สามารถลบแบรนด์ที่ยังมีสินค้าอยู่ได้');

            return;
        }

        if ($brand->logo_path) {
            Storage::disk('public')->delete($brand->logo_path);
        }
        $brand->delete();
        unset($this->brands);
        Flux::toast(text: 'ลบแบรนด์แล้ว');
    }

    // Categories ---------------------------------------------------------

    public function editCategory(Category $category): void
    {
        $this->categoryId = $category->id;
        $this->categoryName = $category->name;
        $this->categorySlug = $category->slug;
        $this->resetErrorBag();
        Flux::modal('category-form')->show();
    }

    public function newCategory(): void
    {
        $this->reset('categoryId', 'categoryName', 'categorySlug');
        $this->resetErrorBag();
        Flux::modal('category-form')->show();
    }

    public function saveCategory(): void
    {
        $this->categorySlug = Str::slug($this->categorySlug ?: $this->categoryName);

        $this->validate([
            'categoryName' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($this->categoryId)],
            'categorySlug' => ['required', 'alpha_dash', 'max:100', Rule::unique('categories', 'slug')->ignore($this->categoryId)],
        ], [], ['categoryName' => 'ชื่อหมวดหมู่', 'categorySlug' => 'slug']);

        Category::updateOrCreate(['id' => $this->categoryId], ['name' => trim($this->categoryName), 'slug' => $this->categorySlug]);
        $this->reset('categoryId', 'categoryName', 'categorySlug');
        unset($this->categories);
        Flux::modal('category-form')->close();
        Flux::toast(variant: 'success', text: 'บันทึกหมวดหมู่แล้ว');
    }

    public function deleteCategory(Category $category): void
    {
        if ($category->products()->exists()) {
            Flux::toast(variant: 'danger', text: 'ไม่สามารถลบหมวดหมู่ที่ยังมีสินค้าอยู่ได้');

            return;
        }

        $category->delete();
        unset($this->categories);
        Flux::toast(text: 'ลบหมวดหมู่แล้ว');
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl" level="1">แบรนด์และหมวดหมู่</flux:heading>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="shop-card p-5">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">แบรนด์ ({{ $this->brands->count() }})</flux:heading>
                <flux:button size="sm" icon="plus" wire:click="newBrand">เพิ่มแบรนด์</flux:button>
            </div>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>ชื่อ</flux:table.column>
                    <flux:table.column align="end">สินค้า</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->brands as $brand)
                        <flux:table.row :key="'b'.$brand->id">
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    @if ($brand->logo_path)
                                        <img src="/storage/{{ $brand->logo_path }}" class="size-7 rounded object-contain" alt="">
                                    @endif
                                    <span class="font-medium">{{ $brand->name }}</span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ $brand->products_count }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="editBrand({{ $brand->id }})" />
                                <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteBrand({{ $brand->id }})" wire:confirm="ลบแบรนด์ {{ $brand->name }}?" />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </section>

        <section class="shop-card p-5">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">หมวดหมู่ ({{ $this->categories->count() }})</flux:heading>
                <flux:button size="sm" icon="plus" wire:click="newCategory">เพิ่มหมวดหมู่</flux:button>
            </div>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>ชื่อ</flux:table.column>
                    <flux:table.column>Slug</flux:table.column>
                    <flux:table.column align="end">สินค้า</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->categories as $category)
                        <flux:table.row :key="'c'.$category->id">
                            <flux:table.cell class="font-medium">{{ $category->name }}</flux:table.cell>
                            <flux:table.cell class="font-mono text-xs">{{ $category->slug }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $category->products_count }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="editCategory({{ $category->id }})" />
                                <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteCategory({{ $category->id }})" wire:confirm="ลบหมวดหมู่ {{ $category->name }}?" />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </section>
    </div>

    <flux:modal name="brand-form" class="w-full max-w-md">
        <form wire:submit="saveBrand" class="space-y-4">
            <flux:heading size="lg">{{ $brandId ? 'แก้ไขแบรนด์' : 'เพิ่มแบรนด์' }}</flux:heading>
            <flux:input wire:model="brandName" label="ชื่อแบรนด์" />
            <flux:input type="file" wire:model="brandLogo" accept="image/*" label="โลโก้ (ไม่บังคับ)" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">ยกเลิก</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">บันทึก</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="category-form" class="w-full max-w-md">
        <form wire:submit="saveCategory" class="space-y-4">
            <flux:heading size="lg">{{ $categoryId ? 'แก้ไขหมวดหมู่' : 'เพิ่มหมวดหมู่' }}</flux:heading>
            <flux:input wire:model="categoryName" label="ชื่อหมวดหมู่" />
            <flux:input wire:model="categorySlug" label="Slug" description:trailing="เว้นว่างเพื่อสร้างจากชื่ออัตโนมัติ" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">ยกเลิก</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">บันทึก</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
