<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    public const string PLACEHOLDER_IMAGE = '/images/placeholder-shoe.svg';

    public const array GENDERS = ['men' => 'ผู้ชาย', 'women' => 'ผู้หญิง', 'unisex' => 'Unisex'];

    protected $attributes = ['is_active' => true];

    protected $fillable = [
        'brand_id', 'category_id', 'name', 'slug', 'description', 'price', 'gender', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderByRaw('CAST(size AS DECIMAL(5,1))');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('products.is_active', true);
    }

    /** Average rating rounded to 1 decimal; uses the withAvg() value when it was eager loaded. */
    public function averageRating(): float
    {
        $avg = $this->reviews_avg_rating ?? $this->reviews()->avg('rating');

        return round((float) $avg, 1);
    }

    /** URL of the first image, or the local placeholder. */
    protected function mainImageUrl(): Attribute
    {
        return Attribute::get(function () {
            $image = $this->relationLoaded('images') ? $this->images->first() : $this->images()->first();

            return $image?->url ?? self::PLACEHOLDER_IMAGE;
        });
    }

    public function genderLabel(): string
    {
        return self::GENDERS[$this->gender] ?? $this->gender;
    }

    public function totalStock(): int
    {
        return (int) ($this->variants_sum_stock ?? $this->variants->sum('stock'));
    }
}
