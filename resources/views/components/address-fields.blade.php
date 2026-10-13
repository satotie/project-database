@props(['model' => 'form'])
<div class="grid gap-4 sm:grid-cols-2">
    <flux:input wire:model="{{ $model }}.recipient_name" label="ชื่อผู้รับ" />
    <flux:input wire:model="{{ $model }}.phone" label="เบอร์โทรศัพท์" type="tel" placeholder="08x-xxx-xxxx" />
    <div class="sm:col-span-2">
        <flux:input wire:model="{{ $model }}.address_line" label="ที่อยู่ (บ้านเลขที่ ซอย ถนน)" />
    </div>
    <flux:input wire:model="{{ $model }}.district" label="เขต/อำเภอ" />
    <flux:input wire:model="{{ $model }}.province" label="จังหวัด" />
    <flux:input wire:model="{{ $model }}.postal_code" label="รหัสไปรษณีย์" maxlength="5" inputmode="numeric" />
    <div class="flex items-end pb-2">
        <flux:checkbox wire:model="{{ $model }}.is_default" label="ตั้งเป็นที่อยู่หลัก" />
    </div>
</div>
