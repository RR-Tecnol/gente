<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FolhaSetor extends Model
{
    use HasFactory;
    protected $table = "FOLHA_SETOR";
    protected $primaryKey = "FOLHA_SETOR_ID";
    public $timestamps = false;
    public static $snakeAttributes = false;
    protected $fillable = [
        'FOLHA_ID',
        'SETOR_ID',
    ];
}
