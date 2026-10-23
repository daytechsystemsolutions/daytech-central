<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ideia extends Model
{
    protected $table = 'ideias';

    protected $fillable = ['marca_id', 'texto'];
}
