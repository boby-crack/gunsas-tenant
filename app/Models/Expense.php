<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = ['date', 'outlet_id', 'category', 'amount', 'notes'];

    public function outlet() { return $this->belongsTo(Outlet::class); }
}