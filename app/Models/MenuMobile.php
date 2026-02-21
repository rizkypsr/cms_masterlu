<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuMobile extends Model
{
    protected $table = 'menu_mobile';

    public $timestamps = false;

    protected $fillable = [
        'nama',
        'code',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];
}
