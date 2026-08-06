<?php

namespace Database\Seeders;

use App\Models\FeriadoNacional;
use Illuminate\Database\Seeder;

class FeriadoNacionalSeeder extends Seeder
{
    // Feriados nacionales de Ecuador (fechas fijas + movibles ya calculadas para
    // el año en curso). No aplica las reglas de "traslado al lunes/viernes más
    // cercano" del Código de Trabajo — para el objetivo de este seeder (que el
    // Timbre Digital clasifique horas extra como "extraordinaria" en feriado)
    // basta con la fecha calendario real; un administrador puede agregar/ajustar
    // filas directamente en la tabla si un feriado se traslada oficialmente.
    private array $feriados = [
        ['fecha' => '2026-01-01', 'nombre' => 'Año Nuevo'],
        ['fecha' => '2026-02-16', 'nombre' => 'Carnaval'],
        ['fecha' => '2026-02-17', 'nombre' => 'Carnaval'],
        ['fecha' => '2026-04-03', 'nombre' => 'Viernes Santo'],
        ['fecha' => '2026-05-01', 'nombre' => 'Día del Trabajo'],
        ['fecha' => '2026-05-24', 'nombre' => 'Batalla de Pichincha'],
        ['fecha' => '2026-08-10', 'nombre' => 'Primer Grito de Independencia'],
        ['fecha' => '2026-10-09', 'nombre' => 'Independencia de Guayaquil'],
        ['fecha' => '2026-11-02', 'nombre' => 'Día de los Difuntos'],
        ['fecha' => '2026-11-03', 'nombre' => 'Independencia de Cuenca'],
        ['fecha' => '2026-12-25', 'nombre' => 'Navidad'],
    ];

    public function run(): void
    {
        foreach ($this->feriados as $feriado) {
            FeriadoNacional::firstOrCreate(
                ['fecha' => $feriado['fecha']],
                ['nombre' => $feriado['nombre']]
            );
        }
    }
}
