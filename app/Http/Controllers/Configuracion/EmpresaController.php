<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\CentroCosto;
use App\Models\Empresa;
use App\Models\Secuencial;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmpresaController extends Controller
{
    public function __construct(private AuditoriaService $auditoria) {}

    public function index(): Response
    {
        $empresaId = session('empresa_activa_id');
        $empresa = Empresa::with(['centrosCosto', 'secuenciales'])
            ->findOrFail($empresaId);

        return Inertia::render('Configuracion/Empresa/Index', [
            'empresa' => array_merge($empresa->toArray(), [
                'codigo_establecimiento' => $empresa->cod_establecimiento ?? '001',
                'codigo_punto_emision' => $empresa->cod_punto_emision ?? '001',
                'siguiente_factura' => DB::table('secuenciales')
                    ->where('empresa_id', $empresa->id)->where('tipo_documento', 'FAC')
                    ->where('establecimiento', $empresa->cod_establecimiento ?? '001')
                    ->where('punto_emision', $empresa->cod_punto_emision ?? '001')
                    ->value('siguiente'),
            ]),
            'centros_costo' => $empresa->centrosCosto,
            'secuenciales' => $empresa->secuenciales,
            'firma' => [
                'cargada' => (bool) ($empresa->firma_electronica_path && $empresa->firma_electronica_pass),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $empresa = Empresa::findOrFail($empresaId);

        $data = $request->validate([
            'razon_social' => ['required', 'string', 'max:300'],
            'nombre_comercial' => ['required', 'string', 'max:300'],
            'ruc' => ['required', 'string', 'size:13'],
            'direccion_matriz' => ['nullable', 'string', 'max:500'],
            'email_notificaciones' => ['nullable', 'email'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'ambiente_sri' => ['required', 'in:1,2'],
            'codigo_establecimiento' => ['required', 'string', 'size:3'],
            'codigo_punto_emision' => ['required', 'string', 'size:3'],
            'obligado_contabilidad' => ['boolean'],
            'contribuyente_especial' => ['boolean'],
            'numero_resolucion_agente_retencion' => ['nullable', 'string', 'max:50'],
            'siguiente_factura' => ['nullable', 'integer', 'min:1', 'max:999999999'],
        ]);

        $est = str_pad(preg_replace('/\D/', '', $data['codigo_establecimiento']), 3, '0', STR_PAD_LEFT);
        $pto = str_pad(preg_replace('/\D/', '', $data['codigo_punto_emision']), 3, '0', STR_PAD_LEFT);
        $siguiente = $data['siguiente_factura'] ?? null;
        unset($data['codigo_establecimiento'], $data['codigo_punto_emision'], $data['siguiente_factura']);

        // Continuar la numeración del sistema anterior: el próximo número no puede ser menor
        // o igual al último ya emitido en este establecimiento/punto (el SRI lo rechazaría).
        if ($siguiente !== null) {
            $ultimo = (int) DB::table('facturas')
                ->where('empresa_id', $empresa->id)->where('establecimiento', $est)->where('punto_emision', $pto)
                ->max(DB::raw('CAST(secuencial AS INTEGER)'));
            if ($siguiente <= $ultimo) {
                return back()->withErrors(['siguiente_factura' => sprintf(
                    'Ya existe la factura %s-%s-%09d en el sistema; el próximo número debe ser mayor (mínimo %d).',
                    $est, $pto, $ultimo, $ultimo + 1
                )])->withInput();
            }
        }

        DB::transaction(function () use ($empresa, $data, $est, $pto, $siguiente) {
            $empresa->update($data);
            $empresa->guardarPuntoEmision($est, $pto);

            if ($siguiente !== null) {
                DB::table('secuenciales')->updateOrInsert(
                    ['empresa_id' => $empresa->id, 'tipo_documento' => 'FAC', 'establecimiento' => $est, 'punto_emision' => $pto],
                    ['siguiente' => $siguiente, 'inicializado_desde_migracion' => true],
                );
            }
        });

        $this->auditoria->documento('editar', 'configuracion', 'empresas', $empresa->id,
            "Datos de empresa {$empresa->nombre_comercial} actualizados");

        return back()->with('success', 'Empresa actualizada correctamente.');
    }

    public function actualizarSecuencial(Request $request, Secuencial $secuencial): RedirectResponse
    {
        $data = $request->validate([
            'siguiente' => ['required', 'integer', 'min:1'],
        ]);

        $secuencial->update($data);

        return back()->with('success', 'Secuencial actualizado.');
    }

    /** Carga el certificado .p12/.pfx de la empresa activa (firma electrónica del SRI). */
    public function subirFirma(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'max:512'],
            'clave'   => ['required', 'string', 'max:200'],
        ]);

        $archivo = $request->file('archivo');
        if (! in_array(strtolower($archivo->getClientOriginalExtension()), ['p12', 'pfx'], true)) {
            return back()->withErrors(['archivo' => 'El archivo debe ser un certificado .p12 o .pfx.']);
        }

        $contenido = file_get_contents($archivo->getRealPath());
        $vence = null;
        $aviso = null;

        // Valida clave y vigencia. OpenSSL 3 no abre .p12 antiguos (RC2); en ese caso no se
        // puede distinguir "clave incorrecta" de "formato antiguo", así que se acepta con aviso.
        if (@openssl_pkcs12_read($contenido, $certs, $request->clave)) {
            $info = openssl_x509_parse($certs['cert']);
            $vence = date('Y-m-d', $info['validTo_time_t']);
            if ($info['validTo_time_t'] < time()) {
                return back()->withErrors(['archivo' => "El certificado está vencido ({$vence})."]);
            }
        } else {
            $aviso = ' No se pudo validar la clave aquí; se comprobará al firmar la primera factura.';
        }

        $empresa = Empresa::findOrFail(session('empresa_activa_id'));

        // El certificado debe ser del RUC de ESTA empresa; si no, el SRI rechaza la firma (error 39).
        if (!$aviso && openssl_x509_export($certs['cert'], $pem)) {
            $der = (string) base64_decode(preg_replace('/-----[^-]+-----|\s/', '', $pem));
            if (!str_contains($der, $empresa->ruc) && !str_contains($der, substr($empresa->ruc, 0, 10))) {
                $aviso = " ATENCIÓN: no se encontró el RUC {$empresa->ruc} dentro del certificado; verifique que sea el de {$empresa->nombre_comercial}.";
            }
        }

        $ruta = trim(config('sri.dir_firmas'), '/') . "/empresa_{$empresa->id}.p12";
        \Illuminate\Support\Facades\Storage::disk('local')->put($ruta, $contenido);

        $empresa->update([
            'firma_electronica_path' => $ruta,
            'firma_electronica_pass' => Crypt::encryptString($request->clave),
        ]);

        $this->auditoria->documento('editar', 'configuracion', 'empresas', $empresa->id,
            "Firma electrónica de {$empresa->nombre_comercial} actualizada");

        return back()->with('success', 'Firma electrónica guardada.' . ($vence ? " Vence el {$vence}." : '') . $aviso);
    }
}
