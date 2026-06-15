<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class FundamentoJuridico extends Model
{
    protected $guarded = ['id'];
    protected $table   = 'fundamento_juridico';

    public function tramite() { return $this->belongsTo(Tramite::class); }

    public function regulacion() { return $this->belongsTo(Regulacion::class); }
}
