<?php

namespace Database\Seeders;

use App\Models\TipoAprobacion;
use Illuminate\Database\Seeder;

class TiposAprobacionSeeder extends Seeder
{
    public function run(): void
    {
        // Schema real: id, codigo, descripcion, perfiles_autorizados, requiere_codigo, activo
        $tipos = [
            ['codigo' => 'descuento_excedido', 'descripcion' => 'Descuento mayor al límite permitido', 'requiere_codigo' => true, 'activo' => true],
            ['codigo' => 'precio_bajo_costo', 'descripcion' => 'Precio menor al costo del producto', 'requiere_codigo' => true, 'activo' => true],
            ['codigo' => 'anulacion_factura', 'descripcion' => 'Anulación de factura emitida', 'requiere_codigo' => true, 'activo' => true],
            ['codigo' => 'anulacion_nota_credito', 'descripcion' => 'Anulación de nota de crédito', 'requiere_codigo' => true, 'activo' => true],
            ['codigo' => 'modificacion_precio_base', 'descripcion' => 'Cambio en precio base de producto', 'requiere_codigo' => true, 'activo' => true],
            ['codigo' => 'credito_excedido', 'descripcion' => 'Cliente supera límite de crédito', 'requiere_codigo' => false, 'activo' => true],
            ['codigo' => 'cierre_periodo', 'descripcion' => 'Cierre de período contable', 'requiere_codigo' => true, 'activo' => true],
            ['codigo' => 'devolucion_mercaderia', 'descripcion' => 'Devolución de productos', 'requiere_codigo' => false, 'activo' => true],
            ['codigo' => 'baja_activo', 'descripcion' => 'Dar de baja un activo fijo', 'requiere_codigo' => true, 'activo' => true],
            ['codigo' => 'transferencia_bodega', 'descripcion' => 'Transferencia de stock entre bodegas', 'requiere_codigo' => false, 'activo' => true],
            ['codigo' => 'ajuste_inventario', 'descripcion' => 'Ajuste manual de inventario', 'requiere_codigo' => true, 'activo' => true],
            ['codigo' => 'modificacion_asiento', 'descripcion' => 'Modificación de asiento contable aprobado', 'requiere_codigo' => true, 'activo' => true],
            ['codigo' => 'cheque_posfechado', 'descripcion' => 'Recepción de pago con cheque posfechado', 'requiere_codigo' => false, 'activo' => true],
            ['codigo' => 'extension_plazo', 'descripcion' => 'Extensión de plazo de pago a cliente', 'requiere_codigo' => false, 'activo' => true],
            ['codigo' => 'descuento_compra', 'descripcion' => 'Descuento adicional en orden de compra', 'requiere_codigo' => false, 'activo' => true],
            ['codigo' => 'egreso_caja_mayor', 'descripcion' => 'Egreso de caja mayor al límite autorizado', 'requiere_codigo' => true, 'activo' => true],
            ['codigo' => 'reactivacion_ot', 'descripcion' => 'Reactivación de orden de trabajo cerrada', 'requiere_codigo' => false, 'activo' => true],
        ];

        foreach ($tipos as $tipo) {
            TipoAprobacion::firstOrCreate(['codigo' => $tipo['codigo']], $tipo);
        }
    }
}
