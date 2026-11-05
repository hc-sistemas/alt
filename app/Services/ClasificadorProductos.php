<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Clasifica productos en las categorías y subcategorías oficiales (02-02 §2.3): por el prefijo del código (lo
 * que ya usa el catálogo) y, dentro de cada categoría, por palabras clave del nombre para elegir la
 * subcategoría. Lo usan la sugerencia de reclasificación y la carga del inventario desde Excel.
 */
class ClasificadorProductos
{
    /** Prefijo del código => categoría. Es el criterio con el que ya está clasificado el catálogo existente. */
    public const POR_PREFIJO = [
        'SON' => 'Parlantes y sistemas de sonido',
        'DJS' => 'Artículos y Equipos de DJ',
        'IML' => 'Iluminación Profesional',
        'ILM' => 'Iluminación Profesional',
        'VID' => 'Pantallas LED y Video',
        'CBL' => 'Cables, Conectores y Estructuras',
        'CMP' => 'Computadoras, partes y repuestos',
        'REP' => 'Repuestos e Insumos de Taller',
        'PLD' => 'PISOS LED (descontinuada)',
        'ACC' => 'ACCESORIOS (por reclasificar)',
        'AYR' => 'ACCESORIOS (por reclasificar)',
    ];

    /** Palabras clave (en minúsculas, sin tildes) => [categoría, subcategoría|null]. El orden importa: primero lo más específico. */
    public const REGLAS = [
        // Sonido
        '/line ?array|linearray/'                              => ['Parlantes y sistemas de sonido', 'Sistemas Line Array'],
        '/subwoofer|sub-?woofer|\bsub\b|\bbajo\b|woofer/'      => ['Parlantes y sistemas de sonido', 'Subwoofers / Bajos'],
        '/pasivo|passive/'                                      => ['Parlantes y sistemas de sonido', 'Parlantes Pasivos'],
        '/activo|amplificad|powered|active|autoamplif/'         => ['Parlantes y sistemas de sonido', 'Parlantes Activos (Amplificados)'],
        '/plafon|techo|empotr|ambiental|instalacion/'           => ['Parlantes y sistemas de sonido', 'Parlantes de Instalación Comercial'],
        '/parlante|bocina|altavoz|speaker|monitor de estudio/'  => ['Parlantes y sistemas de sonido', null],
        // DJ
        '/tornamesa|turntable|giradiscos|\bcdj\b/'              => ['Artículos y Equipos de DJ', 'Tornamesas / Turntables'],
        '/mixer|mezclador|mezcladora/'                          => ['Artículos y Equipos de DJ', 'Mezcladoras / Mixers'],
        '/consola/'                                             => ['Artículos y Equipos de DJ', 'Consolas análogas y digitales'],
        '/controlador dj|dj controller|controller dj|\bddj\b/'  => ['Artículos y Equipos de DJ', 'Controladores de DJ'],
        // Iluminación
        '/dmx/'                                                 => ['Iluminación Profesional', 'Controladores DMX alámbricos e inalámbricos'],
        '/robotic|beam|spot|moving|cabeza movil/'               => ['Iluminación Profesional', 'Luces Robóticas, Beams, Spots'],
        '/\bpar\b|tacho|wash|strobe|barra led|led bar|reflector|luz /' => ['Iluminación Profesional', 'Tachos LED / Iluminación Estática'],
        // Video
        '/procesador|processor|novastar|scaler|sending|receiving|tarjeta receptora/' => ['Pantallas LED y Video', 'Procesadores y Equipos de Control de Video'],
        '/modulo led|gabinete|cabinet|pantalla led|panel led|pantalla/'              => ['Pantallas LED y Video', 'Módulos y Gabinetes LED'],
        // Resto
        '/cable|conector|xlr|plug|jack|speakon|canon|estructura|truss/'              => ['Cables, Conectores y Estructuras', null],
        '/computador|laptop|notebook|mouse|teclado|monitor pc|disco duro|memoria ram/' => ['Computadoras, partes y repuestos', null],
        '/camiseta|regalo|gorra|llavero/'                       => ['Regalos', null],
        '/repuesto|driver|diafragma|capacitor|resistencia|fusible|bocina de repuesto|membrana/' => ['Repuestos e Insumos de Taller', null],
    ];

    /** Sugerencia por palabras clave del nombre: [categoría, subcategoría|null]. */
    public static function porNombre(string $nombre): array
    {
        $texto = ' ' . self::normalizar($nombre) . ' ';
        foreach (self::REGLAS as $regex => [$cat, $sub]) {
            if (preg_match($regex, $texto)) {
                return [$cat, $sub];
            }
        }

        return [null, null];
    }

    /**
     * Id de la categoría (o subcategoría) para un producto nuevo y una advertencia si quedó pendiente de
     * subcategoría. Prefijo del código manda; el nombre elige la subcategoría dentro de esa categoría.
     *
     * @return array{0:int|null,1:string|null} [categoria_id, advertencia]
     */
    public static function categoriaPara(string $codigo, string $nombre): array
    {
        $prefijo = strtoupper(explode('.', $codigo)[0]);
        $catNombre = self::POR_PREFIJO[$prefijo] ?? null;
        if (!$catNombre) {
            $catNombre = 'ACCESORIOS (por reclasificar)';
        }

        $raiz = DB::table('categorias_producto')->where('nombre', $catNombre)->whereNull('categoria_padre_id')->first(['id']);
        if (!$raiz) {
            return [null, "categoría «{$catNombre}» no existe"];
        }

        $tieneSubs = DB::table('categorias_producto')->where('categoria_padre_id', $raiz->id)->exists();
        if (!$tieneSubs) {
            return [(int) $raiz->id, null];
        }

        [$catSug, $subSug] = self::porNombre($nombre);
        if ($catSug === $catNombre && $subSug) {
            $sub = DB::table('categorias_producto')->where('categoria_padre_id', $raiz->id)->where('nombre', $subSug)->first(['id']);
            if ($sub) {
                return [(int) $sub->id, null];
            }
        }

        return [(int) $raiz->id, 'falta elegir subcategoría de «' . $catNombre . '»'];
    }

    public static function normalizar(string $t): string
    {
        $t = mb_strtolower($t);

        return strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
    }
}
