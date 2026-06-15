<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class FichaPortal extends Model
{
    protected $guarded = ['id'];
    protected $table   = 'ficha_portal';

    protected $casts = [
        'horarios_json'   => 'array',   // Fase F.4: estructura JSON de horarios
        'requiere_cita'   => 'boolean',
    ];

    public function tramite() { return $this->belongsTo(Tramite::class); }
}
