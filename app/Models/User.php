<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasFactory;
    protected $guarded = [];


    public function access(){
        return $this->belongsToMany(Access::class, 'access_users');
    }
}
