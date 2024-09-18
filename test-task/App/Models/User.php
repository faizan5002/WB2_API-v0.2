<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasFactory;

    // Define the table
    protected $table = 'agent_user_account';

    // Define the fillable fields
    protected $fillable = [
        'agent', 'account', 'password'
    ];

    // Laravel automatically manages timestamps, so you can leave that as is
}
