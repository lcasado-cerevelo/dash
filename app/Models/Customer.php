<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $guarded = [];
    
    public function customerClients()
    {
        return $this->hasMany(CustomerClient::class, 'customer_id');
    }
    
    public function projects()
    {
        return $this->hasMany(\App\Models\Project::class);
    }

}



