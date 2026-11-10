<?php
// ANDARMA_FASE3_PREFLIGHT_V1
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Diagnóstico NO destructivo: sólo lee configuración, archivos y tablas.
 * No imprime nombres de clientes, teléfonos, precios individuales ni secretos.
 * Ejecución: php artisan andarma:preflight [--strict] [--production] [--json]
 */
class AndarmaPreflightCommand extends Command
{
    protected $signature = 'andarma:preflight
        {--strict : Fallar también si hay advertencias}
        {--production : Verificar requisitos de configuración para producción}
        {--json : Entregar resultado JSON para revisión automatizada}';

    protected $description = 'Inspección de integridad de ANDARMA sin escribir en la base de datos';

    private array $findings = [];
    private array $readyTables = [];
    private int $checks = 0;
    private int $skipped = 0;

    private function issue(string $level, string $code, string $detail, int $count = 0): void
    {
        $this->findings[] = [
            'severity' => $level,
            'code' => $code,
            'count' => $count,
            'detail' => $detail,
        ];
    }

    private function inspect(string $code, string $level, string $detail, array $tables, callable $query): void
    {
        foreach ($tables as $table) {
            if (!($this->readyTables[$table] ?? false)) {
                $this->skipped++;
                return;
            }
        }
        try {
            $count = (int) $query();
            $this->checks++;
            if ($count > 0) $this->issue($level, $code, $detail, $count);
        } catch (Throwable $e) {
            // No presentar datos de la conexión, credenciales ni detalles internos.
            $this->issue('ERROR', 'QUERY_'.$code,
                'Falló una consulta de verificación. Revisa esquema, logs y permisos de lectura.');
        }
    }

    private function environmentChecks(): void
    {
        if (!$this->option('production')) return;

        $this->checks++;
        if (config('app.env') !== 'production')
            $this->issue('ERROR', 'ENV_NOT_PRODUCTION', 'APP_ENV no es production.');

        $this->checks++;
        if ((bool) config('app.debug'))
            $this->issue('ERROR', 'DEBUG_ENABLED', 'APP_DEBUG está activo.');

        $this->checks++;
        if (!is_string(config('app.key')) || config('app.key') === '')
            $this->issue('ERROR', 'APP_KEY_MISSING', 'La clave APP_KEY no está configurada.');

        $this->checks++;
        $url = (string) config('app.url');
        if (!str_starts_with(strtolower($url), 'https://'))
            $this->issue('ERROR', 'APP_URL_NOT_HTTPS', 'APP_URL debe ser HTTPS en producción.');
    }

    private function legalContentChecks(): void
    {
        $path = dirname(base_path()).'/frontend/public/andarma-legacy.js';
        if (!is_file($path)) {
            $this->issue('WARN', 'LEGAL_NOT_INSPECTED',
                'No está el frontend fuente en este servidor; verifica manualmente los documentos legales publicados.');
            return;
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            $this->issue('WARN', 'LEGAL_UNREADABLE', 'No se pudo inspeccionar la plantilla legal pública.');
            return;
        }
        $this->checks++;
        foreach (['Documento pendiente de validación.', 'Documento pendiente de aprobación.',
                  'Política por completar.'] as $placeholder) {
            if (str_contains($content, $placeholder)) {
                $this->issue('ERROR', 'LEGAL_PLACEHOLDERS',
                    'La web aún contiene avisos legales provisionales. Deben aprobarse antes de publicar.');
                break;
            }
        }
    }

    private function databaseChecks(): void
    {
        $needed = [
            'andarma_developments', 'andarma_lots', 'andarma_clients',
            'andarma_lot_financing', 'andarma_client_payments',
            'andarma_lot_geometries', 'andarma_plans', 'andarma_roles',
            'andarma_role_permissions', 'andarma_client_audit',
            'personal_access_tokens',
        ];
        foreach ($needed as $table) {
            try {
                $ok = Schema::hasTable($table);
                $this->readyTables[$table] = $ok;
                $this->checks++;
                if (!$ok) $this->issue('ERROR', 'MISSING_TABLE', 'Falta la tabla '.$table.'.');
            } catch (Throwable $e) {
                $this->issue('ERROR', 'DB_UNAVAILABLE',
                    'No fue posible consultar el esquema de datos. Verifica DB_CONNECTION y conectividad.');
                return;
            }
        }

        $this->inspect('BUYER_AVAILABLE', 'ERROR',
            'Existen terrenos con cliente asignado pero estado disponible o inactivo.',
            ['andarma_lots'], fn () => DB::table('andarma_lots')
                ->whereNotNull('client_id')->whereIn('status', ['available','inactive'])->count());

        $this->inspect('SOLD_WITHOUT_BUYER', 'ERROR',
            'Existen terrenos vendidos sin comprador asignado.',
            ['andarma_lots'], fn () => DB::table('andarma_lots')
                ->where('status', 'sold')->whereNull('client_id')->count());

        $this->inspect('FINANCING_BUYER_MISMATCH', 'ERROR',
            'Un financiamiento apunta a un cliente distinto del asignado al terreno.',
            ['andarma_lot_financing','andarma_lots'], fn () =>
                DB::table('andarma_lot_financing as f')
                    ->join('andarma_lots as l','l.id','=','f.lot_id')
                    ->where(function ($q) {
                        $q->whereNull('l.client_id')
                            ->orWhereColumn('f.client_id','!=','l.client_id');
                    })->count());

        $this->inspect('FINANCING_WITH_INVALID_STATUS', 'ERROR',
            'Hay contratos asociados a terrenos que no están apartados ni vendidos.',
            ['andarma_lot_financing','andarma_lots'], fn () =>
                DB::table('andarma_lot_financing as f')
                    ->join('andarma_lots as l','l.id','=','f.lot_id')
                    ->whereNotIn('l.status', ['reserved','sold'])->count());

        $this->inspect('PAYMENT_BUYER_MISMATCH', 'ERROR',
            'Un pago corresponde a un cliente distinto del asignado al terreno.',
            ['andarma_client_payments','andarma_lots'], fn () =>
                DB::table('andarma_client_payments as p')
                    ->join('andarma_lots as l','l.id','=','p.lot_id')
                    ->where(function ($q) {
                        $q->whereNull('l.client_id')
                            ->orWhereColumn('p.client_id','!=','l.client_id');
                    })->count());

        $this->inspect('APPLIED_PAYMENT_NO_PLAN', 'WARN',
            'Hay pagos aplicados a terrenos sin financiamiento; revisar registros históricos.',
            ['andarma_client_payments','andarma_lot_financing'], fn () =>
                DB::table('andarma_client_payments as p')
                    ->leftJoin('andarma_lot_financing as f','f.lot_id','=','p.lot_id')
                    ->whereNotNull('p.lot_id')->where('p.status','applied')
                    ->whereNull('f.id')->count());

        $this->inspect('INVALID_PAYMENT_AMOUNT', 'ERROR',
            'Existen pagos de importe cero o negativo.',
            ['andarma_client_payments'], fn () =>
                DB::table('andarma_client_payments')->where('amount','<=',0)->count());

        $this->inspect('INVALID_FINANCING_AMOUNT', 'ERROR',
            'Existen contratos con importes no válidos o enganches mayores que el precio neto.',
            ['andarma_lot_financing'], fn () => DB::table('andarma_lot_financing as f')
                ->where(function ($q) {
                    $q->where('f.sale_price','<=',0)
                      ->orWhere('f.discount_amount','<',0)
                      ->orWhere('f.down_payment_amount','<',0)
                      ->orWhere('f.monthly_payment_amount','<',0)
                      ->orWhereRaw('(f.sale_price - f.discount_amount - f.down_payment_amount) < 0')
                      ->orWhereRaw('(f.sale_price - f.discount_amount) <= 0')
                      ->orWhere(function ($financed) {
                          $financed->whereRaw('(f.sale_price - f.discount_amount - f.down_payment_amount) > 0')
                            ->where(function ($missing) {
                                $missing->where('f.monthly_payment_amount','<=',0)
                                    ->orWhereNull('f.first_payment_period');
                            });
                      });
                })->count());

        $this->inspect('PAYMENT_ABOVE_SALE_PRICE', 'ERROR',
            'Suma de pagos aplicados superior al precio de venta menos descuentos.',
            ['andarma_client_payments','andarma_lot_financing'], fn () =>
                DB::table('andarma_lot_financing as f')
                    ->whereRaw("(SELECT COALESCE(SUM(p.amount), 0) FROM andarma_client_payments p WHERE p.lot_id = f.lot_id AND p.status = 'applied') > (f.sale_price - f.discount_amount)")
                    ->count());

        $this->inspect('UNALLOCATED_MONTHLY_PAYMENTS', 'WARN',
            'Hay mensualidades aplicadas sin mes asignado; requieren conciliación.',
            ['andarma_client_payments'], fn () =>
                DB::table('andarma_client_payments')->where('status','applied')
                    ->where('payment_type','monthly')->whereNull('applied_period')->count());

        $this->inspect('POLYGON_DEVELOPMENT_MISMATCH', 'ERROR',
            'Hay polígonos de terreno asociados al mapa de otro desarrollo.',
            ['andarma_lot_geometries','andarma_plans','andarma_lots'], fn () =>
                DB::table('andarma_lot_geometries as g')
                    ->join('andarma_plans as p','p.id','=','g.plan_id')
                    ->join('andarma_lots as l','l.id','=','g.lot_id')
                    ->whereColumn('p.development_id','!=','l.development_id')->count());

        $this->inspect('LEGACY_PRIVILEGED_ROLES', 'WARN',
            'Roles anteriores conservan permisos users.* o roles.*; el código los niega, conviene revisar el catálogo.',
            ['andarma_role_permissions','andarma_roles'], fn () =>
                DB::table('andarma_role_permissions as rp')
                    ->join('andarma_roles as r','r.id','=','rp.role_id')
                    ->where('r.slug','!=','superadmin')
                    ->where(function ($q) {
                        $q->where('rp.permission','like','users.%')
                            ->orWhere('rp.permission','like','roles.%');
                    })->count());
    }

    public function handle(): int
    {
        $this->environmentChecks();
        $this->legalContentChecks();
        $this->databaseChecks();

        $errors = count(array_filter($this->findings, fn ($item) => $item['severity'] === 'ERROR'));
        $warnings = count(array_filter($this->findings, fn ($item) => $item['severity'] === 'WARN'));
        $strict = (bool) $this->option('strict');
        $passed = $errors === 0 && (!$strict || $warnings === 0);

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'ok' => $passed,
                'checks' => $this->checks,
                'skipped' => $this->skipped,
                'error_types' => $errors,
                'warning_types' => $warnings,
                'findings' => $this->findings,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info('ANDARMA · Auditoría de preproducción (solo lectura)');
            $this->line('Consultas y comprobaciones: '.$this->checks.' | Omitidas: '.$this->skipped);
            foreach ($this->findings as $item) {
                $this->line(sprintf('[%s] %s | registros: %d | %s',
                    $item['severity'], $item['code'], $item['count'], $item['detail']));
            }
            if (!$this->findings) $this->info('No se detectaron incidencias en las reglas consultadas.');
            $this->line($passed ? 'RESULTADO: APROBADO EN ESTA REVISIÓN PARCIAL' : 'RESULTADO: REQUIERE CORRECCIÓN');
        }
        return $passed ? self::SUCCESS : self::FAILURE;
    }
}
