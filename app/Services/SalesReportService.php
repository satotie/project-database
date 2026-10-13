<?php

namespace App\Services;

use App\Enums\OrderStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sales report queries written with the query builder (JOIN / GROUP BY / SUM).
 * Only orders whose status is PAID, PREPARING, SHIPPED or DELIVERED count as sales.
 */
class SalesReportService
{
    public function __construct(
        private CarbonInterface $from,
        private CarbonInterface $to,
    ) {
        $this->from = $from->copy()->startOfDay();
        $this->to = $to->copy()->endOfDay();
    }

    /** Base query: sold orders inside the date range. */
    private function orders(): Builder
    {
        return DB::table('orders')
            ->whereIn('orders.status', OrderStatus::revenueValues())
            ->whereBetween('orders.created_at', [$this->from, $this->to]);
    }

    /**
     * SELECT SUM(total), COUNT(*), AVG(total), SUM(discount_amount) FROM orders WHERE ...
     *
     * @return array{revenue: float, orders: int, average: float, discount: float}
     */
    public function summary(): array
    {
        $row = $this->orders()
            ->selectRaw('COALESCE(SUM(total), 0) AS revenue')
            ->selectRaw('COUNT(*) AS orders')
            ->selectRaw('COALESCE(AVG(total), 0) AS average')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) AS discount')
            ->first();

        return [
            'revenue' => (float) $row->revenue,
            'orders' => (int) $row->orders,
            'average' => round((float) $row->average, 2),
            'discount' => (float) $row->discount,
        ];
    }

    /**
     * Revenue per day — GROUP BY DATE(created_at). Days without sales are filled with 0.
     *
     * @return Collection<string, float> keyed by Y-m-d
     */
    public function revenuePerDay(): Collection
    {
        $rows = $this->orders()
            ->selectRaw('DATE(orders.created_at) AS day, SUM(orders.total) AS revenue, COUNT(*) AS orders')
            ->groupByRaw('DATE(orders.created_at)')
            ->orderBy('day')
            ->pluck('revenue', 'day');

        $days = collect();
        for ($d = $this->from->copy(); $d->lte($this->to); $d = $d->addDay()) {
            $days[$d->format('Y-m-d')] = (float) ($rows[$d->format('Y-m-d')] ?? 0);
        }

        return $days;
    }

    /**
     * Top products by quantity sold:
     * orders ⋈ order_items ⋈ product_variants ⋈ products ⋈ brands, GROUP BY product.
     */
    public function topProducts(int $limit = 10): Collection
    {
        return $this->orders()
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('brands', 'brands.id', '=', 'products.brand_id')
            ->groupBy('products.id', 'products.name', 'brands.name')
            ->select('products.id', 'products.name', 'brands.name AS brand')
            ->selectRaw('SUM(order_items.quantity) AS quantity')
            ->selectRaw('SUM(order_items.line_total - order_items.discount_amount) AS revenue')
            ->orderByDesc('quantity')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();
    }

    /** Net item revenue (after item discounts) per brand. */
    public function revenueByBrand(): Collection
    {
        return $this->orders()
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('brands', 'brands.id', '=', 'products.brand_id')
            ->groupBy('brands.id', 'brands.name')
            ->select('brands.name')
            ->selectRaw('SUM(order_items.quantity) AS quantity')
            ->selectRaw('SUM(order_items.line_total - order_items.discount_amount) AS revenue')
            ->orderByDesc('revenue')
            ->get();
    }

    /** Net item revenue (after item discounts) per category. */
    public function revenueByCategory(): Collection
    {
        return $this->orders()
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->groupBy('categories.id', 'categories.name')
            ->select('categories.name')
            ->selectRaw('SUM(order_items.quantity) AS quantity')
            ->selectRaw('SUM(order_items.line_total - order_items.discount_amount) AS revenue')
            ->orderByDesc('revenue')
            ->get();
    }
}
