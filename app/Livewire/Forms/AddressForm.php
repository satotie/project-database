<?php

namespace App\Livewire\Forms;

use App\Models\Address;
use App\Models\User;
use Livewire\Form;

class AddressForm extends Form
{
    public ?Address $address = null;

    public string $recipient_name = '';

    public string $phone = '';

    public string $address_line = '';

    public string $district = '';

    public string $province = '';

    public string $postal_code = '';

    public bool $is_default = false;

    protected function rules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'regex:/^[0-9+\-\s]{9,20}$/'],
            'address_line' => ['required', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'digits:5'],
            'is_default' => ['boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'recipient_name' => 'ชื่อผู้รับ',
            'phone' => 'เบอร์โทรศัพท์',
            'address_line' => 'ที่อยู่',
            'district' => 'เขต/อำเภอ',
            'province' => 'จังหวัด',
            'postal_code' => 'รหัสไปรษณีย์',
        ];
    }

    public function setAddress(Address $address): void
    {
        $this->address = $address;
        $this->fill($address->only(['recipient_name', 'phone', 'address_line', 'district', 'province', 'postal_code', 'is_default']));
    }

    public function save(User $user): Address
    {
        $data = $this->validate();

        // The first address is always the default.
        if (! $user->addresses()->when($this->address, fn ($q) => $q->whereKeyNot($this->address->id))->exists()) {
            $data['is_default'] = true;
        }

        if ($data['is_default']) {
            $user->addresses()->update(['is_default' => false]);
        }

        $address = $this->address
            ? tap($this->address)->update($data)
            : $user->addresses()->create($data);

        $this->reset();

        return $address;
    }
}
