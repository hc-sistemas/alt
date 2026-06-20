<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class SeedearTodo extends Command
{
    protected $signature   = 'altamira:seedear-todo
                              {--limpiar : Limpiar datos antes de seedear}';
    protected $description = 'Crea datos de prueba en todos los módulos';

    public function handle(): void
    {
        $this->info('🌱 Iniciando seeders de todos los módulos...');
        $this->newLine();

        $comandos = [
            'altamira:seedear-contabilidad',
            'altamira:seedear-compras',
            'altamira:seedear-bancos',
            'altamira:seedear-bancos-extra',
        ];

        foreach ($comandos as $cmd) {
            $this->info("▶ Ejecutando {$cmd}...");
            $this->call($cmd);
            $this->newLine();
        }

        $this->info('✅ Todos los módulos seeded correctamente.');
    }
}
