<?php

namespace Workdo\Account\Entities;

use Illuminate\Database\Eloquent\Model;

class CustomerClient extends Model
{
    protected $fillable = [
        'customer_id',
        'external_code',
        'name',
        'email',
        'contact',
        'country',
        'state',
        'city',
        'phone',
        'zip',
        'address',
        'notes',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    
    public function users()
    {
        return $this->belongsToMany(\App\Models\User::class, 'customer_clients_users',
            'customer_client_id', 'user_id'
        )->withTimestamps();
    }    
}
