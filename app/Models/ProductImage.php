<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'path', 'sort_order'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Files live on the public disk; a relative URL keeps it working regardless of APP_URL. */
    protected function url(): Attribute
    {
        return Attribute::get(fn () => Str::startsWith($this->path, ['http://', 'https://', '/'])
            ? $this->path
            : '/storage/'.$this->path);
    }
}
