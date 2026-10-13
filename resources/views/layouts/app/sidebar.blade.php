<!DOCTYPE html>
<html lang="th">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-cream text-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-white">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('staff.dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group heading="ภาพรวม" class="grid">
                    <flux:sidebar.item icon="home" :href="route('staff.dashboard')" :current="request()->routeIs('staff.dashboard')" wire:navigate>แดชบอร์ด</flux:sidebar.item>
                    <flux:sidebar.item icon="chart-bar" :href="route('staff.reports')" :current="request()->routeIs('staff.reports')" wire:navigate>รายงานยอดขาย</flux:sidebar.item>
                </flux:sidebar.group>
                <flux:sidebar.group heading="การขาย" class="grid">
                    <flux:sidebar.item icon="clipboard-document-list" :href="route('staff.orders.index')" :current="request()->routeIs('staff.orders.*')" wire:navigate>คำสั่งซื้อ</flux:sidebar.item>
                    <flux:sidebar.item icon="ticket" :href="route('staff.coupons')" :current="request()->routeIs('staff.coupons')" wire:navigate>คูปอง</flux:sidebar.item>
                </flux:sidebar.group>
                <flux:sidebar.group heading="สินค้า" class="grid">
                    <flux:sidebar.item icon="shopping-bag" :href="route('staff.products.index')" :current="request()->routeIs('staff.products.*')" wire:navigate>สินค้า</flux:sidebar.item>
                    <flux:sidebar.item icon="tag" :href="route('staff.catalog')" :current="request()->routeIs('staff.catalog')" wire:navigate>แบรนด์และหมวดหมู่</flux:sidebar.item>
                </flux:sidebar.group>
                <flux:sidebar.group heading="ผู้ใช้" class="grid">
                    <flux:sidebar.item icon="users" :href="route('staff.users')" :current="request()->routeIs('staff.users')" wire:navigate>ผู้ใช้งาน</flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="building-storefront" :href="route('home')" wire:navigate>ไปที่หน้าร้าน</flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden bg-white border-b border-zinc-200">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <flux:spacer />
            <flux:dropdown position="top" align="end">
                <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />
                <flux:menu>
                    <x-user-menu-items />
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
