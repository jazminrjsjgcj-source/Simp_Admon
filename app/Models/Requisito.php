<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Requisito extends Model
{
    protected $guarded = ['id'];
    protected $table   = 'requisitos';

    public function tramite() { return $this->belongsTo(Tramite::class); }
}
