<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class UnidadAdministrativa extends Model
{
    protected $guarded = ['id'];
    protected $table   = 'unidades_administrativas';

    public function dependencia() { return $this->belongsTo(Dependencia::class); }
}
