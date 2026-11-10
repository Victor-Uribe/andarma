<?php
// ANDARMA_FASE3D_CONCILIACION_LOTE_V1
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Conciliación excepcional, auditada y de alcance estrictamente limitado.
 * No se ejecuta desde la web. Por defecto: SOLO LECTURA.
 * Solo admite el registro confirmado por la auditoría de preproducción:
 * lote ID 1, manzana M51, código L011, desarrollo El caballito.
 */
class AndarmaLotReconciliationCommand extends Command
{
    protected $signature = 'andarma:preflight:reconcile-lot
        {--lot=1 : ID del terreno a inspeccionar (la corrección solo acepta ID 1)}
        {--action= : assign o available, solo junto con --apply}
        {--client= : ID existente del comprador, requerido para assign}
        {--reason= : Motivo documentado de la corrección (15 a 300 caracteres)}
        {--apply : Autorizar explícitamente una escritura limitada en la BD local}
        {--confirm= : Frase de confirmación exacta para la acción solicitada}';

    protected $description = 'ANDARMA: revisar y, con autorización explícita, conciliar lote vendido sin comprador';

    private const LOT_ID = 1;
    private const LOT_BLOCK = 'M51';
    private const LOT_CODE = 'L011';
    private const DEVELOPMENT = 'El caballito';

    private function lot(int $lotId, bool $lock = false): ?object
    {
        $query = DB::table('andarma_lots as l')
            ->join('andarma_developments as d', 'd.id', '=', 'l.development_id')
            ->where('l.id', $lotId)
            ->select('l.id', 'l.code', 'l.block', 'l.status', 'l.client_id',
                'l.development_id', 'd.name as development_name');
        if ($lock) $query->lockForUpdate();
        return $query->first();
    }

    private function counts(int $lotId): array
    {
        return [
            'financiamientos' => (int) DB::table('andarma_lot_financing')->where('lot_id', $lotId)->count(),
            'pagos_total' => (int) DB::table('andarma_client_payments')->where('lot_id', $lotId)->count(),
            'pagos_aplicados' => (int) DB::table('andarma_client_payments')
                ->where('lot_id', $lotId)->where('status', 'applied')->count(),
            'pagos_cancelados' => (int) DB::table('andarma_client_payments')
                ->where('lot_id', $lotId)->where('status', 'cancelled')->count(),
            'poligonos' => (int) DB::table('andarma_lot_geometries')->where('lot_id', $lotId)->count(),
        ];
    }

    private function assertTarget(object $lot, array $counts): void
    {
        if ((int) $lot->id !== self::LOT_ID
            || trim((string) $lot->block) !== self::LOT_BLOCK
            || trim((string) $lot->code) !== self::LOT_CODE
            || trim((string) $lot->development_name) !== self::DEVELOPMENT) {
            throw new RuntimeException('El terreno no coincide exactamente con M51/L011 de El caballito.');
        }
        if ((string) $lot->status !== 'sold' || $lot->client_id !== null) {
            throw new RuntimeException('El lote dejó de estar vendido sin comprador. No necesita esta conciliación.');
        }
        if ($counts['financiamientos'] !== 0 || $counts['pagos_total'] !== 0) {
            throw new RuntimeException('Existe historial financiero asociado. La corrección automática está prohibida.');
        }
        if ($counts['poligonos'] !== 1) {
            throw new RuntimeException('La cantidad de polígonos cambió respecto a la auditoría. Revisa antes de modificar.');
        }
    }

    public function handle(): int
    {
        $rawId = (string) $this->option('lot');
        if (!ctype_digit($rawId) || (int) $rawId < 1) {
            $this->error('El identificador de lote debe ser un entero positivo.');
            return self::FAILURE;
        }
        $lotId = (int) $rawId;
        $apply = (bool) $this->option('apply');
        $action = (string) ($this->option('action') ?? '');
        $clientRaw = (string) ($this->option('client') ?? '');
        $reason = trim((string) ($this->option('reason') ?? ''));
        $confirm = (string) ($this->option('confirm') ?? '');

        if (!$apply && ($action !== '' || $clientRaw !== '' || $reason !== '' || $confirm !== '')) {
            $this->error('Para diagnóstico solo lectura no envíes opciones de escritura. Usa únicamente --lot=1.');
            return self::FAILURE;
        }
        if ($apply && app()->environment('production')) {
            $this->error('Operación de escritura bloqueada: APP_ENV=production. Solo permitida en preproducción local.');
            return self::FAILURE;
        }
        $clientId = null;
        if ($apply) {
            if ($lotId !== self::LOT_ID) {
                $this->error('Este comando solo permite corregir el lote ID 1.');
                return self::FAILURE;
            }
            if (!in_array($action, ['assign', 'available'], true)) {
                $this->error('Elige --action=assign o --action=available después de comprobar la situación real.');
                return self::FAILURE;
            }
            if (mb_strlen($reason) < 15 || mb_strlen($reason) > 300) {
                $this->error('Debes incluir --reason con motivo documentado de 15 a 300 caracteres.');
                return self::FAILURE;
            }
            if ($action === 'assign') {
                if (!ctype_digit($clientRaw) || (int) $clientRaw < 1) {
                    $this->error('--client requiere el ID numérico de un comprador existente.');
                    return self::FAILURE;
                }
                $clientId = (int) $clientRaw;
                $expected = 'ASIGNAR_LOTE_1_CLIENTE_'.$clientId;
            } else {
                if ($clientRaw !== '') {
                    $this->error('No uses --client para marcar el lote como disponible.');
                    return self::FAILURE;
                }
                $expected = 'CORREGIR_LOTE_1_DISPONIBLE';
            }
            if ($confirm !== $expected) {
                $this->error('Confirmación no válida. Frase esperada: '.$expected);
                return self::FAILURE;
            }
        }

        try {
            foreach ([
                'andarma_lots', 'andarma_developments', 'andarma_clients',
                'andarma_lot_financing', 'andarma_client_payments',
                'andarma_lot_geometries', 'andarma_client_audit',
            ] as $table) {
                if (!Schema::hasTable($table)) {
                    throw new RuntimeException('Falta la tabla '.$table.'. Comprueba las migraciones.');
                }
            }
            $lot = $this->lot($lotId);
            if (!$lot) {
                $this->error('No existe el lote solicitado.');
                return self::FAILURE;
            }
            $counts = $this->counts($lotId);
            $this->info('ANDARMA · Fase 3D · Conciliación controlada');
            $this->line('Lote ID: '.$lot->id.' | '.($lot->block ?? '').' / '.$lot->code.' | '.$lot->development_name);
            $this->line('Estado: '.$lot->status.' | Cliente ID: '.($lot->client_id ?? 'SIN ASIGNAR'));
            foreach ($counts as $name => $count) $this->line('  '.$name.': '.$count);
            if (!$apply) {
                $this->comment('SOLO LECTURA. No se modificaron terrenos, polígonos, clientes ni pagos.');
                if ($lotId === self::LOT_ID && $lot->status === 'sold' && $lot->client_id === null) {
                    $this->warn('Pendiente: confirmar documentalmente si la venta ocurrió antes de ejecutar una acción.');
                }
                return self::SUCCESS;
            }

            DB::transaction(function () use ($lotId, $action, $clientId, $reason): void {
                // Lock y validación repetidos dentro de la misma transacción.
                $lot = $this->lot($lotId, true);
                if (!$lot) throw new RuntimeException('El lote ya no existe.');
                $counts = $this->counts($lotId);
                $this->assertTarget($lot, $counts);

                if ($action === 'assign') {
                    $client = DB::table('andarma_clients')->where('id', $clientId)->lockForUpdate()->first();
                    if (!$client || $client->status !== 'active') {
                        throw new RuntimeException('El cliente no existe o no está activo.');
                    }
                    $written = DB::table('andarma_lots')->where('id', $lotId)
                        ->where('status', 'sold')->whereNull('client_id')
                        ->update(['client_id' => $clientId, 'updated_at' => now()]);
                    if ($written !== 1) throw new RuntimeException('El lote cambió durante la operación.');
                    DB::table('andarma_client_audit')->insert([
                        'actor_user_id' => null,
                        'action' => 'lot.assigned',
                        'client_id' => $clientId,
                        'lot_id' => $lotId,
                        'changes' => json_encode([
                            'origin' => 'andarma:preflight:reconcile-lot',
                            'previous_status' => 'sold', 'new_status' => 'sold',
                            'previous_client_id' => null, 'new_client_id' => $clientId,
                            'reason' => $reason,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'created_at' => now(),
                    ]);
                } else {
                    $written = DB::table('andarma_lots')->where('id', $lotId)
                        ->where('status', 'sold')->whereNull('client_id')
                        ->update(['status' => 'available', 'updated_at' => now()]);
                    if ($written !== 1) throw new RuntimeException('El lote cambió durante la operación.');
                    DB::table('andarma_client_audit')->insert([
                        'actor_user_id' => null,
                        'action' => 'lot.status.corrected',
                        'client_id' => null,
                        'lot_id' => $lotId,
                        'changes' => json_encode([
                            'origin' => 'andarma:preflight:reconcile-lot',
                            'previous_status' => 'sold', 'new_status' => 'available',
                            'reason' => $reason,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'created_at' => now(),
                    ]);
                }
            }, 3);
            $this->info('OPERACIÓN APLICADA. Se modificó exactamente un lote y se generó un registro de auditoría.');
            $this->line('El polígono y el historial financiero se conservaron sin cambios.');
            $this->comment('Ejecuta ahora: php artisan andarma:preflight --strict');
            return self::SUCCESS;
        } catch (Throwable $e) {
            // No publicar SQL, contraseñas, credenciales o datos sensibles.
            if ($e instanceof RuntimeException) $this->error('OPERACIÓN DETENIDA: '.$e->getMessage());
            else $this->error('OPERACIÓN DETENIDA. Revisa los logs de Laravel y la conexión local.');
            return self::FAILURE;
        }
    }
}
