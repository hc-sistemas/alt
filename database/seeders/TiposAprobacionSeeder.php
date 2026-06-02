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
            ['clave' => 'descuento_excedido', 'nombre' => 'Descuento Excedido', 'descripcion' => 'Descuento mayor al límite permitido', 'activo' => true],
            ['clave' => 'precio_bajo_costo', 'nombre' => 'Precio Bajo Costo', 'descripcion' => 'Precio menor al costo del producto', 'activo' => true],
            ['clave' => 'anulacion_factura', 'nombre' => 'Anulación Factura', 'descripcion' => 'Anulación de factura emitida', 'activo' => true],
            ['clave' => 'anulacion_nota_credito', 'nombre' => 'Anulación Nota Crédito', 'descripcion' => 'Anulación de nota de crédito', 'activo' => true],
            ['clave' => 'modificacion_precio_base', 'nombre' => 'Modificación Precio Base', 'descripcion' => 'Cambio en precio base de producto', 'activo' => true],
            ['clave' => 'credito_excedido', 'nombre' => 'Crédito Excedido', 'descripcion' => 'Cliente supera límite de crédito', 'activo' => true],
            ['clave' => 'cierre_periodo', 'nombre' => 'Cierre de Período', 'descripcion' => 'Cierre de período contable', 'activo' => true],
            ['clave' => 'devolucion_mercaderia', 'nombre' => 'Devolución Mercadería', 'descripcion' => 'Devolución de productos', 'activo' => true],
            ['clave' => 'baja_activo', 'nombre' => 'Baja de Activo', 'descripcion' => 'Dar de baja un activo fijo', 'activo' => true],
            ['clave' => 'transferencia_bodega', 'nombre' => 'Transferencia Bodega', 'descripcion' => 'Transferencia de stock entre bodegas', 'activo' => true],
            ['clave' => 'ajuste_inventario', 'nombre' => 'Ajuste Inventario', 'descripcion' => 'Ajuste manual de inventario', 'activo' => true],
            ['clave' => 'modificacion_asiento', 'nombre' => 'Modificación Asiento', 'descripcion' => 'Modificación de asiento contable aprobado', 'activo' => true],
            ['clave' => 'cheque_posfechado', 'nombre' => 'Cheque Posfechado', 'descripcion' => 'Recepción de pago con cheque posfechado', 'activo' => true],
            ['clave' => 'extension_plazo', 'nombre' => 'Extensión de Plazo', 'descripcion' => 'Extensión de plazo de pago a cliente', 'activo' => true],
            ['clave' => 'descuento_compra', 'nombre' => 'Descuento Compra', 'descripcion' => 'Descuento adicional en orden de compra', 'activo' => true],
            ['clave' => 'egreso_caja_mayor', 'nombre' => 'Egreso Caja Mayor', 'descripcion' => 'Egreso de caja mayor al límite autorizado', 'activo' => true],
            ['clave' => 'reactivacion_ot', 'nombre' => 'Reactivación OT', 'descripcion' => 'Reactivación de orden de trabajo cerrada', 'activo' => true],
        ];

        foreach ($tipos as $tipo) {
            TipoAprobacion::firstOrCreate(['clave' => $tipo['clave']], $tipo);
        }
    }
}

