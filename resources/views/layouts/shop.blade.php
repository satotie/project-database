<!DOCTYPE html>
<html lang="th">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-cream text-zinc-800 antialiased">
        <flux:header sticky class="border-b border-zinc-200/80 bg-cream/90 backdrop-blur">
            <div class="shop-container flex items-center gap-2">
                <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

                <x-app-logo href="{{ route('home') }}" wire:navigate />

                <flux:navbar class="-mb-px ms-4 max-lg:hidden">
                    <flux:navbar.item :href="route('home')" :current="request()->routeIs('home')" wire:navigate>หน้าแรก</flux:navbar.item>
                    <flux:navbar.item :href="route('products.index')" :current="request()->routeIs('products.*')" wire:navigate>สินค้าทั้งหมด</flux:navbar.item>
                    @auth
                        @if (auth()->user()->isCustomer())
                            <flux:navbar.item :href="route('orders.index')" :current="request()->routeIs('orders.*')" wire:navigate>คำสั่งซื้อของฉัน</flux:navbar.item>
                        @endif
                    @endauth
                </flux:navbar>

                <flux:spacer />

                <form action="{{ route('products.index') }}" method="GET" class="me-2 max-md:hidden">
                    <flux:input name="search" size="sm" icon="magnifying-glass" placeholder="ค้นหารองเท้า..." class="w-56" />
                </form>

                @if (! auth()->user()?->isStaff())
                    <livewire:cart-badge />
                @endif

                @auth
                    <flux:dropdown position="bottom" align="end">
                        <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />
                        <flux:menu>
                            <x-user-menu-items />
                        </flux:menu>
                    </flux:dropdown>
                @else
                    <div class="flex items-center gap-2">
                        <flux:button size="sm" variant="ghost" :href="route('login')" wire:navigate>เข้าสู่ระบบ</flux:button>
                        <flux:button size="sm" variant="primary" :href="route('register')" wire:navigate class="max-sm:hidden">สมัครสมาชิก</flux:button>
                    </div>
                @endauth
            </div>
        </flux:header>

        <!-- Mobile menu -->
        <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-zinc-200 bg-cream">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('home') }}" wire:navigate />
                <flux:sidebar.collapse />
            </flux:sidebar.header>
            <form action="{{ route('products.index') }}" method="GET" class="px-2">
                <flux:input name="search" size="sm" icon="magnifying-glass" placeholder="ค้นหารองเท้า..." />
            </form>
            <flux:sidebar.nav>
                <flux:sidebar.item icon="home" :href="route('home')" :current="request()->routeIs('home')" wire:navigate>หน้าแรก</flux:sidebar.item>
                <flux:sidebar.item icon="shopping-bag" :href="route('products.index')" :current="request()->routeIs('products.*')" wire:navigate>สินค้าทั้งหมด</flux:sidebar.item>
                @auth
                    @if (auth()->user()->isCustomer())
                        <flux:sidebar.item icon="shopping-cart" :href="route('cart')" wire:navigate>ตะกร้าสินค้า</flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('orders.index')" wire:navigate>คำสั่งซื้อของฉัน</flux:sidebar.item>
                        <flux:sidebar.item icon="user-circle" :href="route('account')" wire:navigate>ข้อมูลสมาชิก</flux:sidebar.item>
                    @else
                        <flux:sidebar.item icon="squares-2x2" :href="route('staff.dashboard')" wire:navigate>ระบบหลังร้าน</flux:sidebar.item>
                    @endif
                @else
                    <flux:sidebar.item icon="arrow-right-end-on-rectangle" :href="route('login')" wire:navigate>เข้าสู่ระบบ</flux:sidebar.item>
                    <flux:sidebar.item icon="user-plus" :href="route('register')" wire:navigate>สมัครสมาชิก</flux:sidebar.item>
                @endauth
            </flux:sidebar.nav>
        </flux:sidebar>

        <main class="min-h-[70vh]">
            {{ $slot }}
        </main>

        <footer class="mt-16 border-t border-zinc-200 bg-brown-dark text-zinc-300">
            <div class="shop-container grid gap-8 py-10 sm:grid-cols-3">
                <div>
                    <div class="flex items-center gap-2 text-white">
                        <span class="flex size-8 items-center justify-center rounded-lg bg-gold text-brown-dark"><x-app-logo-icon class="size-5 fill-current" /></span>
                        <span class="font-semibold">Shoe Shop</span>
                    </div>
                    <p class="mt-3 text-sm">ร้านรองเท้าออนไลน์ของแท้ 100% จากแบรนด์ชั้นนำ จัดส่งทั่วประเทศ</p>
                </div>
                <div class="text-sm">
                    <p class="font-medium text-white">บริการลูกค้า</p>
                    <ul class="mt-3 space-y-1.5">
                        <li>จัดส่งฟรีเมื่อซื้อครบ ฿3,000 (ส่งแบบปกติ)</li>
                        <li>ชำระเงินผ่านการโอนและแนบสลิป</li>
                        <li>รับสินค้าเองที่ร้านได้</li>
                    </ul>
                </div>
                <div class="text-sm">
                    <p class="font-medium text-white">ติดต่อเรา</p>
                    <ul class="mt-3 space-y-1.5">
                        <li>โทร 02-123-4567</li>
                        <li>LINE @shoeshop</li>
                        <li>เปิดทุกวัน 10:00 - 20:00 น.</li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-white/10 py-4 text-center text-xs text-zinc-400">© {{ date('Y') }} Shoe Shop — โปรเจกต์วิชาฐานข้อมูล</div>
        </footer>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
