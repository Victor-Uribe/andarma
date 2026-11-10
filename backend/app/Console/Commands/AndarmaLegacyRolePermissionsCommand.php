<?php
// ANDARMA_FASE3C_PERMISOS_HEREDADOS_V1
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Solo repara los cuatro permisos users.* heredados del rol Administrador.
 * Nunca altera usuarios, terrenos, contratos, cobros o el rol Superadministrador.
 * Por defecto NO modifica ninguna tabla.
 */
class AndarmaLegacyRolePermissionsCommand extends Command
{
    protected $signature = 'andarma:preflight:permissions
        {--apply : Quitar los cuatro permisos heredados de la base de datos}
        {--confirm= : Confirmación explícita requerida al aplicar}';

    protected $description = 'ANDARMA: revisar o retirar permisos users.* heredados del Administrador';

    private const EXPECTED = [
        'users.create', 'users.delete', 'users.edit', 'users.view',
    ];

    private function legacyPermissions(int $roleId): array
    {
        $items = DB::table('andarma_role_permissions')
            ->where('role_id', $roleId)
            ->where(function ($q) {
                $q->where('permission', 'like', 'users.%')
                  ->orWhere('permission', 'like', 'roles.%');
            })->orderBy('permission')->pluck('permission')->all();
        return array_values(array_map('strval', $items));
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        if ($apply && $this->option('confirm') !== 'QUITAR_4_PERMISOS_ADMIN') {
            $this->error('Se requiere --confirm=QUITAR_4_PERMISOS_ADMIN para modificar la base de datos.');
            return self::FAILURE;
        }
        if (!$apply && $this->option('confirm') !== null) {
            $this->error('No uses --confirm sin --apply.');
            return self::FAILURE;
        }
        try {
            if (!Schema::hasTable('andarma_roles') || !Schema::hasTable('andarma_role_permissions')) {
                $this->error('No están disponibles las tablas de roles y permisos.');
                return self::FAILURE;
            }
            $role = DB::table('andarma_roles')->where('slug', 'administrator')->first();
            if (!$role || (int) $role->id !== 2 || (string) $role->name !== 'Administrador') {
                $this->error('El rol Administrador no coincide exactamente con ID 2; no se realiza ningún cambio.');
                return self::FAILURE;
            }
            $current = $this->legacyPermissions(2);
            $expected = self::EXPECTED;
            sort($current);
            sort($expected);
            if ($current !== [] && $current !== $expected) {
                $this->error('Los permisos actuales difieren de los cuatro detectados. No se permite una eliminación parcial.');
                foreach ($current as $permission) $this->line('  '. $permission);
                return self::FAILURE;
            }
            $this->info('ANDARMA · Fase 3C · Permisos heredados de Administrador');
            $this->line('Rol: Administrador (ID 2). Permisos encontrados: '.count($current));
            foreach ($current as $permission) $this->line('  '. $permission);
            if (!$apply) {
                $this->comment('SOLO LECTURA. No se realizaron cambios.');
                return self::SUCCESS;
            }
            if (!$current) {
                $this->info('Los cuatro permisos ya están ausentes. Nada por modificar.');
                return self::SUCCESS;
            }
            $removed = DB::transaction(function () use ($expected): int {
                $role = DB::table('andarma_roles')->where('id', 2)->lockForUpdate()->first();
                if (!$role || $role->slug !== 'administrator' || $role->name !== 'Administrador') {
                    throw new \RuntimeException('El rol cambió durante la operación.');
                }
                $actual = $this->legacyPermissions(2);
                sort($actual);
                if ($actual !== $expected) {
                    throw new \RuntimeException('Los permisos cambiaron durante la operación.');
                }
                // No revoca sesiones ni borra roles: solo elimina estas cuatro filas.
                $deleted = DB::table('andarma_role_permissions')->where('role_id', 2)
                    ->whereIn('permission', $expected)->delete();
                if ($deleted !== 4) throw new \RuntimeException('El recuento no coincide. Transacción revertida.');
                return $deleted;
            });
            $this->info('Limpieza aplicada en la base configurada: '.$removed.' permisos eliminados.');
            $this->comment('No se modificaron usuarios, terrenos, compradores, pagos ni finanzas.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            // No exponer errores internos de BD o conexión a la salida del comando.
            $this->error('Operación detenida. Revisa el estado de la conexión y los logs locales.');
            return self::FAILURE;
        }
    }
}
