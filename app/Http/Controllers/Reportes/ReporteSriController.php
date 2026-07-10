<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Inertia\Inertia;

class ReporteSriController extends Controller
{
    private array $meses = [
        '', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
    ];

    public function index()
    {
        $empresa = \App\Models\Empresa::findOrFail(session('empresa_activa_id'));

        $periodos = [];
        for ($i = 0; $i < 24; $i++) {
            $fecha = Carbon::now()->subMonths($i)->startOfMonth();
            $m = (int) $fecha->format('m');
            $periodos[] = [
                'value' => $fecha->format('Y-m'),
                'label' => $this->meses[$m] . ' ' . $fecha->format('Y'),
            ];
        }

        return Inertia::render('Reportes/SRI/Index', [
            'empresa' => [
                'id'            => $empresa->id,
                'razon_social'  => $empresa->razon_social,
                'ruc'           => $empresa->ruc,
            ],
            'periodos' => $periodos,
        ]);
    }

    public function ats(Request $request)
    {
        $request->validate([
            'periodo' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'formato' => 'nullable|in:xml,pdf',
        ]);

        [$anio, $mes] = explode('-', $request->periodo);
        $empresa = \App\Models\Empresa::findOrFail(session('empresa_activa_id'));
        $formato = $request->get('formato', 'xml');

        $compras = DB::table('compras as c')
            ->join('proveedores as p', 'p.id', '=', 'c.proveedor_id')
            ->leftJoin('retenciones as r', 'r.compra_id', '=', 'c.id')
            ->where('c.empresa_id', $empresa->id)
            ->where('c.estado', '!=', 'anulada')
            ->whereYear('c.fecha_emision', $anio)
            ->whereMonth('c.fecha_emision', $mes)
            ->select([
                'p.identificacion', 'p.tipo_identificacion', 'p.razon_social',
                'c.num_documento', 'c.num_autorizacion',
                'c.tipo_documento', 'c.sustento_tributario', 'c.fecha_emision',
                'c.subtotal_0', 'c.subtotal_iva', 'c.total_iva', 'c.total',
                'r.numero_completo as num_retencion', 'r.total as total_retencion',
            ])
            ->orderBy('c.fecha_emision')
            ->get();

        $ventas = collect();
        if (Schema::hasTable('facturas')) {
            $ventas = DB::table('facturas as f')
                ->join('clientes as cl', 'cl.id', '=', 'f.cliente_id')
                ->where('f.empresa_id', $empresa->id)
                ->whereYear('f.fecha_emision', $anio)
                ->whereMonth('f.fecha_emision', $mes)
                ->where('f.estado', '!=', 'anulada')
                ->select([
                    'cl.identificacion', 'cl.tipo_identificacion', 'cl.razon_social',
                    'f.numero_completo', 'f.fecha_emision',
                    'f.subtotal_0', 'f.subtotal_15',
                    'f.total_iva', 'f.total',
                ])
                ->orderBy('f.fecha_emision')
                ->get();
        }

        $retenciones = DB::table('retenciones as r')
            ->join('compras as c', 'c.id', '=', 'r.compra_id')
            ->join('proveedores as p', 'p.id', '=', 'c.proveedor_id')
            ->where('r.empresa_id', $empresa->id)
            ->whereYear('r.fecha_emision', $anio)
            ->whereMonth('r.fecha_emision', $mes)
            ->whereNotNull('r.compra_id')
            ->where('r.estado', '!=', 'anulada')
            ->select([
                'r.id', 'r.numero_completo', 'r.fecha_emision', 'r.total',
                'p.identificacion', 'p.tipo_identificacion', 'p.razon_social',
            ])
            ->orderBy('r.fecha_emision')
            ->get();

        if ($formato === 'xml') {
            $xml = $this->generarXmlAts($empresa, $anio, $mes, $compras, $ventas, $retenciones);
            $nombreArchivo = "ATS_{$empresa->ruc}_{$anio}" . str_pad($mes, 2, '0', STR_PAD_LEFT) . '.xml';
            return response($xml, 200, [
                'Content-Type'        => 'application/xml; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
            ]);
        }

        $nombreMes = $this->meses[(int) $mes] ?? $mes;
        $pdf = Pdf::loadView('pdf.sri.ats', compact(
            'empresa', 'anio', 'mes', 'nombreMes', 'compras', 'ventas', 'retenciones'
        ))->setPaper('letter', 'landscape');

        return $pdf->stream("ATS_{$empresa->ruc}_{$anio}" . str_pad($mes, 2, '0', STR_PAD_LEFT) . '.pdf');
    }

    public function formulario103(Request $request)
    {
        $request->validate(['periodo' => 'required|string|regex:/^\d{4}-\d{2}$/']);
        [$anio, $mes] = explode('-', $request->periodo);
        $empresa = \App\Models\Empresa::findOrFail(session('empresa_activa_id'));

        $detalles = DB::table('retencion_detalles as rd')
            ->join('retenciones as r', 'r.id', '=', 'rd.retencion_id')
            ->where('r.empresa_id', $empresa->id)
            ->whereYear('r.fecha_emision', $anio)
            ->whereMonth('r.fecha_emision', $mes)
            ->where('r.estado', '!=', 'anulada')
            ->where('rd.tipo', 'IR')
            ->select([
                'rd.codigo',
                'rd.porcentaje',
                DB::raw('SUM(rd.base_imponible) as base_imponible'),
                DB::raw('SUM(rd.valor_retenido) as valor_retenido'),
                DB::raw('COUNT(*) as num_comprobantes'),
            ])
            ->groupBy('rd.codigo', 'rd.porcentaje')
            ->orderBy('rd.codigo')
            ->get();

        $totalBase      = $detalles->sum('base_imponible');
        $totalRetenido  = $detalles->sum('valor_retenido');
        $nombreMes      = $this->meses[(int) $mes] ?? $mes;

        $pdf = Pdf::loadView('pdf.sri.formulario103', compact(
            'empresa', 'anio', 'mes', 'nombreMes', 'detalles', 'totalBase', 'totalRetenido'
        ))->setPaper('letter', 'portrait');

        return $pdf->stream("F103_{$empresa->ruc}_{$anio}" . str_pad($mes, 2, '0', STR_PAD_LEFT) . '.pdf');
    }

    public function formulario104(Request $request)
    {
        $request->validate(['periodo' => 'required|string|regex:/^\d{4}-\d{2}$/']);
        [$anio, $mes] = explode('-', $request->periodo);
        $empresa = \App\Models\Empresa::findOrFail(session('empresa_activa_id'));

        $ventas = null;
        if (Schema::hasTable('facturas')) {
            $ventas = DB::table('facturas')
                ->where('empresa_id', $empresa->id)
                ->whereYear('fecha_emision', $anio)
                ->whereMonth('fecha_emision', $mes)
                ->where('estado', '!=', 'anulada')
                ->select([
                    DB::raw('COALESCE(SUM(subtotal_0),  0) as total_ventas_0'),
                    DB::raw('COALESCE(SUM(subtotal_15), 0) as total_ventas_gravadas'),
                    DB::raw('COALESCE(SUM(total_iva),   0) as iva_ventas'),
                    DB::raw('COALESCE(SUM(total),       0) as total_ventas'),
                    DB::raw('COUNT(*) as num_facturas'),
                ])
                ->first();
        }

        $compras = DB::table('compras')
            ->where('empresa_id', $empresa->id)
            ->whereYear('fecha_emision', $anio)
            ->whereMonth('fecha_emision', $mes)
            ->where('estado', '!=', 'anulada')
            ->select([
                DB::raw('COALESCE(SUM(subtotal_0),   0) as total_compras_0'),
                DB::raw('COALESCE(SUM(subtotal_iva), 0) as total_compras_gravadas'),
                DB::raw('COALESCE(SUM(total_iva),    0) as iva_compras'),
                DB::raw('COALESCE(SUM(total),        0) as total_compras'),
                DB::raw('COUNT(*) as num_compras'),
            ])
            ->first();

        $ivaRetenido = (float) DB::table('retencion_detalles as rd')
            ->join('retenciones as r', 'r.id', '=', 'rd.retencion_id')
            ->where('r.empresa_id', $empresa->id)
            ->whereYear('r.fecha_emision', $anio)
            ->whereMonth('r.fecha_emision', $mes)
            ->where('r.estado', '!=', 'anulada')
            ->where('rd.tipo', 'IVA')
            ->sum('rd.valor_retenido');

        $ivaVentas   = (float) ($ventas->iva_ventas ?? 0);
        $ivaCausado  = $ivaVentas;
        $ivaCompras  = (float) ($compras->iva_compras ?? 0);
        $credito     = $ivaCompras;
        $ivaPagar    = max(0, $ivaCausado - $credito - $ivaRetenido);
        $nombreMes   = $this->meses[(int) $mes] ?? $mes;

        $pdf = Pdf::loadView('pdf.sri.formulario104', compact(
            'empresa', 'anio', 'mes', 'nombreMes', 'ventas', 'compras',
            'ivaRetenido', 'ivaVentas', 'ivaCausado', 'ivaCompras', 'credito', 'ivaPagar'
        ))->setPaper('letter', 'portrait');

        return $pdf->stream("F104_{$empresa->ruc}_{$anio}" . str_pad($mes, 2, '0', STR_PAD_LEFT) . '.pdf');
    }

    public function anexoIce(Request $request)
    {
        $request->validate(['periodo' => 'required|string|regex:/^\d{4}-\d{2}$/']);
        [$anio, $mes] = explode('-', $request->periodo);
        $empresa   = \App\Models\Empresa::findOrFail(session('empresa_activa_id'));
        $nombreMes = $this->meses[(int) $mes] ?? $mes;

        // Compras con ICE (proveedor → Altamira paga ICE en importaciones)
        $comprasIce = DB::table('compras as c')
            ->join('proveedores as p', 'p.id', '=', 'c.proveedor_id')
            ->where('c.empresa_id', $empresa->id)
            ->where('c.estado', '!=', 'anulada')
            ->whereYear('c.fecha_emision', $anio)
            ->whereMonth('c.fecha_emision', $mes)
            ->where('c.total_ice', '>', 0)
            ->select([
                'c.num_documento', 'c.fecha_emision',
                'p.razon_social as proveedor',
                'c.subtotal_iva as base_imponible',
                'c.total_ice',
                'c.total',
            ])
            ->orderBy('c.fecha_emision')
            ->get();

        // Ventas con ICE
        $ventasIce = collect();
        if (Schema::hasTable('facturas') && Schema::hasColumn('facturas', 'total_ice')) {
            $ventasIce = DB::table('facturas as f')
                ->join('clientes as cl', 'cl.id', '=', 'f.cliente_id')
                ->where('f.empresa_id', $empresa->id)
                ->where('f.estado', '!=', 'anulada')
                ->whereYear('f.fecha_emision', $anio)
                ->whereMonth('f.fecha_emision', $mes)
                ->where('f.total_ice', '>', 0)
                ->select([
                    'f.numero_completo', 'f.fecha_emision',
                    'cl.razon_social as cliente',
                    'f.total_ice',
                    'f.total',
                ])
                ->orderBy('f.fecha_emision')
                ->get();
        }

        $totalIceCompras = (float) $comprasIce->sum('total_ice');
        $totalIceVentas  = (float) $ventasIce->sum('total_ice');
        $sinRegistros    = $comprasIce->isEmpty() && $ventasIce->isEmpty();

        $pdf = Pdf::loadView('pdf.sri.anexo-ice', compact(
            'empresa', 'anio', 'mes', 'nombreMes',
            'comprasIce', 'ventasIce',
            'totalIceCompras', 'totalIceVentas', 'sinRegistros'
        ))->setPaper('letter', 'portrait');

        return $pdf->stream("AnexoICE_{$empresa->ruc}_{$anio}" . str_pad($mes, 2, '0', STR_PAD_LEFT) . '.pdf');
    }

    private function generarXmlAts($empresa, string $anio, string $mes, $compras, $ventas, $retenciones): string
    {
        $mesPad = str_pad($mes, 2, '0', STR_PAD_LEFT);

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><iva/>');
        $xml->addAttribute('id', 'iva');
        $xml->addAttribute('version', '2.0.0');

        $xml->addChild('TipoIDInformante', 'R');
        $xml->addChild('IdInformante', $empresa->ruc);
        $xml->addChild('razonSocial', htmlspecialchars($empresa->razon_social, ENT_XML1, 'UTF-8'));
        $xml->addChild('Anio', $anio);
        $xml->addChild('Mes', $mesPad);
        $xml->addChild('numEstabRuc', str_pad($empresa->cod_establecimiento ?? '001', 3, '0', STR_PAD_LEFT));
        $xml->addChild('totalVentas', number_format((float) $ventas->sum('total'), 2, '.', ''));
        $xml->addChild('codigoOperativo', 'IVA');

        // Compras
        $comprasNode = $xml->addChild('compras');
        foreach ($compras as $c) {
            $partes = explode('-', $c->num_documento ?? '001-001-000000001');
            $det = $comprasNode->addChild('detalleCompras');
            $det->addChild('codSustento', $c->sustento_tributario ?? '01');
            $det->addChild('tpIdProv', $this->mapTipoId($c->tipo_identificacion));
            $det->addChild('idProv', $c->identificacion);
            $det->addChild('tipoComprobante', $this->mapTipoDocAts($c->tipo_documento));
            $det->addChild('parteRel', 'NO');
            $det->addChild('fechaRegistro', Carbon::parse($c->fecha_emision)->format('d/m/Y'));
            $det->addChild('establecimiento', $partes[0] ?? '001');
            $det->addChild('puntoEmision', $partes[1] ?? '001');
            $det->addChild('secuencial', $partes[2] ?? '000000001');
            $det->addChild('fechaEmision', Carbon::parse($c->fecha_emision)->format('d/m/Y'));
            $det->addChild('autorizacion', $c->num_autorizacion ?? '');
            $det->addChild('baseNoGraIva', number_format((float) ($c->subtotal_0 ?? 0), 2, '.', ''));
            $det->addChild('baseImponible', number_format((float) ($c->subtotal_iva ?? 0), 2, '.', ''));
            $det->addChild('baseImpGrav', number_format((float) ($c->subtotal_iva ?? 0), 2, '.', ''));
            $det->addChild('montoIce', '0.00');
            $det->addChild('montoIva', number_format((float) ($c->total_iva ?? 0), 2, '.', ''));
            $det->addChild('valRetBien10', '0.00');
            $det->addChild('valRetServ20', '0.00');
            $det->addChild('valorRetBienes', '0.00');
            $det->addChild('valRetServ50', '0.00');
            $det->addChild('valorRetServicios', '0.00');
            $det->addChild('valRetServ100', '0.00');
            $det->addChild('totbasesImpReemb', '0.00');
            $det->addChild('pagoLocExt', '01');
            $det->addChild('tipoRegi', 'N/A');
            $det->addChild('paisEfecPago', 'N/A');
            $det->addChild('aplicConvDobTrib', 'NO');
            $det->addChild('pagExtSujRetNorLeg', 'NO');
            $det->addChild('pagoRegFis', 'NO');
        }

        // Ventas
        $ventasNode = $xml->addChild('ventas');
        foreach ($ventas as $v) {
            $det = $ventasNode->addChild('detalleVentas');
            $det->addChild('tpIdCliente', $this->mapTipoId($v->tipo_identificacion));
            $det->addChild('idCliente', $v->identificacion);
            $det->addChild('parteRel', 'NO');
            $det->addChild('tipoComprobante', '18');
            $det->addChild('tipoEmision', 'F');
            $det->addChild('numeroComprobantes', '1');
            $det->addChild('baseNoGraIva', number_format((float) ($v->subtotal_0 ?? 0), 2, '.', ''));
            $det->addChild('baseImponible', number_format((float) ($v->subtotal_15 ?? 0), 2, '.', ''));
            $det->addChild('baseImpGrav', number_format((float) ($v->subtotal_15 ?? 0), 2, '.', ''));
            $det->addChild('montoIce', '0.00');
            $det->addChild('montoIva', number_format((float) ($v->total_iva ?? 0), 2, '.', ''));
            $det->addChild('valorRetIva', '0.00');
            $det->addChild('valorRetRenta', '0.00');
        }

        // Retenciones emitidas
        $retNode = $xml->addChild('retenciones');
        foreach ($retenciones as $r) {
            $det = $retNode->addChild('detalleRetencion');
            $det->addChild('tpIdProv', $this->mapTipoId($r->tipo_identificacion));
            $det->addChild('idProv', $r->identificacion);
            $det->addChild('periodoFiscal', $mesPad . '/' . $anio);
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        return $dom->saveXML();
    }

    private function mapTipoId(?string $tipo): string
    {
        return match ($tipo) {
            'ruc'       => '04',
            'cedula'    => '05',
            'pasaporte' => '06',
            default     => '04',
        };
    }

    private function mapTipoDocAts(?string $tipo): string
    {
        return match ($tipo) {
            'liquidacion' => '03',
            'rise'        => '04',
            'exterior'    => '41',
            default       => '01',
        };
    }
}
