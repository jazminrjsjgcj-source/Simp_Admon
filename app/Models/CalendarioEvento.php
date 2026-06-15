<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CalendarioEvento extends Model
{
    protected $guarded = ['id'];
    protected $table   = 'calendario_eventos';

    public function dependencia() { return $this->belongsTo(Dependencia::class); }
}
