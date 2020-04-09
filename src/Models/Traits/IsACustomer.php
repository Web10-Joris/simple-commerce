<?php

namespace DoubleThreeDigital\SimpleCommerce\Models\Traits;

use DoubleThreeDigital\SimpleCommerce\Models\Address;
use DoubleThreeDigital\SimpleCommerce\Models\Order;

trait IsACustomer
{
    public $fields = ['name', 'email', 'password'];

    public function addresses()
    {
        return $this->hasMany(Address::class, 'customer_id', 'id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id', 'id');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ];
    }

    public function editUrl()
    {
        return cp_route('users.edit', $this->id);
    }
}
