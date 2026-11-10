<?php
// ANDARMA_FASE3B_DETALLES_V1
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Diagnóstico de registros encontrados por andarma:preflight.
 * SOLO LECTURA. No imprime nombres de compradores, teléfonos o montos.
 */
class AndarmaPreflightDetailsCommand extends Command
{
    protected $signature = 'andarma:preflight:details
        {--limit=30 : Máximo de terrenos o asignaciones por bloque (1-100)}
        {--json : Entregar JSON para revisión local}';

    protected $description = 'Detalle de incidencias ANDARMA de preproducción (solo lectura)';

    private function countByStatus(string $status, int $lotId): int
    {
        return (int) DB::table('andarma_client_payments')
            ->where('lot_id', $lotId)->where('status', $status)->count();
    }

    public function handle(): int
    {
        $limitRaw = (string) $this->option('limit');
        if (!ctype_digit($limitRaw) || (int) $limitRaw < 1 || (int) $limitRaw > 100) {
            $this->error('El límite debe estar entre 1 y 100.');
            return self::FAILURE;
        }
        $limit = (int) $limitRaw;
        $tables = [
            'andarma_lots', 'andarma_developments', 'andarma_lot_financing',
            'andarma_client_payments', 'andarma_lot_geometries',
            'andarma_roles', 'andarma_role_permissions',
        ];
        try {
            foreach ($tables as $table) {
                if (!Schema::hasTable($table)) {
                    $this->error('Falta la tabla '.$table.'. No se ejecutó la consulta.');
                    return self::FAILURE;
                }
            }

            $base = DB::table('andarma_lots as l')
                ->join('andarma_developments as d', 'd.id', '=', 'l.development_id')
                ->where('l.status', 'sold')->whereNull('l.client_id');
            $total = (clone $base)->count();
            $lots = (clone $base)
                ->select('l.id', 'l.code', 'l.block', 'd.name as development_name')
                ->orderBy('l.id')->limit($limit)->get()
                ->map(function ($lot) {
                    $id = (int) $lot->id;
                    return [
                        'lot_id' => $id,
                        'code' => (string) $lot->code,
                        'block' => (string) ($lot->block ?? ''),
                        'development' => (string) $lot->development_name,
                        'financing_count' => (int) DB::table('andarma_lot_financing')
                            ->where('lot_id', $id)->count(),
                        'payments_applied' => $this->countByStatus('applied', $id),
                        'payments_cancelled' => $this->countByStatus('cancelled', $id),
                        'polygon_count' => (int) DB::table('andarma_lot_geometries')
                            ->where('lot_id', $id)->count(),
                    ];
                })->values()->all();

            $roleQuery = DB::table('andarma_role_permissions as rp')
                ->join('andarma_roles as r', 'r.id', '=', 'rp.role_id')
                ->where('r.slug', '!=', 'superadmin')
                ->where(function ($q) {
                    $q->where('rp.permission', 'like', 'users.%')
                      ->orWhere('rp.permission', 'like', 'roles.%');
                });
            $roleTotal = (clone $roleQuery)->count();
            $roles = (clone $roleQuery)
                ->select('r.id as role_id', 'r.name as role_name', 'r.slug as role_slug', 'rp.permission')
                ->orderBy('r.id')->orderBy('rp.permission')->limit($limit)->get()
                ->map(fn ($row) => [
                    'role_id' => (int) $row->role_id,
                    'role_name' => (string) $row->role_name,
                    'role_slug' => (string) $row->role_slug,
                    'permission' => (string) $row->permission,
                ])->values()->all();
        } catch (Throwable $e) {
            // Nunca imprimir cadenas de conexión, SQL, credenciales ni secretos.
            $this->error('La consulta falló. Revisa conexión y estructura; consulta los logs locales.');
            return self::FAILURE;
        }

        $legalPath = dirname(base_path()).'/frontend/public/andarma-legacy.js';
        $legal = [
            'privacy' => 'no_inspeccionado',
            'terms' => 'no_inspeccionado',
            'cookies' => 'no_inspeccionado',
        ];
        if (is_file($legalPath) && is_readable($legalPath)) {
            $source = file_get_contents($legalPath);
            if (is_string($source)) {
                $legal = [
                    'privacy' => str_contains($source, 'Documento pendiente de validación.') ? 'provisional' : 'sin_marcador_provisional',
                    'terms' => str_contains($source, 'Documento pendiente de aprobación.') ? 'provisional' : 'sin_marcador_provisional',
                    'cookies' => str_contains($source, 'Política por completar.') ? 'provisional' : 'sin_marcador_provisional',
                ];
            }
        }
        $result = [
            'read_only' => true,
            'sold_without_buyer' => ['total' => $total, 'items' => $lots, 'truncated' => $total > count($lots)],
            'legacy_privileged_permissions' => ['total' => $roleTotal, 'items' => $roles, 'truncated' => $roleTotal > count($roles)],
            'legal_placeholders' => $legal,
        ];
        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('ANDARMA · Detalle de incidencias (SOLO LECTURA)');
            $this->line('TERRENOS VENDIDOS SIN COMPRADOR: '.$total);
            foreach ($lots as $lot) {
                $this->line(sprintf('  Lote ID %d | %s / %s | %s',
                    $lot['lot_id'], $lot['block'], $lot['code'], $lot['development']));
                $this->line(sprintf('    Financiamientos: %d | Pagos aplicados: %d | Cancelados: %d | Polígonos: %d',
                    $lot['financing_count'], $lot['payments_applied'], $lot['payments_cancelled'], $lot['polygon_count']));
            }
            if ($total > count($lots)) $this->warn('Hay más terrenos: aumenta --limit para revisarlos.');
            $this->line('ASIGNACIONES DE PERMISOS HEREDADOS: '.$roleTotal);
            foreach ($roles as $role) {
                $this->line(sprintf('  Rol ID %d | %s (%s) | permiso: %s',
                    $role['role_id'], $role['role_name'], $role['role_slug'], $role['permission']));
            }
            if ($roleTotal > count($roles)) $this->warn('Hay más permisos: aumenta --limit para revisarlos.');
            $this->line('DOCUMENTOS LEGALES:');
            foreach ($legal as $key => $value) $this->line('  '.$key.': '.$value);
            $this->comment('Sin escrituras. Revisa el expediente y los roles antes de autorizar cambios.');
        }
        return self::SUCCESS;
    }
}
