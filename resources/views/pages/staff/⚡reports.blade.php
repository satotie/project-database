<?php

use App\Services\SalesReportService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('รายงานยอดขาย')] class extends Component {
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->from = $this->from ?: now()->subDays(29)->format('Y-m-d');
        $this->to = $this->to ?: now()->format('Y-m-d');
    }

    public function updated(): void
    {
        $this->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ], ['to.after_or_equal' => 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น'], ['from' => 'วันที่เริ่มต้น', 'to' => 'วันที่สิ้นสุด']);

        unset($this->report);
    }

    public function preset(int $days): void
    {
        $this->from = now()->subDays($days - 1)->format('Y-m-d');
        $this->to = now()->format('Y-m-d');
        unset($this->report);
    }

    #[Computed]
    public function report(): array
    {
        $service = new SalesReportService(Carbon::parse($this->from), Carbon::parse($this->to));

        return [
            'summary' => $service->summary(),
            'daily' => $service->revenuePerDay(),
            'top' => $service->topProducts(10),
            'brands' => $service->revenueByBrand(),
            'categories' => $service->revenueByCategory(),
        ];
    }
}; ?>

@assets
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
@endassets

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">รายงานยอดขาย</flux:heading>
            <flux:text>นับเฉพาะคำสั่งซื้อสถานะ ชำระเงินแล้ว / กำลังเตรียม / จัดส่งแล้ว / ได้รับสินค้าแล้ว</flux:text>
        </div>
        <flux:button icon="arrow-down-tray" :href="route('staff.reports.export', ['from' => $from, 'to' => $to])">ส่งออก CSV</flux:button>
    </div>

    <div class="shop-card flex flex-wrap items-end gap-3 p-4">
        <flux:input type="date" wire:model.live="from" label="ตั้งแต่วันที่" />
        <flux:input type="date" wire:model.live="to" label="ถึงวันที่" />
        <div class="flex gap-1">
            <flux:button size="sm" variant="ghost" wire:click="preset(7)">7 วัน</flux:button>
            <flux:button size="sm" variant="ghost" wire:click="preset(30)">30 วัน</flux:button>
            <flux:button size="sm" variant="ghost" wire:click="preset(90)">90 วัน</flux:button>
        </div>
        <flux:error name="to" class="w-full" />
    </div>

    @php($s = $this->report['summary'])
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="รายได้รวม" :value="baht($s['revenue'])" icon="banknotes" />
        <x-stat-card label="จำนวนคำสั่งซื้อ" :value="number_format($s['orders'])" icon="shopping-bag" />
        <x-stat-card label="ยอดเฉลี่ยต่อคำสั่งซื้อ" :value="baht($s['average'])" icon="calculator" />
        <x-stat-card label="ส่วนลดที่ให้ไป" :value="baht($s['discount'])" icon="ticket" />
    </div>

    <section class="shop-card p-5">
        <flux:heading size="lg" class="mb-4">รายได้รายวัน</flux:heading>
        <div class="h-72" wire:key="chart-{{ $from }}-{{ $to }}"
            x-data="{
                init() {
                    const data = @js($this->report['daily']);
                    new Chart(this.$refs.canvas, {
                        type: 'bar',
                        data: {
                            labels: Object.keys(data).map(d => d.slice(8, 10) + '/' + d.slice(5, 7)),
                            datasets: [{ label: 'รายได้ (บาท)', data: Object.values(data), backgroundColor: '#c9a063', borderRadius: 6 }],
                        },
                        options: {
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => '฿' + c.parsed.y.toLocaleString('th-TH') } } },
                            scales: { y: { beginAtZero: true, ticks: { callback: (v) => '฿' + v.toLocaleString('th-TH') } }, x: { grid: { display: false } } },
                        },
                    });
                },
            }">
            <canvas x-ref="canvas"></canvas>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="shop-card p-5">
            <flux:heading size="lg" class="mb-3">สินค้าขายดี 10 อันดับ (ตามจำนวนคู่)</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>#</flux:table.column>
                    <flux:table.column>สินค้า</flux:table.column>
                    <flux:table.column align="end">จำนวน</flux:table.column>
                    <flux:table.column align="end">ยอดขาย</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->report['top'] as $i => $row)
                        <flux:table.row :key="'t'.$row->id">
                            <flux:table.cell>{{ $i + 1 }}</flux:table.cell>
                            <flux:table.cell><p class="font-medium">{{ $row->name }}</p><p class="text-xs text-zinc-500">{{ $row->brand }}</p></flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($row->quantity) }}</flux:table.cell>
                            <flux:table.cell align="end">{{ baht($row->revenue) }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="4" class="py-6 text-center text-zinc-500">ไม่มียอดขายในช่วงนี้</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </section>

        <div class="space-y-6">
            @foreach (['brands' => 'ยอดขายตามแบรนด์', 'categories' => 'ยอดขายตามหมวดหมู่'] as $key => $title)
                @php($rows = $this->report[$key])
                @php($max = max(1, (float) $rows->max('revenue')))
                <section class="shop-card p-5">
                    <flux:heading size="lg" class="mb-3">{{ $title }}</flux:heading>
                    <div class="space-y-3">
                        @forelse ($rows as $row)
                            <div>
                                <div class="mb-1 flex justify-between text-sm">
                                    <span class="font-medium">{{ $row->name }} <span class="text-xs text-zinc-500">· {{ number_format($row->quantity) }} คู่</span></span>
                                    <span>{{ baht($row->revenue) }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-zinc-100"><div class="h-full rounded-full bg-gold" style="width: {{ $row->revenue / $max * 100 }}%"></div></div>
                            </div>
                        @empty
                            <flux:text>ไม่มียอดขายในช่วงนี้</flux:text>
                        @endforelse
                    </div>
                </section>
            @endforeach
            <flux:text size="sm">* ยอดขายตามแบรนด์/หมวดหมู่ คิดจากยอดสินค้าหลังหักส่วนลด ไม่รวมค่าจัดส่ง</flux:text>
        </div>
    </div>
</div>
