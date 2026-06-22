<?php

namespace App\Http\Controllers;

use App\Models\User;

use App\Models\AccionAgenda;
use App\Models\Firma;
use App\Models\PropuestaRegulatoria;
use App\Models\Tramite;
use App\Services\FirmaDigitalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Controlador del módulo de firmas digitales.
 *
 * Permite a los usuarios autorizados firmar trámites, acciones de agenda
 * y propuestas regulatorias. Cada firma se registra con hash y snapshot
 * del firmante.
 *
 * El listado se filtra por rol:
 *   - enlace: solo sus propios registros
 *   - admin / juridico: todos los registros
 */
class FirmaController extends Controller
{
    public function __construct(private FirmaDigitalService $firmaService) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $tramitesFirmables = Tramite::with('dependencia', 'firmas')
            ->whereIn('estatus', [Tramite::ESTATUS_EN_FIRMA])
            ->when(!$user->veTodoElModulo('tramites'),
                fn ($q) => $q->where('dependencia_id', $user->dependencia_id))
            ->orderBy('estatus')
            ->latest()
            ->get();

        return view('screens.firmas.index', compact('tramitesFirmables'));
    }

    public function mostrar(string $tipo, int $id)
    {
        $modelo = $this->resolverModelo($tipo, $id);
        $modelo->load('firmas.firmante');

        $firmas        = $this->firmaService->firmasActivas($modelo);
        $integridades  = $firmas->mapWithKeys(fn ($f) => [
            $f->id => $this->firmaService->verificarIntegridad($f),
        ]);

        return view('screens.firmas.mostrar', compact('modelo', 'tipo', 'firmas', 'integridades'));
    }

    public function firmar(Request $request, string $tipo, int $id)
    {
        $request->validate([
            'tipo_firma'     => 'required|in:aceptacion_sujeto,aceptacion_enlace,aprobacion_revisora,aprobacion_juridico,firma_fisica',
            'observaciones'  => 'nullable|string|max:1000',
        ]);

        $modelo = $this->resolverModelo($tipo, $id);
        $user   = $request->user();

        if (!$this->usuarioPuedeFirmar($user, $request->tipo_firma)) {
            return back()->with('error', 'No tiene permiso para registrar este tipo de firma.');
        }

        // Las aceptaciones de sujeto y enlace son locales: solo las firma quien
        // pertenece a la dependencia del registro. Las aprobaciones de revisora
        // y jurídico son transversales, así que no se restringen por dependencia.
        $firmasLocales = [Firma::TIPO_ACEPTACION_SUJETO, Firma::TIPO_ACEPTACION_ENLACE];
        if (in_array($request->tipo_firma, $firmasLocales, true)
            && !$user->isRol(User::ROL_ADMIN)
            && !$user->esDeSuDependencia($modelo)) {
            return back()->with('error', 'Solo puede firmar registros de su propia dependencia.');
        }

        // Para trámites: solo se puede firmar en etapa de firma
        if ($tipo === 'tramite' && $modelo->estatus !== 'en_firma') {
            return back()->with('error', 'El trámite debe estar en etapa de firma.');
        }

        if ($this->firmaService->yaFirmadoPor($modelo, $request->tipo_firma)) {
            return back()->with('error', 'Este registro ya tiene una firma activa de este tipo.');
        }

        // ── FIRMA FUTURA (FIEL) ──────────────────────────────────────────
        // Hoy TODOS los tipos se firman con hash (FirmaDigitalService::firmar).
        // Cuando se integre la e.firma del SAT, aquí se bifurca:
        //
        //   if (Firma::tipoUsaFiel($request->tipo_firma)) {
        //       // Solo sujeto y enlace: validación del documento oficial.
        //       // Pedir .cer/.key + contraseña, generar el sello con la
        //       // llave privada y guardarlo en certificado_emisor,
        //       // certificado_serie, firmante_rfc y metadata_firmante.
        //       return $this->firmarConFiel($modelo, $user, $request);
        //   }
        //
        // Revisora y jurídico (aprobación) NUNCA pasan por aquí: su visto
        // bueno se queda en hash común.
        // ─────────────────────────────────────────────────────────────────

        $this->firmaService->firmar(
            $modelo,
            $user,
            $request->tipo_firma,
            $request,
            $request->observaciones
        );

        // Auto-completar según las firmas requeridas por módulo:
        //   Trámite      → sujeto + enlace + revisora (las tres)
        //   Agenda SyD   → sujeto + enlace
        //   Agenda Reg.  → sujeto + enlace
        //   AIR          → solo aprobacion_revisora
        if ($modelo instanceof Tramite && $modelo->estatus === Tramite::ESTATUS_EN_FIRMA) {
            $firmasActivas = $this->firmaService->firmasActivas($modelo)->pluck('tipo')->toArray();
            $tieneSujeto   = in_array('aceptacion_sujeto',  $firmasActivas);
            $tieneEnlace   = in_array('aceptacion_enlace',  $firmasActivas);
            $tieneRevisora = in_array('aprobacion_revisora', $firmasActivas);
            if ($tieneSujeto && $tieneEnlace && $tieneRevisora) {
                $modelo->update(['estatus' => Tramite::ESTATUS_COMPLETADO]);
            }
        }

        if ($modelo instanceof AccionAgenda && $modelo->estatus === AccionAgenda::ESTATUS_EN_FIRMA) {
            $firmasActivas = $this->firmaService->firmasActivas($modelo)->pluck('tipo')->toArray();
            $tieneSujeto   = in_array('aceptacion_sujeto', $firmasActivas);
            $tieneEnlace   = in_array('aceptacion_enlace', $firmasActivas);
            if ($tieneSujeto && $tieneEnlace) {
                $modelo->update(['estatus' => AccionAgenda::ESTATUS_COMPLETADO]);
            }
        }

        if ($modelo instanceof PropuestaRegulatoria && $modelo->estatus === 'en_firma') {
            $firmasActivas = $this->firmaService->firmasActivas($modelo)->pluck('tipo')->toArray();
            $tieneSujeto   = in_array('aceptacion_sujeto', $firmasActivas);
            $tieneEnlace   = in_array('aceptacion_enlace', $firmasActivas);
            if ($tieneSujeto && $tieneEnlace) {
                $modelo->update(['estatus' => 'completada']);
            }
        }

        if ($modelo instanceof AnalisisImpactoRegulatorio && $modelo->estatus === 'enviado') {
            $firmasActivas = $this->firmaService->firmasActivas($modelo)->pluck('tipo')->toArray();
            if (in_array('aprobacion_revisora', $firmasActivas)) {
                $modelo->update(['estatus' => 'dictaminado']);
            }
        }

        return redirect()->route('firmas.mostrar', ['tipo' => $tipo, 'id' => $modelo->id])
            ->with('success', 'Firma registrada correctamente.');
    }

    public function revocar(Request $request, Firma $firma)
    {
        $request->validate([
            'motivo' => 'required|string|min:10|max:1000',
        ]);

        if (!$this->usuarioPuedeFirmar($request->user(), $firma->tipo)) {
            return back()->with('error', 'No tiene permiso para revocar este tipo de firma.');
        }

        $this->firmaService->revocar($firma, $request->user(), $request->motivo);

        return back()->with('success', 'Firma revocada. La acción queda registrada en bitácora.');
    }

    public function verificar(Firma $firma)
    {
        $valida = $this->firmaService->verificarIntegridad($firma);

        return response()->json([
            'firma_id'  => $firma->id,
            'integra'   => $valida,
            'mensaje'   => $valida
                ? 'La firma es íntegra: el hash coincide con la cadena original.'
                : 'La firma NO es íntegra: el hash no coincide con la cadena original.',
        ]);
    }

    // ========== Internos ==========

    private function resolverModelo(string $tipo, int $id): Model
    {
        return match($tipo) {
            'tramite'             => Tramite::findOrFail($id),
            'agenda'              => AccionAgenda::findOrFail($id),
            'propuesta_regulatoria' => PropuestaRegulatoria::findOrFail($id),
            default               => abort(404, 'Tipo de registro firmable no soportado.'),
        };
    }

    /**
     * Reglas de quién puede firmar qué tipo.
     * Esto puede migrarse a permisos ACL granulares en el futuro.
     */
    private function usuarioPuedeFirmar($user, string $tipoFirma): bool
    {
        return match($tipoFirma) {
            Firma::TIPO_ACEPTACION_SUJETO   => $user->isAnyRol([User::ROL_SUJETO, User::ROL_ADMIN]),
            Firma::TIPO_ACEPTACION_ENLACE   => $user->isAnyRol([User::ROL_ENLACE, User::ROL_ADMIN]),
            Firma::TIPO_APROBACION_REVISORA => $user->isAnyRol([User::ROL_REVISORA, User::ROL_ADMIN]),
            Firma::TIPO_APROBACION_JURIDICO => $user->isAnyRol([User::ROL_JURIDICO, User::ROL_ADMIN]),
            Firma::TIPO_FIRMA_FISICA        => $user->isAnyRol([User::ROL_ADMIN]),
            default                          => false,
        };
    }
}
