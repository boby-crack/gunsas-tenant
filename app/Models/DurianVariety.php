<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DurianVariety extends Model
{
    use HasFactory;
    protected $fillable = ['name'];
}