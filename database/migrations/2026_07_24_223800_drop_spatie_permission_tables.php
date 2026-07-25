<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * spatie/laravel-permission se agregó al proyecto pero nunca se usó como
     * sistema de autorización real — VerificarPermiso.php siempre consultó
     * directamente las tablas perfiles/permisos/modulos. Confirmado antes de
     * este cambio: las 5 tablas de Spatie estaban vacías (0 filas) y un grep
     * completo de la API de Spatie (hasRole, hasAnyRole, assignRole,
     * hasPermissionTo, etc.) en app/ y resources/views/ no tenía ningún uso
     * real fuera de 2 llamadas a hasAnyRole() en AsistenciaController.php,
     * ya migradas a la comparación directa de perfil en el mismo commit.
     */
    public function up(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }

    public function down(): void
    {
        // Sin reversa: las tablas de Spatie nunca tuvieron datos reales y el
        // paquete ya no está instalado — recrearlas requeriría reinstalar
        // spatie/laravel-permission y correr sus propias migraciones.
    }
};
