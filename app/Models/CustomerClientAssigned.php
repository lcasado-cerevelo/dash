<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerClientAssigned extends Model
{
    use HasFactory;

    protected $table = 'customer_clients_assigned';

    protected $fillable = [
        'customer_client_id',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}