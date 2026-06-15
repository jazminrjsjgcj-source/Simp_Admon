<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ProcesoAtencion extends Model
{
    protected $guarded = ['id'];
    protected $table   = 'proceso_atencion';

    public function tramite() { return $this->belongsTo(Tramite::class); }
}
