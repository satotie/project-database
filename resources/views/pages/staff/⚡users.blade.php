<?php

use App\Enums\OrderStatus;
use App\Models\User;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('ผู้ใช้งาน')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $role = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->withCount('orders')
            ->withSum(['orders as total_spent' => fn ($q) => $q->whereIn('status', OrderStatus::revenueValues())], 'total')
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->when(in_array($this->role, ['customer', 'staff'], true), fn ($q) => $q->where('role', $this->role))
            ->orderBy('role', 'desc')
            ->latest('id')
            ->paginate(15);
    }

    private function guardSelf(User $user, string $message): void
    {
        if ($user->is(auth()->user())) {
            throw ValidationException::withMessages(['user' => $message]);
        }
    }

    public function toggleActive(User $user): void
    {
        $this->guardSelf($user, 'ไม่สามารถระงับบัญชีของตัวเองได้');
        $user->update(['is_active' => ! $user->is_active]);
        Flux::toast(text: $user->is_active ? "เปิดใช้งานบัญชี {$user->name} แล้ว" : "ระงับบัญชี {$user->name} แล้ว");
    }

    public function changeRole(User $user, string $role): void
    {
        abort_unless(in_array($role, ['customer', 'staff'], true), 422);
        $this->guardSelf($user, 'ไม่สามารถเปลี่ยนสิทธิ์ของตัวเองได้');
        $user->update(['role' => $role]);
        Flux::toast(variant: 'success', text: "เปลี่ยนสิทธิ์ {$user->name} เป็น ".($role === 'staff' ? 'พนักงาน' : 'ลูกค้า').' แล้ว');
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl" level="1">ผู้ใช้งาน</flux:heading>
        <flux:text>จัดการบัญชีลูกค้าและพนักงาน</flux:text>
    </div>

    <div class="shop-card grid gap-3 p-4 sm:grid-cols-[1fr_200px]">
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="ค้นหาชื่อ อีเมล หรือเบอร์โทร" />
        <flux:select wire:model.live="role">
            <flux:select.option value="">ทุกสิทธิ์</flux:select.option>
            <flux:select.option value="customer">ลูกค้า</flux:select.option>
            <flux:select.option value="staff">พนักงาน</flux:select.option>
        </flux:select>
    </div>

    <flux:error name="user" />

    <div class="shop-card overflow-x-auto p-2">
        <flux:table :paginate="$this->users">
            <flux:table.columns>
                <flux:table.column>ผู้ใช้</flux:table.column>
                <flux:table.column>เบอร์โทร</flux:table.column>
                <flux:table.column>สมัครเมื่อ</flux:table.column>
                <flux:table.column align="end">คำสั่งซื้อ</flux:table.column>
                <flux:table.column align="end">ยอดซื้อสะสม</flux:table.column>
                <flux:table.column>สิทธิ์</flux:table.column>
                <flux:table.column>ใช้งาน</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->users as $user)
                    @php $isMe = $user->is(auth()->user()); @endphp
                    <flux:table.row :key="$user->id">
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                <flux:avatar size="sm" :name="$user->name" :initials="$user->initials()" />
                                <div>
                                    <p class="font-medium text-zinc-900">{{ $user->name }} @if ($isMe) <span class="text-xs text-zinc-500">(คุณ)</span> @endif</p>
                                    <p class="text-xs text-zinc-500">{{ $user->email }}</p>
                                </div>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $user->phone ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ thai_date($user->created_at, false) }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format($user->orders_count) }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-medium">{{ baht($user->total_spent ?? 0) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:select size="sm" class="w-32" :disabled="$isMe" wire:change="changeRole({{ $user->id }}, $event.target.value)" aria-label="สิทธิ์">
                                <flux:select.option value="customer" :selected="$user->role === 'customer'">ลูกค้า</flux:select.option>
                                <flux:select.option value="staff" :selected="$user->role === 'staff'">พนักงาน</flux:select.option>
                            </flux:select>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:switch :checked="$user->is_active" :disabled="$isMe" wire:click="toggleActive({{ $user->id }})" aria-label="เปิดใช้งาน" />
                                @unless ($user->is_active)
                                    <flux:badge size="sm" color="red">ถูกระงับ</flux:badge>
                                @endunless
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500">ไม่พบผู้ใช้</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
