<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentAccount extends Model
{
    protected $connection = 'mysql_master';
    protected $table = 'agent_account_user_api_game';

    protected $fillable = [
        'agent',
        'password',
        'currency',
        'credit',
        'status',
        'api_key_id',
        'api_secret_key',
    ];

    // You might want to add hidden fields if you don't want them to be visible in JSON responses
    protected $hidden = [
        'password',
        'api_secret_key',
    ];
}
