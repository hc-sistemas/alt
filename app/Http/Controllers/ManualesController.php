<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManualesController extends Controller
{
    private function catalogo(): array
    {
        return [
            'bancos' => [
                'titulo'      => 'Bancos',
                'descripcion' => 'Movimientos bancarios, conciliación, Datafast, cheques y reportes.',
                'tipo'        => 'dinamico',
                'route'       => 'bancos.manual-pdf',
            ],
            'compras' => [
                'titulo'      => 'Compras',
                'descripcion' => 'Facturas de compra, proveedores, CxP, anticipos, importaciones y devoluciones.',
                'tipo'        => 'dinamico',
                'route'       => 'manuales.compras-pdf',
            ],
            'contabilidad' => [
                'titulo'      => 'Contabilidad',
                'descripcion' => 'Plan de cuentas, asientos automáticos, ejercicios, parámetros y reportes contables.',
                'tipo'        => 'dinamico',
                'route'       => 'manuales.contabilidad-pdf',
            ],
            'configuracion' => [
                'titulo'      => 'Configuración',
                'descripcion' => 'Usuarios, permisos, empresa y parámetros del sistema.',
                'tipo'        => 'estatico',
                'archivo'     => storage_path('app/public/manuales/Manual_Configuracion.pdf'),
            ],
            'inventario' => [
                'titulo'      => 'Inventario',
                'descripcion' => 'Productos, kárdex, bodegas, traslados y activos fijos.',
                'tipo'        => 'estatico',
                'archivo'     => storage_path('app/public/manuales/Manual_Inventario.pdf'),
            ],
            'personas' => [
                'titulo'      => 'Personas',
                'descripcion' => 'Clientes, proveedores y transportistas del sistema.',
                'tipo'        => 'estatico',
                'archivo'     => storage_path('app/public/manuales/Manual_Personas.pdf'),
            ],
        ];
    }

    public function index(): Response
    {
        $catalogo = $this->catalogo();

        $manuales = collect($catalogo)->map(function ($m, $clave) {
            if ($m['tipo'] === 'dinamico') {
                $url        = route($m['route']);
                $disponible = true;
            } else {
                $url        = route('manuales.pdf', $clave);
                $disponible = file_exists($m['archivo']);
            }

            return [
                'clave'       => $clave,
                'titulo'      => $m['titulo'],
                'descripcion' => $m['descripcion'],
                'url'         => $url,
                'disponible'  => $disponible,
            ];
        })->values();

        return Inertia::render('Manuales/Index', [
            'manuales' => $manuales,
        ]);
    }

    public function pdf(string $clave): BinaryFileResponse
    {
        $catalogo = $this->catalogo();

        if (!isset($catalogo[$clave]) || $catalogo[$clave]['tipo'] !== 'estatico') {
            abort(404);
        }

        $ruta = $catalogo[$clave]['archivo'];

        if (!file_exists($ruta)) {
            abort(404, 'Manual no disponible.');
        }

        return response()->file($ruta, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="manual-' . $clave . '.pdf"',
        ]);
    }
}
