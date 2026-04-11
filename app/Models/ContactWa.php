<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactWa extends Model
{
    protected $table = 'contact_wa';

    public $timestamps = false;

    protected $fillable = [
        'nama',
        'no_wa',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
    ];
}
