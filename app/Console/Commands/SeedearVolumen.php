<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Seeder de VOLUMEN ALTO para pruebas de integridad y rendimiento a escala.
 * Distinto de los seeders de demo pequeños (PersonasSeeder, IntegracionSeeder, etc.).
 * NO pensado para producción — es una herramienta de desarrollo/testing.
 *
 * Genera datos ficticios pero realistas, distribuidos en ~2.5 años de operación,
 * con contabilidad de partida doble correcta (cada asiento nace de una
 * transacción real: factura, compra, nómina, movimiento bancario o liquidación
 * de taller). Usa inserts masivos (DB::table()->insert() en chunks) en vez de
 * Eloquent/Servicios por registro para poder generar miles de filas en minutos
 * en vez de horas.
 */
class SeedearVolumen extends Command
{
    protected $signature = 'altamira:seedear-volumen
                            {--clientes=850}
                            {--proveedores=220}
                            {--productos=550}
                            {--colaboradores=26}
                            {--compras=2200}
                            {--facturas=4200}
                            {--movimientos=3200}
                            {--taller=1600}
                            {--prestamos=45}
                            {--anio-inicio=2024}';

    protected $description = 'Genera datos de volumen alto (miles de registros) para pruebas de integridad y rendimiento — SOLO DESARROLLO';

    private int $empresaId = 1;
    private int $centroCostoMatriz = 1;
    private int $centroCostoTaller = 3;
    private array $cta = [];
    private array $bodegas = [];
    private array $bancosCajas = [];
    private array $ejercicios = []; // 'YYYY-M' => id
    private Carbon $hoy;
    private Carbon $inicio;
    private array $stock = []; // [producto_id][bodega_id] => cantidad (en memoria, se vuelca al final)
    private array $stockTocado = []; // set de "producto_id:bodega_id" tocados
    private int $contadorAsiento = 0;

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Este comando es SOLO para entornos de desarrollo/pruebas.');
            $this->error('No se puede ejecutar con APP_ENV=production.');
            return self::FAILURE;
        }

        $this->hoy = now();
        $this->inicio = Carbon::create((int) $this->option('anio-inicio'), 1, 1);

        $this->info('=== Seeder de Volumen Altamira ===');
        $this->info("Rango de fechas: {$this->inicio->toDateString()} -> {$this->hoy->toDateString()}");

        $this->contadorAsiento = (int) DB::table('asientos_contables')->max('id');
        $this->cargarCuentas();
        $this->cargarBodegas();
        $this->cargarBancosCajas();
        $this->generarEjercicios();

        $idsClientes = $this->generarClientes((int) $this->option('clientes'));
        $idsProveedores = $this->generarProveedores((int) $this->option('proveedores'));
        $idsProductos = $this->generarProductos((int) $this->option('productos'));
        $idsColaboradores = $this->generarColaboradores((int) $this->option('colaboradores'));

        $this->generarCompras((int) $this->option('compras'), $idsProveedores, $idsProductos);
        $this->generarFacturas((int) $this->option('facturas'), $idsClientes, $idsProductos);
        $this->generarTaller((int) $this->option('taller'), $idsClientes, $idsProductos);
        $this->generarMovimientosBancarios((int) $this->option('movimientos'), $idsClientes, $idsProveedores);
        $this->generarNominas($idsColaboradores);
        $this->generarPrestamos((int) $this->option('prestamos'), $idsColaboradores);

        $this->volcarStock();
        $this->cerrarEjerciciosAntiguos();

        $this->imprimirResumen();

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────
    //  Preparación
    // ─────────────────────────────────────────────────────────────

    private function cargarCuentas(): void
    {
        $codigos = [
            'caja' => '1.1.1.01', 'caja_chica' => '1.1.1.02', 'bancos' => '1.1.1.03',
            'vouchers' => '1.1.1.05', 'clientes' => '1.1.3.01', 'anticipos_empleados' => '1.1.3.04',
            'inventario' => '1.1.4.01', 'iva_compras' => '1.1.5.01', 'ret_iva_cobrada' => '1.1.5.02',
            'ret_ir_cobrada' => '1.1.5.03', 'proveedores' => '2.1.1.01', 'ret_ir_pagar' => '2.1.3.01',
            'ret_iva_pagar' => '2.1.3.02', 'iva_ventas' => '2.1.3.04', 'anticipos_clientes' => '2.1.6.01',
            'nomina_pagar' => '2.1.4.01', 'iess_patronal' => '2.1.4.02', 'iess_personal' => '2.1.4.03',
            'ventas' => '4.1.1.01', 'costo_ventas' => '5.1.1.01', 'sueldos' => '5.2.1.01',
            'aporte_patronal' => '5.2.1.03', 'comisiones_bancarias' => '5.3.1.02',
        ];

        foreach ($codigos as $clave => $codigo) {
            $id = DB::table('plan_cuentas')->where('codigo', $codigo)->value('id');
            if (!$id) {
                $this->error("Cuenta {$codigo} ({$clave}) no encontrada en plan_cuentas — abortando.");
                exit(1);
            }
            $this->cta[$clave] = $id;
        }
        $this->info('✓ ' . count($this->cta) . ' cuentas contables resueltas.');
    }

    private function cargarBodegas(): void
    {
        $this->bodegas['general'] = DB::table('bodegas')->where('empresa_id', $this->empresaId)->where('tipo', 'general')->value('id');
        $this->bodegas['taller'] = DB::table('bodegas')->where('empresa_id', $this->empresaId)->where('tipo', 'taller')->value('id');
    }

    private function cargarBancosCajas(): void
    {
        $this->bancosCajas = DB::table('bancos_cajas')->where('empresa_id', $this->empresaId)->pluck('tipo', 'id')->toArray();
    }

    private function generarEjercicios(): void
    {
        $cursor = $this->inicio->copy();
        $filas = [];
        while ($cursor->lessThanOrEqualTo($this->hoy)) {
            $anio = $cursor->year;
            $mes = $cursor->month;
            $existe = DB::table('ejercicios_contables')
                ->where('empresa_id', $this->empresaId)->where('anio', $anio)->where('mes', $mes)->exists();
            if (!$existe) {
                $filas[] = [
                    'empresa_id' => $this->empresaId, 'anio' => $anio, 'mes' => $mes,
                    'descripcion' => $cursor->translatedFormat('F') . " {$anio}",
                    'fecha_apertura' => $cursor->copy()->startOfMonth()->toDateString(),
                    'estado' => 'abierto', 'created_at' => now(),
                ];
            }
            $cursor->addMonth();
        }
        if (!empty($filas)) {
            DB::table('ejercicios_contables')->insert($filas);
        }

        foreach (DB::table('ejercicios_contables')->where('empresa_id', $this->empresaId)->get() as $e) {
            $this->ejercicios["{$e->anio}-{$e->mes}"] = $e->id;
        }
        $this->info('✓ ' . count($this->ejercicios) . ' ejercicios contables disponibles (' . $this->inicio->year . '-' . $this->hoy->year . ').');
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers de generación de datos
    // ─────────────────────────────────────────────────────────────

    private function cedula(): string
    {
        $provincia = str_pad((string) random_int(1, 24), 2, '0', STR_PAD_LEFT);
        $tercero = random_int(0, 5);
        $digitos = [(int) $provincia[0], (int) $provincia[1], $tercero];
        for ($i = 0; $i < 6; $i++) {
            $digitos[] = random_int(0, 9);
        }
        $coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];
        $suma = 0;
        foreach ($digitos as $i => $d) {
            $v = $d * $coeficientes[$i];
            if ($v > 9) {
                $v -= 9;
            }
            $suma += $v;
        }
        $verificador = (10 - ($suma % 10)) % 10;
        $digitos[] = $verificador;
        return implode('', $digitos);
    }

    private function rucEmpresa(): string
    {
        $provincia = str_pad((string) random_int(1, 24), 2, '0', STR_PAD_LEFT);
        $medio = (string) random_int(100000000, 999999999);
        return $provincia . '9' . substr($medio, 0, 8) . '001';
    }

    private const NOMBRES_M = ['Carlos','Juan','Luis','Andrés','Diego','Miguel','José','Pedro','Fernando','Roberto','Jorge','Marco','Iván','Xavier','Santiago','Alexis','Bryan','Kevin','Cristian','Danilo'];
    private const NOMBRES_F = ['María','Ana','Gabriela','Patricia','Verónica','Andrea','Silvia','Lucía','Fernanda','Karen','Johanna','Priscila','Adriana','Mónica','Paola','Nicole','Estefanía','Diana','Carla','Jessica'];
    private const APELLIDOS = ['Maldonado','Vásquez','Paredes','Moreno','Villa','Cárdenas','Ruiz','Herrera','Toapanta','Chiluisa','Ortiz','Salazar','Andrade','Espinoza','Vega','Castillo','Mora','Guerrero','Jácome','Freire','Naranjo','Solís','Chávez','Rivas','Cedeño','Zambrano','Barros','Quinde','Aguilar','León'];
    private const EMPRESAS_SUFIJO = ['Cía. Ltda.','S.A.','Distribuidora','Import Export','Comercial'];
    private const RUBROS_AUDIO = ['Sound Systems','Audio Pro','Iluminación','DJ Equipment','Producciones','Eventos','Sonido y Luces','Backline'];
    private const CIUDADES = ['Quito','Guayaquil','Cuenca','Ambato','Latacunga','Riobamba','Manta','Machala','Loja','Ibarra','Santo Domingo','Portoviejo'];

    private function nombrePersona(): array
    {
        $sexo = random_int(0, 1) === 0 ? 'M' : 'F';
        $nombre = $sexo === 'M' ? self::NOMBRES_M[array_rand(self::NOMBRES_M)] : self::NOMBRES_F[array_rand(self::NOMBRES_F)];
        $apellido1 = self::APELLIDOS[array_rand(self::APELLIDOS)];
        $apellido2 = self::APELLIDOS[array_rand(self::APELLIDOS)];
        return [$nombre, "{$apellido1} {$apellido2}", $sexo];
    }

    private function nombreEmpresa(): string
    {
        $rubro = self::RUBROS_AUDIO[array_rand(self::RUBROS_AUDIO)];
        $apellido = self::APELLIDOS[array_rand(self::APELLIDOS)];
        $sufijo = self::EMPRESAS_SUFIJO[array_rand(self::EMPRESAS_SUFIJO)];
        return "{$rubro} {$apellido} {$sufijo}";
    }

    /** Fecha aleatoria uniforme entre inicio y hoy. */
    private function fechaAleatoria(?Carbon $desde = null, ?Carbon $hasta = null): Carbon
    {
        $desde = $desde ?? $this->inicio;
        $hasta = $hasta ?? $this->hoy;
        $diffDias = max(1, $desde->diffInDays($hasta));
        return $desde->copy()->addDays(random_int(0, (int) $diffDias));
    }

    private function chunkInsert(string $tabla, array $filas, int $tamano = 500): void
    {
        foreach (array_chunk($filas, $tamano) as $lote) {
            DB::table($tabla)->insert($lote);
        }
    }

    private function ejercicioIdPara(Carbon $fecha): ?int
    {
        return $this->ejercicios["{$fecha->year}-{$fecha->month}"] ?? null;
    }

    /** Inserta un asiento contable balanceado y devuelve su id. $lineas: [[cuenta_id, debe, haber, desc], ...] */
    private function crearAsiento(Carbon $fecha, string $concepto, string $docTipo, ?int $docId, array $lineas): int
    {
        $totalDebe = array_sum(array_column($lineas, 1));
        $totalHaber = array_sum(array_column($lineas, 2));

        $asientoId = DB::table('asientos_contables')->insertGetId([
            'empresa_id' => $this->empresaId,
            'ejercicio_id' => $this->ejercicioIdPara($fecha),
            'numero' => 'V' . str_pad((string) (++$this->contadorAsiento), 9, '0', STR_PAD_LEFT),
            'fecha' => $fecha->toDateString(),
            'concepto' => $concepto,
            'documento_tipo' => $docTipo,
            'documento_id' => $docId,
            'total_debe' => round($totalDebe, 4),
            'total_haber' => round($totalHaber, 4),
            'es_automatico' => true,
            'estado' => 1,
            'created_at' => $fecha,
        ]);

        $detalles = [];
        foreach ($lineas as [$cuentaId, $debe, $haber, $desc]) {
            $detalles[] = [
                'asiento_id' => $asientoId,
                'cuenta_id' => $cuentaId,
                'descripcion' => $desc,
                'debe' => round($debe, 4),
                'haber' => round($haber, 4),
            ];
        }
        DB::table('asiento_detalles')->insert($detalles);

        return $asientoId;
    }

    /** Aplica el delta al stock en memoria y devuelve [stock_anterior, stock_nuevo]. */
    private function tocarStock(int $productoId, int $bodegaId, float $delta, float $costo = 0): array
    {
        $key = "{$productoId}:{$bodegaId}";
        if (!isset($this->stock[$key])) {
            $existente = DB::table('inventario_saldos')->where('producto_id', $productoId)->where('bodega_id', $bodegaId)->first();
            $this->stock[$key] = [
                'producto_id' => $productoId, 'bodega_id' => $bodegaId,
                'cantidad' => $existente->stock_actual ?? 0,
                'costo' => $existente->costo_promedio ?? $costo,
            ];
        }
        $anterior = $this->stock[$key]['cantidad'];
        $this->stock[$key]['cantidad'] += $delta;
        if ($delta > 0 && $costo > 0) {
            $this->stock[$key]['costo'] = $costo;
        }
        $this->stockTocado[$key] = true;
        return [$anterior, $this->stock[$key]['cantidad']];
    }

    private function stockDisponible(int $productoId, int $bodegaId): float
    {
        $key = "{$productoId}:{$bodegaId}";
        if (!isset($this->stock[$key])) {
            $this->tocarStock($productoId, $bodegaId, 0);
        }
        return $this->stock[$key]['cantidad'];
    }

    private function volcarStock(): void
    {
        $this->info('Volcando saldos finales de inventario...');
        $filas = [];
        foreach (array_keys($this->stockTocado) as $key) {
            $s = $this->stock[$key];
            $filas[] = $s;
        }
        foreach (array_chunk($filas, 500) as $lote) {
            foreach ($lote as $s) {
                DB::table('inventario_saldos')->updateOrInsert(
                    ['producto_id' => $s['producto_id'], 'bodega_id' => $s['bodega_id']],
                    ['stock_actual' => max(0, round($s['cantidad'], 4)), 'costo_promedio' => round($s['costo'], 4), 'updated_at' => now()]
                );
            }
        }
        $this->info('✓ ' . count($filas) . ' saldos de inventario actualizados.');
    }

    // ─────────────────────────────────────────────────────────────
    //  1. Clientes / Proveedores / Productos / Colaboradores
    // ─────────────────────────────────────────────────────────────

    private function generarClientes(int $cantidad): array
    {
        $this->info("Generando {$cantidad} clientes...");
        $filas = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $esEmpresa = random_int(1, 100) <= 30;
            [$nombre, $apellido] = $this->nombrePersona();
            $razonSocial = $esEmpresa ? $this->nombreEmpresa() : "{$apellido} {$nombre}";
            $tieneCredito = random_int(1, 100) <= 40;
            $filas[] = [
                'empresa_id' => $this->empresaId,
                'tipo_identificacion' => $esEmpresa ? '04' : '05',
                'identificacion' => $esEmpresa ? $this->rucEmpresa() : $this->cedula(),
                'razon_social' => $razonSocial,
                'email' => strtolower(str_replace(' ', '.', $razonSocial)) . $i . '@example.com',
                'telefono' => '09' . random_int(10000000, 99999999),
                'direccion' => 'Av. ' . self::APELLIDOS[array_rand(self::APELLIDOS)] . ' N' . random_int(10, 99) . '-' . random_int(10, 99),
                'ciudad' => self::CIUDADES[array_rand(self::CIUDADES)],
                'provincia' => self::CIUDADES[array_rand(self::CIUDADES)],
                'pais' => 'ECUADOR',
                'tiene_credito' => $tieneCredito,
                'dias_credito' => $tieneCredito ? [15, 30, 30, 30, 45, 60][array_rand([15, 30, 30, 30, 45, 60])] : 0,
                'cupo_maximo' => $tieneCredito ? random_int(500, 20000) : 0,
                'es_cliente_nuevo' => false,
                'estado' => true,
                'created_at' => $this->fechaAleatoria(),
                'updated_at' => now(),
            ];
        }
        $this->chunkInsert('clientes', $filas);
        $ids = DB::table('clientes')->where('empresa_id', $this->empresaId)->pluck('id')->toArray();
        $this->info('✓ Clientes totales: ' . count($ids));
        return $ids;
    }

    private function generarProveedores(int $cantidad): array
    {
        $this->info("Generando {$cantidad} proveedores...");
        $filas = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $internacional = random_int(1, 100) <= 25;
            $tieneCredito = random_int(1, 100) <= 60;
            $filas[] = [
                'empresa_id' => $this->empresaId,
                'tipo' => $internacional ? 'internacional' : 'nacional',
                'tipo_identificacion' => $internacional ? '06' : '04',
                'identificacion' => $internacional ? 'EXT' . random_int(100000, 999999) : $this->rucEmpresa(),
                'razon_social' => $this->nombreEmpresa(),
                'email' => 'contacto' . $i . '@proveedor.com',
                'telefono' => '09' . random_int(10000000, 99999999),
                'direccion' => 'Zona Industrial ' . random_int(1, 20),
                'ciudad' => $internacional ? ['Miami', 'Shenzhen', 'Bogotá', 'Lima'][array_rand(['Miami', 'Shenzhen', 'Bogotá', 'Lima'])] : self::CIUDADES[array_rand(self::CIUDADES)],
                'pais' => $internacional ? ['USA', 'CHINA', 'COLOMBIA', 'PERU'][array_rand(['USA', 'CHINA', 'COLOMBIA', 'PERU'])] : 'ECUADOR',
                'divisa' => 'USD',
                'tiene_credito' => $tieneCredito,
                'dias_credito' => $tieneCredito ? [15, 30, 30, 45, 60, 90][array_rand([15, 30, 30, 45, 60, 90])] : 0,
                'estado' => true,
                'created_at' => $this->fechaAleatoria(),
                'updated_at' => now(),
            ];
        }
        $this->chunkInsert('proveedores', $filas);
        $ids = DB::table('proveedores')->where('empresa_id', $this->empresaId)->pluck('id')->toArray();
        $this->info('✓ Proveedores totales: ' . count($ids));
        return $ids;
    }

    private const CATEGORIAS_NOMBRE = ['Parlantes','Consolas de Sonido','Microfonía','Cableado','Iluminación LED','Cabezas Móviles','Amplificadores','DJ Controllers','Repuestos','Accesorios','Trípodes y Soportes','Efectos de Iluminación'];

    private function generarProductos(int $cantidad): array
    {
        $this->info("Generando {$cantidad} productos...");

        $marcaIds = DB::table('marcas')->where('empresa_id', $this->empresaId)->pluck('id')->toArray();
        if (empty($marcaIds)) {
            $marcaIds = [DB::table('marcas')->insertGetId(['empresa_id' => $this->empresaId, 'nombre' => 'Genérico Volumen', 'estado' => true, 'created_at' => now(), 'updated_at' => now()])];
        }

        $categoriaIds = [];
        foreach (self::CATEGORIAS_NOMBRE as $nombreCat) {
            $existente = DB::table('categorias_producto')->where('nombre', $nombreCat)->value('id');
            $categoriaIds[] = $existente ?: DB::table('categorias_producto')->insertGetId(['nombre' => $nombreCat, 'estado' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        $siguienteCodigo = (int) (DB::table('productos')->where('empresa_id', $this->empresaId)->max(DB::raw("NULLIF(regexp_replace(codigo, '\\D', '', 'g'), '')::int")) ?? 10000) + 1;

        $filas = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $categoria = self::CATEGORIAS_NOMBRE[array_rand(self::CATEGORIAS_NOMBRE)];
            $costo = round(mt_rand(500, 80000) / 100, 2);
            $pvp = round($costo * (1 + mt_rand(30, 90) / 100), 2);
            $requiereSerie = in_array($categoria, ['DJ Controllers', 'Consolas de Sonido']) && random_int(1, 100) <= 50;
            $codigo = 'PRD' . str_pad((string) ($siguienteCodigo + $i), 6, '0', STR_PAD_LEFT);
            $filas[] = [
                'empresa_id' => $this->empresaId,
                'marca_id' => $marcaIds[array_rand($marcaIds)],
                'categoria_id' => $categoriaIds[array_rand($categoriaIds)],
                'codigo' => $codigo,
                'nombre' => $categoria . ' ' . strtoupper(substr(md5((string) mt_rand()), 0, 4)) . '-' . random_int(100, 999),
                'descripcion' => "Producto de {$categoria} para audio profesional.",
                'tipo' => 'producto',
                'unidad' => 'unidad',
                'requiere_serie' => $requiereSerie,
                'pvp' => $pvp,
                'pvd' => round($pvp * 0.85, 2),
                'costo' => $costo,
                'descuento_maximo' => [5, 10, 15][array_rand([5, 10, 15])],
                'porcentaje_iva' => 15,
                'tiene_ice' => false,
                'porcentaje_ice' => 0,
                'stock_minimo' => random_int(3, 15),
                'stock_maximo' => random_int(50, 200),
                'estado' => true,
                'created_at' => $this->fechaAleatoria(),
                'updated_at' => now(),
            ];
        }
        $this->chunkInsert('productos', $filas);
        $ids = DB::table('productos')->where('empresa_id', $this->empresaId)->pluck('id')->toArray();
        $this->info('✓ Productos totales: ' . count($ids));
        return $ids;
    }

    private const CARGOS = ['Vendedor', 'Bodeguero', 'Técnico', 'Contador', 'Administrador', 'Asistente Administrativo'];

    private function generarColaboradores(int $cantidad): array
    {
        $this->info("Generando {$cantidad} colaboradores...");
        $puestoId = DB::table('puestos_trabajo')->where('empresa_id', $this->empresaId)->value('id');
        if (!$puestoId) {
            $puestoId = DB::table('puestos_trabajo')->insertGetId(['empresa_id' => $this->empresaId, 'nombre' => 'General', 'estado' => true]);
        }
        $horarioId = DB::table('horarios')->value('id');

        $filas = [];
        for ($i = 0; $i < $cantidad; $i++) {
            [$nombre, $apellido, $sexo] = $this->nombrePersona();
            $fechaIngreso = $this->fechaAleatoria($this->inicio, $this->hoy->copy()->subMonths(1));
            $activo = random_int(1, 100) <= 90;
            $filas[] = [
                'empresa_id' => $this->empresaId,
                'puesto_id' => $puestoId,
                'horario_id' => $horarioId,
                'cedula_ruc' => $this->cedula(),
                'apellidos' => $apellido,
                'nombres' => $nombre,
                'email' => strtolower($nombre . '.' . str_replace(' ', '', $apellido)) . $i . '@altamira.com',
                'telefono' => '09' . random_int(10000000, 99999999),
                'direccion' => 'Sector ' . self::CIUDADES[array_rand(self::CIUDADES)],
                'fecha_nacimiento' => Carbon::create(random_int(1970, 2002), random_int(1, 12), random_int(1, 28))->toDateString(),
                'sexo' => $sexo,
                'estado_civil' => ['soltero', 'casado', 'union_libre'][array_rand(['soltero', 'casado', 'union_libre'])],
                'fecha_ingreso' => $fechaIngreso->toDateString(),
                'fecha_salida' => $activo ? null : $this->fechaAleatoria($fechaIngreso, $this->hoy)->toDateString(),
                'tipo_contrato' => 'indefinido',
                'cargo' => self::CARGOS[array_rand(self::CARGOS)],
                'departamento' => 'Operaciones',
                'comision_porcentaje' => 0,
                'sueldo_base' => [460, 500, 600, 700, 850, 1200][array_rand([460, 500, 600, 700, 850, 1200])],
                'decimo_tercero' => 'mensualiza',
                'decimo_cuarto' => 'mensualiza',
                'fondos_reserva' => 'acumula',
                'banco' => ['Pichincha', 'Guayaquil', 'Produbanco'][array_rand(['Pichincha', 'Guayaquil', 'Produbanco'])],
                'tipo_cuenta' => 'ahorros',
                'numero_cuenta' => (string) random_int(1000000000, 9999999999),
                'estado' => $activo,
                'created_at' => $fechaIngreso,
                'updated_at' => now(),
            ];
        }
        $this->chunkInsert('colaboradores', $filas);
        $ids = DB::table('colaboradores')->where('empresa_id', $this->empresaId)->pluck('id')->toArray();
        $this->info('✓ Colaboradores totales: ' . count($ids));
        return $ids;
    }

    // ─────────────────────────────────────────────────────────────
    //  2. Compras
    // ─────────────────────────────────────────────────────────────

    private function generarCompras(int $cantidad, array $proveedorIds, array $productoIds): void
    {
        $this->info("Generando {$cantidad} compras...");
        $bar = $this->output->createProgressBar($cantidad);
        $secuencial = (int) DB::table('compras')->where('empresa_id', $this->empresaId)->count() + 1;

        for ($lote = 0; $lote < ceil($cantidad / 200); $lote++) {
            DB::transaction(function () use ($proveedorIds, $productoIds, &$secuencial, $lote, $cantidad, $bar) {
                $enEsteLote = min(200, $cantidad - $lote * 200);
                for ($i = 0; $i < $enEsteLote; $i++) {
                    $fecha = $this->fechaAleatoria();
                    $proveedorId = $proveedorIds[array_rand($proveedorIds)];
                    $nLineas = random_int(1, 4);
                    $lineasProductos = [];
                    $subtotal = 0;
                    for ($l = 0; $l < $nLineas; $l++) {
                        $productoId = $productoIds[array_rand($productoIds)];
                        $prod = DB::table('productos')->where('id', $productoId)->first(['costo']);
                        $cantidadProd = random_int(2, 30);
                        $precio = (float) $prod->costo;
                        $subtotalLinea = round($precio * $cantidadProd, 2);
                        $lineasProductos[] = compact('productoId', 'cantidadProd', 'precio', 'subtotalLinea');
                        $subtotal += $subtotalLinea;
                    }
                    $ivaTotal = round($subtotal * 0.15, 2);
                    $total = $subtotal + $ivaTotal;
                    $tieneCredito = random_int(1, 100) <= 55;
                    $diasCredito = $tieneCredito ? [15, 30, 30, 45][array_rand([15, 30, 30, 45])] : 0;

                    $compraId = DB::table('compras')->insertGetId([
                        'empresa_id' => $this->empresaId,
                        'centro_costo_id' => $this->centroCostoMatriz,
                        'proveedor_id' => $proveedorId,
                        'bodega_id' => $this->bodegas['general'],
                        'tipo_documento' => 'FAC',
                        'num_documento' => '001-001-' . str_pad((string) $secuencial, 9, '0', STR_PAD_LEFT),
                        'fecha_emision' => $fecha->toDateString(),
                        'fecha_registro' => $fecha->toDateString(),
                        'fecha_vencimiento' => $diasCredito ? $fecha->copy()->addDays($diasCredito)->toDateString() : $fecha->toDateString(),
                        'dias_credito' => $diasCredito,
                        'subtotal_0' => 0,
                        'subtotal_iva' => $subtotal,
                        'total_iva' => $ivaTotal,
                        'total' => $total,
                        'estado' => 'activa',
                        'created_at' => $fecha,
                        'updated_at' => $fecha,
                    ]);
                    $secuencial++;

                    $detalles = [];
                    foreach ($lineasProductos as $lp) {
                        $detalles[] = [
                            'compra_id' => $compraId,
                            'producto_id' => $lp['productoId'],
                            'descripcion' => 'Compra de mercadería',
                            'cantidad' => $lp['cantidadProd'],
                            'precio_unitario' => $lp['precio'],
                            'subtotal' => $lp['subtotalLinea'],
                            'porcentaje_iva' => 15,
                            'valor_iva' => round($lp['subtotalLinea'] * 0.15, 2),
                            'total' => round($lp['subtotalLinea'] * 1.15, 2),
                        ];
                        [$stockAntes, $stockDespues] = $this->tocarStock($lp['productoId'], $this->bodegas['general'], $lp['cantidadProd'], $lp['precio']);

                        DB::table('inventario_movimientos')->insert([
                            'producto_id' => $lp['productoId'], 'bodega_id' => $this->bodegas['general'],
                            'tipo' => 'entrada', 'doc_tipo' => 'COMPRA', 'doc_id' => $compraId,
                            'cantidad' => $lp['cantidadProd'], 'costo_unitario' => $lp['precio'],
                            'costo_total' => $lp['subtotalLinea'], 'usuario_id' => 1, 'stock_anterior' => $stockAntes,
                            'stock_nuevo' => $stockDespues, 'empresa_id' => $this->empresaId,
                            'created_at' => $fecha,
                        ]);
                    }
                    DB::table('compra_detalles')->insert($detalles);

                    $asientoId = $this->crearAsiento($fecha, 'Compra a proveedor', 'COMPRA', $compraId, [
                        [$this->cta['inventario'], $subtotal, 0, 'Ingreso a inventario'],
                        [$this->cta['iva_compras'], $ivaTotal, 0, 'IVA en compras'],
                        [$this->cta['proveedores'], 0, $total, 'Obligación con proveedor'],
                    ]);
                    DB::table('compras')->where('id', $compraId)->update(['asiento_id' => $asientoId]);

                    if ($tieneCredito) {
                        $vencida = $fecha->copy()->addDays($diasCredito)->lessThan($this->hoy);
                        $pagada = $vencida && random_int(1, 100) <= 65;
                        DB::table('cuentas_pagar')->insert([
                            'empresa_id' => $this->empresaId, 'proveedor_id' => $proveedorId, 'compra_id' => $compraId,
                            'monto' => $total, 'saldo' => $pagada ? 0 : $total,
                            'fecha_emision' => $fecha->toDateString(),
                            'fecha_vencimiento' => $fecha->copy()->addDays($diasCredito)->toDateString(),
                            'aprobada' => $pagada, 'estado' => $pagada ? 'pagada' : 'pendiente',
                            'created_at' => $fecha, 'updated_at' => $fecha,
                        ]);
                    }
                    $bar->advance();
                }
            });
        }
        $bar->finish();
        $this->newLine();
        $this->info('✓ Compras generadas: ' . $cantidad);
    }

    // ─────────────────────────────────────────────────────────────
    //  3. Facturas
    // ─────────────────────────────────────────────────────────────

    private function generarFacturas(int $cantidad, array $clienteIds, array $productoIds): void
    {
        $this->info("Generando {$cantidad} facturas...");
        $bar = $this->output->createProgressBar($cantidad);
        $secuencial = (int) DB::table('facturas')->where('empresa_id', $this->empresaId)->count() + 1;

        $clientesInfo = DB::table('clientes')->whereIn('id', $clienteIds)
            ->get(['id', 'tipo_identificacion', 'identificacion', 'razon_social', 'tiene_credito', 'dias_credito'])
            ->keyBy('id');

        for ($lote = 0; $lote < ceil($cantidad / 200); $lote++) {
            DB::transaction(function () use ($clienteIds, $productoIds, $clientesInfo, &$secuencial, $lote, $cantidad, $bar) {
                $enEsteLote = min(200, $cantidad - $lote * 200);
                for ($i = 0; $i < $enEsteLote; $i++) {
                    $fecha = $this->fechaAleatoria();
                    $cliente = $clientesInfo[$clienteIds[array_rand($clienteIds)]];
                    $roll = random_int(1, 100);
                    $estadoFactura = $roll <= 5 ? 'anulada' : 'activa';

                    $nLineas = random_int(1, 5);
                    $lineas = [];
                    $subtotal15 = 0;
                    for ($l = 0; $l < $nLineas; $l++) {
                        $productoId = $productoIds[array_rand($productoIds)];
                        $disponible = $this->stockDisponible($productoId, $this->bodegas['general']);
                        if ($disponible < 1) {
                            continue;
                        }
                        $prod = DB::table('productos')->where('id', $productoId)->first(['pvp', 'costo']);
                        $cant = (float) min(random_int(1, 5), max(1, floor($disponible)));
                        $precio = (float) $prod->pvp;
                        $sub = round($precio * $cant, 2);
                        $lineas[] = ['productoId' => $productoId, 'cantidad' => $cant, 'precio' => $precio, 'subtotal' => $sub, 'costo' => (float) $prod->costo];
                        $subtotal15 += $sub;
                    }
                    if (empty($lineas)) {
                        $bar->advance();
                        continue;
                    }
                    $ivaTotal = round($subtotal15 * 0.15, 2);
                    $total = $subtotal15 + $ivaTotal;
                    $costoTotalLineas = array_sum(array_map(fn($l) => $l['costo'] * $l['cantidad'], $lineas));

                    $esCredito = $estadoFactura === 'activa' && $cliente->tiene_credito && random_int(1, 100) <= 45;
                    $formaPago = $esCredito ? 'credito' : (random_int(1, 100) <= 70 ? 'efectivo' : 'transferencia');

                    $facturaId = DB::table('facturas')->insertGetId([
                        'empresa_id' => $this->empresaId,
                        'centro_costo_id' => $this->centroCostoMatriz,
                        'cliente_id' => $cliente->id,
                        'establecimiento' => '001', 'punto_emision' => '001',
                        'secuencial' => str_pad((string) $secuencial, 9, '0', STR_PAD_LEFT),
                        'numero_completo' => '001-001-' . str_pad((string) $secuencial, 9, '0', STR_PAD_LEFT),
                        'fecha_emision' => $fecha->toDateString(),
                        'hora_emision' => $fecha->toTimeString(),
                        'estado_sri' => $estadoFactura === 'anulada' ? 'anulada' : 'autorizada',
                        'tipo_identificacion' => $cliente->tipo_identificacion,
                        'identificacion' => $cliente->identificacion,
                        'razon_social' => $cliente->razon_social,
                        'subtotal_0' => 0, 'subtotal_15' => $subtotal15, 'subtotal_exento' => 0,
                        'descuento_total' => 0, 'total_ice' => 0, 'total_iva' => $ivaTotal, 'total' => $total,
                        'tipo' => 1, 'estado' => $estadoFactura, 'email_enviado' => false,
                        'created_at' => $fecha, 'updated_at' => $fecha,
                    ]);
                    $secuencial++;

                    $detalles = [];
                    foreach ($lineas as $l) {
                        $detalles[] = [
                            'factura_id' => $facturaId, 'producto_id' => $l['productoId'],
                            'descripcion' => 'Venta de producto', 'cantidad' => $l['cantidad'],
                            'precio_unitario' => $l['precio'], 'subtotal' => $l['subtotal'],
                            'porcentaje_iva' => 15, 'valor_iva' => round($l['subtotal'] * 0.15, 2),
                            'total' => round($l['subtotal'] * 1.15, 2), 'costo_unitario' => $l['costo'],
                        ];
                    }
                    DB::table('factura_detalles')->insert($detalles);

                    if ($estadoFactura === 'anulada') {
                        $bar->advance();
                        continue; // sin asiento, sin egreso de stock — nunca llegó a autorizarse en firme
                    }

                    foreach ($lineas as $l) {
                        [$stockAntes, $stockDespues] = $this->tocarStock($l['productoId'], $this->bodegas['general'], -$l['cantidad']);
                        DB::table('inventario_movimientos')->insert([
                            'producto_id' => $l['productoId'], 'bodega_id' => $this->bodegas['general'],
                            'tipo' => 'salida', 'doc_tipo' => 'FAC', 'doc_id' => $facturaId,
                            'cantidad' => $l['cantidad'], 'costo_unitario' => $l['costo'],
                            'costo_total' => round($l['costo'] * $l['cantidad'], 2), 'usuario_id' => 1, 'stock_anterior' => $stockAntes,
                            'stock_nuevo' => $stockDespues, 'empresa_id' => $this->empresaId,
                            'created_at' => $fecha,
                        ]);
                    }

                    DB::table('factura_pagos')->insert([
                        'factura_id' => $facturaId, 'forma_pago' => $formaPago, 'valor' => $total,
                        'dias_credito' => $esCredito ? $cliente->dias_credito : 0,
                        'fecha_vencimiento' => $esCredito ? $fecha->copy()->addDays($cliente->dias_credito)->toDateString() : null,
                        'estado' => $esCredito ? 'pendiente' : 'pagado',
                    ]);

                    $lineasAsiento = [
                        [$esCredito ? $this->cta['clientes'] : $this->cta['caja'], $total, 0, $esCredito ? 'CxC cliente' : 'Cobro en efectivo'],
                        [$this->cta['ventas'], 0, $subtotal15, 'Venta de mercadería'],
                        [$this->cta['iva_ventas'], 0, $ivaTotal, 'IVA en ventas'],
                    ];
                    $asientoId = $this->crearAsiento($fecha, 'Venta factura ' . $facturaId, 'FAC', $facturaId, $lineasAsiento);

                    // Asiento simultáneo de costo de ventas
                    if ($costoTotalLineas > 0) {
                        $this->crearAsiento($fecha, 'Costo de ventas factura ' . $facturaId, 'FAC', $facturaId, [
                            [$this->cta['costo_ventas'], $costoTotalLineas, 0, 'Costo de ventas'],
                            [$this->cta['inventario'], 0, $costoTotalLineas, 'Salida de inventario'],
                        ]);
                    }

                    DB::table('facturas')->where('id', $facturaId)->update(['asiento_id' => $asientoId]);

                    if ($esCredito) {
                        $fechaVenc = $fecha->copy()->addDays($cliente->dias_credito);
                        $vencida = $fechaVenc->lessThan($this->hoy);
                        $cobrada = $vencida && random_int(1, 100) <= 70;
                        DB::table('cuentas_cobrar')->insert([
                            'empresa_id' => $this->empresaId, 'cliente_id' => $cliente->id, 'factura_id' => $facturaId,
                            'monto' => $total, 'saldo' => $cobrada ? 0 : $total,
                            'fecha_emision' => $fecha->toDateString(), 'fecha_vencimiento' => $fechaVenc->toDateString(),
                            'forma_cobro' => 'credito', 'estado' => $cobrada ? 'cobrada' : 'pendiente',
                            'created_at' => $fecha, 'updated_at' => $fecha,
                        ]);
                    }
                    $bar->advance();
                }
            });
        }
        $bar->finish();
        $this->newLine();
        $this->info('✓ Facturas generadas: ' . $cantidad);
    }

    // ─────────────────────────────────────────────────────────────
    //  4. Taller
    // ─────────────────────────────────────────────────────────────

    private function generarTaller(int $cantidad, array $clienteIds, array $productoIds): void
    {
        $this->info("Generando {$cantidad} órdenes de trabajo de Taller...");
        $bar = $this->output->createProgressBar($cantidad);

        $tipoEquipoId = DB::table('taller_tipos_equipo')->value('id');
        if (!$tipoEquipoId) {
            $tipoEquipoId = DB::table('taller_tipos_equipo')->insertGetId(['descripcion' => 'Equipo General', 'estado' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $secuencial = (int) DB::table('taller_ordenes_trabajo')->where('empresa_id', $this->empresaId)->count() + 1;
        $facturaSecuencial = (int) DB::table('facturas')->where('empresa_id', $this->empresaId)->count() + 1;

        for ($lote = 0; $lote < ceil($cantidad / 200); $lote++) {
            DB::transaction(function () use ($clienteIds, $productoIds, $tipoEquipoId, &$secuencial, &$facturaSecuencial, $lote, $cantidad, $bar) {
                $enEsteLote = min(200, $cantidad - $lote * 200);
                for ($i = 0; $i < $enEsteLote; $i++) {
                    $fecha = $this->fechaAleatoria();
                    $clienteId = $clienteIds[array_rand($clienteIds)];

                    $equipoId = DB::table('taller_equipos')->insertGetId([
                        'tipo_id' => $tipoEquipoId, 'marca' => ['Pioneer', 'JBL', 'QSC', 'Behringer', 'Chauvet'][array_rand(['Pioneer', 'JBL', 'QSC', 'Behringer', 'Chauvet'])],
                        'modelo' => 'MOD-' . random_int(100, 999), 'numero_serie' => 'SN' . random_int(100000, 999999), 'estado' => 1,
                    ]);

                    $roll = random_int(1, 100);
                    $estadoOt = match (true) {
                        $roll <= 40 => 'facturado',
                        $roll <= 55 => 'entregado',
                        $roll <= 70 => 'listo',
                        $roll <= 85 => 'en_proceso',
                        default => 'pendiente',
                    };

                    $ingresoId = DB::table('taller_ingresos')->insertGetId([
                        'empresa_id' => $this->empresaId, 'cliente_id' => $clienteId, 'equipo_id' => $equipoId,
                        'fecha' => $fecha->toDateString(), 'hora' => $fecha->toTimeString(),
                        'diagnostico_inicial' => 'Revisión general del equipo.', 'estado' => $estadoOt === 'pendiente' ? 0 : ($estadoOt === 'facturado' || $estadoOt === 'entregado' ? 4 : 2),
                        'created_at' => $fecha,
                    ]);

                    $costoManoObra = round(mt_rand(1500, 8000) / 100, 2);
                    $numero = 'OT-VOL-' . str_pad((string) $secuencial, 6, '0', STR_PAD_LEFT);
                    $secuencial++;

                    $ordenId = DB::table('taller_ordenes_trabajo')->insertGetId([
                        'empresa_id' => $this->empresaId, 'ingreso_id' => $ingresoId, 'numero' => $numero,
                        'fecha_inicio' => $fecha->toDateString(), 'hora_inicio' => $fecha->toTimeString(),
                        'tipo_orden' => 1, 'descripcion_trabajo' => 'Mantenimiento y reparación de equipo de audio/iluminación.',
                        'costo_mano_obra' => $costoManoObra, 'costo_repuestos' => 0, 'costo_total' => $costoManoObra,
                        'estado' => $estadoOt, 'created_at' => $fecha, 'updated_at' => $fecha,
                    ]);

                    if (in_array($estadoOt, ['facturado', 'entregado'])) {
                        $subtotal0 = $costoManoObra;
                        $total = $subtotal0;
                        $facturaId = DB::table('facturas')->insertGetId([
                            'empresa_id' => $this->empresaId, 'centro_costo_id' => $this->centroCostoTaller,
                            'cliente_id' => $clienteId, 'establecimiento' => '001', 'punto_emision' => '001',
                            'secuencial' => str_pad((string) $facturaSecuencial, 9, '0', STR_PAD_LEFT),
                            'numero_completo' => '001-001-' . str_pad((string) $facturaSecuencial, 9, '0', STR_PAD_LEFT),
                            'fecha_emision' => $fecha->toDateString(), 'hora_emision' => $fecha->toTimeString(),
                            'estado_sri' => 'autorizada', 'subtotal_0' => $subtotal0, 'subtotal_15' => 0,
                            'subtotal_exento' => 0, 'descuento_total' => 0, 'total_ice' => 0, 'total_iva' => 0,
                            'total' => $total, 'tipo' => 3, 'estado' => 'activa', 'email_enviado' => false,
                            'created_at' => $fecha, 'updated_at' => $fecha,
                        ]);
                        $facturaSecuencial++;

                        DB::table('factura_detalles')->insert([
                            'factura_id' => $facturaId, 'descripcion' => 'Mano de obra - Orden ' . $numero,
                            'cantidad' => 1, 'precio_unitario' => $costoManoObra, 'subtotal' => $subtotal0,
                            'porcentaje_iva' => 0, 'valor_iva' => 0, 'total' => $total,
                        ]);
                        DB::table('factura_pagos')->insert([
                            'factura_id' => $facturaId, 'forma_pago' => 'efectivo', 'valor' => $total, 'estado' => 'pagado',
                        ]);

                        $asientoId = $this->crearAsiento($fecha, 'Facturación Taller OT ' . $numero, 'TALLER_OT', $ordenId, [
                            [$this->cta['caja'], $total, 0, 'Cobro en efectivo'],
                            [$this->cta['ventas'], 0, $subtotal0, 'Servicio técnico Taller'],
                        ]);

                        DB::table('facturas')->where('id', $facturaId)->update(['asiento_id' => $asientoId]);
                        DB::table('taller_ordenes_trabajo')->where('id', $ordenId)->update([
                            'factura_id' => $facturaId, 'asiento_id' => $asientoId, 'fecha_fin_real' => $fecha->toDateString(),
                        ]);
                    }
                    $bar->advance();
                }
            });
        }
        $bar->finish();
        $this->newLine();
        $this->info('✓ Órdenes de trabajo generadas: ' . $cantidad);
    }

    // ─────────────────────────────────────────────────────────────
    //  5. Movimientos bancarios
    // ─────────────────────────────────────────────────────────────

    private function generarMovimientosBancarios(int $cantidad, array $clienteIds, array $proveedorIds): void
    {
        $this->info("Generando {$cantidad} movimientos bancarios...");
        $bar = $this->output->createProgressBar($cantidad);

        $bancoIds = array_keys($this->bancosCajas);
        $subTipos = ['transferencia', 'efectivo', 'cheque', 'deposito'];

        for ($lote = 0; $lote < ceil($cantidad / 500); $lote++) {
            $filas = [];
            $enEsteLote = min(500, $cantidad - $lote * 500);
            for ($i = 0; $i < $enEsteLote; $i++) {
                $fecha = $this->fechaAleatoria();
                $tipo = random_int(1, 100) <= 55 ? 'ingreso' : 'egreso';
                $monto = round(mt_rand(2000, 500000) / 100, 2);
                $cuentaContrapartida = $tipo === 'ingreso' ? $this->cta['ventas'] : $this->cta['comisiones_bancarias'];
                $filas[] = [
                    'empresa_id' => $this->empresaId,
                    'banco_caja_id' => $bancoIds[array_rand($bancoIds)],
                    'tipo' => $tipo, 'sub_tipo' => $subTipos[array_rand($subTipos)],
                    'fecha' => $fecha->toDateString(), 'monto' => $monto,
                    'persona_tipo' => $tipo === 'ingreso' ? 'cliente' : 'proveedor',
                    'persona_id' => $tipo === 'ingreso' ? $clienteIds[array_rand($clienteIds)] : $proveedorIds[array_rand($proveedorIds)],
                    'beneficiario' => $tipo === 'ingreso' ? 'Cliente varios' : 'Proveedor varios',
                    'descripcion' => 'Movimiento generado por volumen de prueba',
                    'cuenta_contrapartida_id' => $cuentaContrapartida,
                    'conciliado' => random_int(1, 100) <= 40,
                    'anulado' => false,
                    'created_at' => $fecha, 'updated_at' => $fecha,
                ];
            }
            DB::table('movimientos_bancarios')->insert($filas);
            $bar->advance($enEsteLote);
        }
        $bar->finish();
        $this->newLine();
        $this->info('✓ Movimientos bancarios generados: ' . $cantidad);
    }

    // ─────────────────────────────────────────────────────────────
    //  6. Nóminas
    // ─────────────────────────────────────────────────────────────

    private function generarNominas(array $colaboradorIds): void
    {
        $this->info('Generando nóminas mensuales del período...');
        $colaboradores = DB::table('colaboradores')->whereIn('id', $colaboradorIds)
            ->get(['id', 'sueldo_base', 'fecha_ingreso', 'fecha_salida'])->keyBy('id');

        $cursor = $this->inicio->copy()->startOfMonth();
        $totalNominas = 0;
        while ($cursor->lessThan($this->hoy->copy()->startOfMonth())) {
            $desde = $cursor->copy()->startOfMonth();
            $hasta = $cursor->copy()->endOfMonth();

            $activosEnPeriodo = $colaboradores->filter(function ($c) use ($desde, $hasta) {
                $ingreso = Carbon::parse($c->fecha_ingreso);
                $salida = $c->fecha_salida ? Carbon::parse($c->fecha_salida) : null;
                return $ingreso->lessThanOrEqualTo($hasta) && (!$salida || $salida->greaterThanOrEqualTo($desde));
            });

            if ($activosEnPeriodo->isNotEmpty()) {
                $nominaId = DB::table('nominas')->insertGetId([
                    'empresa_id' => $this->empresaId, 'periodo_tipo' => 'mensual',
                    'anio' => $desde->year, 'mes' => $desde->month, 'quincena' => null,
                    'fecha_emision' => $hasta->toDateString(), 'estado' => 'pagado',
                    'generado_por' => 1, 'procesado_por' => 1, 'pagado_por' => 1,
                    'created_at' => $hasta,
                ]);

                $totalIngresosNomina = 0;
                $totalEgresosNomina = 0;
                $detalles = [];
                foreach ($activosEnPeriodo as $col) {
                    $sueldo = (float) $col->sueldo_base;
                    $horasExtra50 = random_int(1, 100) <= 20 ? round(mt_rand(500, 3000) / 100, 2) : 0;
                    $aportePersonal = round($sueldo * 0.0945, 2);
                    $atrasos = random_int(1, 100) <= 15 ? round(mt_rand(100, 800) / 100, 2) : 0;
                    $totalIngresos = round($sueldo + $horasExtra50, 2);
                    $totalEgresos = round($aportePersonal + $atrasos, 2);
                    $neto = round($totalIngresos - $totalEgresos, 2);

                    $detalles[] = [
                        'nomina_id' => $nominaId, 'colaborador_id' => $col->id, 'sueldo_base' => $sueldo,
                        'horas_extras_50' => $horasExtra50, 'horas_extras_100' => 0, 'comisiones' => 0,
                        'otros_ingresos' => 0, 'total_ingresos' => $totalIngresos,
                        'aporte_personal_iess' => $aportePersonal, 'descuento_atrasos' => $atrasos,
                        'descuento_prestamos' => 0, 'descuento_anticipos' => 0, 'otros_egresos' => 0,
                        'total_egresos' => $totalEgresos, 'neto_pagar' => $neto, 'estado' => 'pagado',
                        'modificado_manualmente' => false, 'created_at' => $hasta,
                    ];
                    $totalIngresosNomina += $totalIngresos;
                    $totalEgresosNomina += $totalEgresos;
                }
                DB::table('nomina_detalles')->insert($detalles);

                $aportePatronalTotal = round($totalIngresosNomina * 0.1115, 2);
                $aportePersonalTotal = array_sum(array_column($detalles, 'aporte_personal_iess'));
                $atrasosTotal = array_sum(array_column($detalles, 'descuento_atrasos'));
                $netoTotal = array_sum(array_column($detalles, 'neto_pagar'));

                // Los atrasos reducen el gasto de sueldos (ese tiempo no fue laborado),
                // no se registran como una cuenta puente aparte — igual que NOM-05 real.
                $asientoId = $this->crearAsiento($hasta, 'Nómina ' . $desde->format('Y-m'), 'NOM', $nominaId, [
                    [$this->cta['sueldos'], round($totalIngresosNomina - $atrasosTotal, 2), 0, 'Sueldos del período'],
                    [$this->cta['aporte_patronal'], $aportePatronalTotal, 0, 'Aporte patronal IESS'],
                    [$this->cta['iess_personal'], 0, $aportePersonalTotal, 'Aporte personal IESS'],
                    [$this->cta['iess_patronal'], 0, $aportePatronalTotal, 'Aporte patronal IESS por pagar'],
                    [$this->cta['nomina_pagar'], 0, round($netoTotal, 2), 'Neto a pagar nómina'],
                ]);

                DB::table('nominas')->where('id', $nominaId)->update([
                    'asiento_id' => $asientoId,
                    'total_ingresos' => $totalIngresosNomina, 'total_egresos' => $totalEgresosNomina,
                    'total_neto' => $netoTotal,
                ]);
                $totalNominas++;
            }
            $cursor->addMonth();
        }
        $this->info("✓ Nóminas mensuales generadas: {$totalNominas}");
    }

    private function generarPrestamos(int $cantidad, array $colaboradorIds): void
    {
        $this->info("Generando {$cantidad} préstamos/anticipos...");
        $filas = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $monto = round(mt_rand(10000, 100000) / 100, 2);
            $pagado = random_int(1, 100) <= 50;
            $fecha = $this->fechaAleatoria();
            $filas[] = [
                'colaborador_id' => $colaboradorIds[array_rand($colaboradorIds)],
                'tipo' => random_int(1, 100) <= 50 ? 'prestamo' : 'anticipo',
                'monto_total' => $monto, 'saldo' => $pagado ? 0 : round($monto * (mt_rand(20, 90) / 100), 2),
                'cuota' => round($monto / random_int(3, 12), 2),
                'fecha' => $fecha->toDateString(), 'descripcion' => 'Préstamo/anticipo generado para prueba de volumen',
                'estado' => $pagado ? 'pagado' : 'activo', 'created_at' => $fecha,
            ];
        }
        $this->chunkInsert('prestamos_empleados', $filas);
        $this->info('✓ Préstamos/anticipos generados: ' . $cantidad);
    }

    // ─────────────────────────────────────────────────────────────
    //  Cierre y resumen
    // ─────────────────────────────────────────────────────────────

    private function cerrarEjerciciosAntiguos(): void
    {
        $this->info('Cerrando ejercicios contables antiguos (todos menos los últimos 2 meses)...');
        $limite = $this->hoy->copy()->subMonths(2)->startOfMonth();
        $actualizados = DB::table('ejercicios_contables')
            ->where('empresa_id', $this->empresaId)
            ->where('estado', 'abierto')
            ->where(function ($q) use ($limite) {
                $q->where('anio', '<', $limite->year)
                  ->orWhere(function ($q2) use ($limite) {
                      $q2->where('anio', $limite->year)->where('mes', '<', $limite->month);
                  });
            })
            ->update(['estado' => 'cerrado', 'fecha_cierre' => now()]);
        $this->info("✓ {$actualizados} ejercicios cerrados.");
    }

    private function imprimirResumen(): void
    {
        $this->newLine();
        $this->info('=== RESUMEN FINAL ===');
        $tablas = [
            'clientes', 'proveedores', 'productos', 'colaboradores', 'compras', 'compra_detalles',
            'facturas', 'factura_detalles', 'factura_pagos', 'taller_ordenes_trabajo', 'movimientos_bancarios',
            'nominas', 'nomina_detalles', 'prestamos_empleados', 'inventario_movimientos', 'asientos_contables',
            'asiento_detalles', 'cuentas_cobrar', 'cuentas_pagar',
        ];
        foreach ($tablas as $t) {
            $this->line(str_pad($t, 25) . ': ' . DB::table($t)->count());
        }
        $debe = DB::table('asiento_detalles')->sum('debe');
        $haber = DB::table('asiento_detalles')->sum('haber');
        $this->newLine();
        $this->info("Balance de Comprobación: DEBE={$debe} HABER={$haber} " . ($debe == $haber ? '✓ CUADRADO' : '✗ DESCUADRADO'));
    }
}
