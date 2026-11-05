<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Cobranza ANDARMA V38.
 * Conserva intactos los pagos anulados y no modifica los lotes ni sus compradores.
 * Los saldos se calculan en centavos para reducir errores de redondeo.
 */
class AndarmaFinanceController extends Controller
{
    private const TZ = 'America/Mexico_City';

    private function cents($value): int
    {
        return (int) round((float) ($value ?? 0) * 100);
    }

    private function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function ownedLot(int $lotId, bool $lock = false): object
    {
        $q = DB::table('andarma_lots as l')
            ->join('andarma_clients as c', 'c.id', '=', 'l.client_id')
            ->join('andarma_developments as d', 'd.id', '=', 'l.development_id')
            ->select('l.id', 'l.code', 'l.block', 'l.price', 'l.status', 'l.development_id',
                     'l.client_id', 'c.name as client_name', 'c.phone as client_phone',
                     'd.name as development_name')
            ->where('l.id', $lotId);
        if ($lock) $q->lockForUpdate();
        $lot = $q->first();
        abort_unless($lot, 404, 'La propiedad no existe o no tiene un cliente asignado.');
        return $lot;
    }

    private function monthRows(object $plan, $payments): array
    {
        $financed = $this->cents($plan->sale_price) - $this->cents($plan->discount_amount)
                    - $this->cents($plan->down_payment_amount);
        $installment = $this->cents($plan->monthly_payment_amount);
        if ($financed <= 0 || $installment <= 0 || !$plan->first_payment_period) return [];
        $count = (int) ceil($financed / $installment);
        if ($count > 600) throw ValidationException::withMessages([
            'monthly_payment_amount' => 'Este plan supera 600 mensualidades. Corrige sus condiciones.',
        ]);

        $applied = [];
        foreach ($payments as $p) {
            if ($p->status !== 'applied' || $p->payment_type !== 'monthly' || !$p->applied_period) continue;
            $key = substr((string) $p->applied_period, 0, 7);
            $applied[$key] = ($applied[$key] ?? 0) + $this->cents($p->amount);
        }
        $start = CarbonImmutable::parse($plan->first_payment_period)->startOfMonth();
        $today = CarbonImmutable::now(self::TZ)->toDateString();
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $month = $start->addMonthsNoOverflow($i);
            $period = $month->format('Y-m');
            $due = $month->day(min((int) $plan->payment_due_day, $month->daysInMonth))->toDateString();
            $scheduled = min($installment, $financed - $installment * $i);
            $paid = $applied[$period] ?? 0;
            $pending = max(0, $scheduled - $paid);
            $isOverdue = $pending > 0 && $due < $today;
            $rows[] = [
                'period' => $period,
                'due_date' => $due,
                'scheduled' => $this->decimal($scheduled),
                'paid' => $this->decimal($paid),
                'pending' => $this->decimal($pending),
                'status' => $pending === 0 ? 'paid' : ($isOverdue ? 'overdue' : ($paid > 0 ? 'partial' : 'pending')),
                'is_overdue' => $isOverdue,
            ];
        }
        return $rows;
    }

    public function lots(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:120', 'per_page' => 'nullable|integer|min:1|max:100']);
        $q = DB::table('andarma_lots as l')
            ->join('andarma_clients as c', 'c.id', '=', 'l.client_id')
            ->join('andarma_developments as d', 'd.id', '=', 'l.development_id')
            ->leftJoin('andarma_lot_financing as f', function ($join) {
                $join->on('f.lot_id', '=', 'l.id')->on('f.client_id', '=', 'l.client_id');
            })
            ->select('l.id', 'l.code', 'l.block', 'l.price', 'l.status',
                'c.id as client_id', 'c.name as client_name', 'd.name as development_name',
                'f.id as financing_id');
        if (!empty($data['q'])) {
            $like = '%' . addcslashes(trim($data['q']), '%_\\') . '%';
            $q->where(function ($query) use ($like) {
                $query->where('c.name', 'like', $like)
                    ->orWhere('l.code', 'like', $like)
                    ->orWhere('l.block', 'like', $like)
                    ->orWhere('d.name', 'like', $like);
            });
        }
        $page = $q->orderBy('d.name')->orderBy('l.block')->orderBy('l.code')
            ->paginate($data['per_page'] ?? 30);
        $lotIds = $page->getCollection()->pluck('id')->all();
        $plans = DB::table('andarma_lot_financing')->whereIn('lot_id', $lotIds)->get()->keyBy('lot_id');
        $payments = DB::table('andarma_client_payments')->whereIn('lot_id', $lotIds)->get()->groupBy('lot_id');
        foreach ($page->getCollection() as $lot) {
            $plan = $plans->get($lot->id);
            $lot->payment_tone = 'neutral';
            $lot->payment_label = 'Sin plan';
            if (!$plan) continue;
            $lotPayments = $payments->get($lot->id, collect());
            if ($lotPayments->contains(fn ($p) => $p->status === 'applied' && $p->payment_type === 'monthly' && !$p->applied_period)) {
                $lot->payment_label = 'Requiere conciliación';
                continue;
            }
            $months = count(array_filter($this->monthRows($plan, $lotPayments), fn ($p) => $p['is_overdue']));
            $lot->payment_tone = $months >= 4 ? 'red' : ($months >= 1 ? 'yellow' : 'green');
            $lot->payment_label = $months === 0 ? 'Al corriente' : $months . ($months === 1 ? ' mes de atraso' : ' meses de atraso');
        }
        return response()->json($page);
    }

    public function index(Request $request)
    {
        $d = $request->validate([
            'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d',
            'q' => 'nullable|string|max:120', 'status' => ['nullable', Rule::in(['all', 'applied', 'cancelled'])],
            'client_id' => 'nullable|integer|min:1', 'lot_id' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        if (!empty($d['from']) && !empty($d['to']) && $d['to'] < $d['from']) {
            throw ValidationException::withMessages(['to' => 'La fecha final no puede ser anterior a la inicial.']);
        }
        $q = DB::table('andarma_client_payments as p')
            ->join('andarma_clients as c', 'c.id', '=', 'p.client_id')
            ->leftJoin('andarma_lots as l', 'l.id', '=', 'p.lot_id')
            ->leftJoin('andarma_developments as d', 'd.id', '=', 'l.development_id');
        if (!empty($d['from'])) $q->where('p.paid_at', '>=', $d['from']);
        if (!empty($d['to'])) $q->where('p.paid_at', '<=', $d['to']);
        if (($d['status'] ?? 'all') !== 'all') $q->where('p.status', $d['status']);
        if (!empty($d['client_id'])) $q->where('p.client_id', (int) $d['client_id']);
        if (!empty($d['lot_id'])) $q->where('p.lot_id', (int) $d['lot_id']);
        if (!empty($d['q'])) {
            $like = '%' . addcslashes(trim($d['q']), '%_\\') . '%';
            $q->where(function ($query) use ($like) {
                $query->where('c.name', 'like', $like)->orWhere('l.code', 'like', $like)
                    ->orWhere('d.name', 'like', $like)->orWhere('p.reference', 'like', $like)
                    ->orWhere('p.folio', 'like', $like);
            });
        }
        $stats = (clone $q)->selectRaw(
            "COUNT(*) AS total_count, " .
            "COALESCE(SUM(CASE WHEN p.status = 'applied' THEN p.amount ELSE 0 END),0) AS applied_amount, " .
            "SUM(CASE WHEN p.status = 'applied' THEN 1 ELSE 0 END) AS applied_count, " .
            "SUM(CASE WHEN p.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count"
        )->first();
        $rows = $q->select('p.*', 'c.name as client_name', 'l.code as lot_code', 'l.block as lot_block',
                          'd.name as development_name')
            ->orderByDesc('p.paid_at')->orderByDesc('p.id')->paginate($d['per_page'] ?? 20);
        return response()->json(['data' => $rows->items(), 'current_page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(), 'total' => $rows->total(),
            'summary' => $stats]);
    }

    public function summary(int $lotId)
    {
        $lot = $this->ownedLot($lotId);
        $plan = DB::table('andarma_lot_financing')->where('lot_id', $lotId)
            ->where('client_id', $lot->client_id)->first();
        $payments = DB::table('andarma_client_payments')->where('lot_id', $lotId)
            ->orderByDesc('paid_at')->orderByDesc('id')->get();
        $applied = $payments->where('status', 'applied');
        $monthlyPaid = $applied->where('payment_type', 'monthly')->sum(fn ($p) => $this->cents($p->amount));
        $downPaid = $applied->where('payment_type', 'down_payment')->sum(fn ($p) => $this->cents($p->amount));
        $totalPaid = $applied->sum(fn ($p) => $this->cents($p->amount));
        $periods = $plan ? $this->monthRows($plan, $payments) : [];
        $overdue = array_filter($periods, fn ($row) => $row['is_overdue']);
        $overdueAmount = array_sum(array_map(fn ($row) => $this->cents($row['pending']), $overdue));
        $sale = $plan ? $this->cents($plan->sale_price) : 0;
        $discount = $plan ? $this->cents($plan->discount_amount) : 0;
        $downAgreed = $plan ? $this->cents($plan->down_payment_amount) : 0;
        $financed = max(0, $sale - $discount - $downAgreed);
        $noPeriodTotal = $applied->filter(fn ($p) => $p->payment_type === 'monthly' && !$p->applied_period)
            ->sum(fn ($p) => $this->cents($p->amount));
        return response()->json([
            'lot' => $lot, 'plan' => $plan, 'payments' => $payments, 'periods' => $periods,
            'summary' => $plan ? [
                'sale_price' => $this->decimal($sale), 'discount' => $this->decimal($discount),
                'agreed_down_payment' => $this->decimal($downAgreed), 'down_payment_paid' => $this->decimal($downPaid),
                'down_payment_pending' => $this->decimal(max(0, $downAgreed - $downPaid)),
                'financed_amount' => $this->decimal($financed), 'monthly_payment' => $this->decimal($this->cents($plan->monthly_payment_amount)),
                'monthly_paid' => $this->decimal($monthlyPaid), 'total_paid' => $this->decimal($totalPaid),
                'total_balance' => $this->decimal(max(0, $sale - $discount - $totalPaid)),
                'financed_balance' => $this->decimal(max(0, $financed - $monthlyPaid)),
                'overdue_periods' => count($overdue), 'overdue_amount' => $this->decimal($overdueAmount),
                'unallocated_monthly_amount' => $this->decimal($noPeriodTotal),
            ] : null,
        ]);
    }

    public function saveTerms(Request $request, int $lotId)
    {
        $d = $request->validate([
            'sale_price' => 'required|numeric|gt:0|max:9999999999',
            'discount_amount' => 'required|numeric|min:0',
            'down_payment_amount' => 'required|numeric|min:0',
            'monthly_payment_amount' => 'required|numeric|min:0',
            'payment_due_day' => 'required|integer|between:1,31',
            'first_payment_period' => 'nullable|date_format:Y-m',
            'sale_date' => 'nullable|date_format:Y-m-d',
        ]);
        $net = $this->cents($d['sale_price']) - $this->cents($d['discount_amount']);
        $financed = $net - $this->cents($d['down_payment_amount']);
        $month = $this->cents($d['monthly_payment_amount']);
        if ($net <= 0 || $financed < 0) throw ValidationException::withMessages([
            'down_payment_amount' => 'El descuento y el enganche no pueden superar el precio de venta.',
        ]);
        if ($financed > 0 && ($month <= 0 || empty($d['first_payment_period'])))
            throw ValidationException::withMessages(['monthly_payment_amount' => 'Captura mensualidad y primer mes de pago para un financiamiento.']);
        if ($financed > 0 && (int) ceil($financed / $month) > 600)
            throw ValidationException::withMessages(['monthly_payment_amount' => 'El plan no puede superar 600 mensualidades.']);
        return DB::transaction(function () use ($request, $d, $lotId) {
            $lot = $this->ownedLot($lotId, true);
            if (!in_array($lot->status, ['reserved', 'sold'], true)) return response()->json([
                'message' => 'Primero aparta o vende la propiedad al cliente.'], 409);
            $exists = DB::table('andarma_client_payments')->where('lot_id', $lotId)->exists();
            if ($exists) return response()->json([
                'message' => 'Esta propiedad ya tiene pagos. Para modificar condiciones se necesita un procedimiento de ajuste auditado.'], 409);
            $row = [
                'client_id' => $lot->client_id, 'sale_price' => $d['sale_price'],
                'discount_amount' => $d['discount_amount'], 'down_payment_amount' => $d['down_payment_amount'],
                'monthly_payment_amount' => $d['monthly_payment_amount'], 'payment_due_day' => $d['payment_due_day'],
                'first_payment_period' => !empty($d['first_payment_period']) ? $d['first_payment_period'] . '-01' : null,
                'sale_date' => $d['sale_date'] ?? null, 'updated_by' => $request->user()->id,
                'updated_at' => now(),
            ];
            $existing = DB::table('andarma_lot_financing')->where('lot_id', $lotId)->first();
            if ($existing) {
                DB::table('andarma_lot_financing')->where('lot_id', $lotId)->update($row);
            } else {
                DB::table('andarma_lot_financing')->insert(['lot_id' => $lotId, ...$row, 'created_at' => now()]);
            }
            return response()->json(['message' => 'Condiciones de venta guardadas correctamente.']);
        });
    }

    public function storePayment(Request $request, int $lotId)
    {
        $d = $request->validate([
            'paid_at' => 'required|date_format:Y-m-d', 'amount' => 'required|numeric|gt:0|max:9999999999',
            'payment_type' => ['required', Rule::in(['monthly', 'down_payment'])],
            'applied_period' => 'nullable|date_format:Y-m',
            'payment_method' => ['required', Rule::in(['cash', 'transfer', 'deposit', 'card', 'other'])],
            'reference' => 'nullable|string|max:150', 'folio' => 'nullable|string|max:150',
            'notes' => 'nullable|string|max:3000', 'request_key' => 'required|uuid',
        ]);
        if ($d['payment_type'] === 'monthly' && empty($d['applied_period'])) throw ValidationException::withMessages([
            'applied_period' => 'Selecciona el mes al que se aplicará este pago.',
        ]);
        return DB::transaction(function () use ($request, $d, $lotId) {
            $lot = $this->ownedLot($lotId, true);
            // ANDARMA_FASE15_VALIDAR_ESTADO_PROPIEDAD
            // Un registro de pago requiere una propiedad apartada o vendida.
            // No se modifican ni eliminan pagos previos de propiedades con otro estado.
            if (!in_array($lot->status, ['reserved', 'sold'], true)) {
                return response()->json([
                    'message' => 'No es posible registrar un pago: la propiedad debe estar apartada o vendida.',
                ], 409);
            }
            $plan = DB::table('andarma_lot_financing')->where('lot_id', $lotId)->lockForUpdate()->first();
            if (!$plan || (int) $plan->client_id !== (int) $lot->client_id)
                return response()->json(['message' => 'Configura primero las condiciones de venta de esta propiedad.'], 409);
            $amount = $this->cents($d['amount']);
            $existing = DB::table('andarma_client_payments')->where('request_key', $d['request_key'])->first();
            if ($existing) {
                if ((int) $existing->lot_id !== $lotId || $existing->payment_type !== $d['payment_type']
                    || (int) round((float) $existing->amount * 100) !== $amount
                    || substr((string) $existing->paid_at, 0, 10) !== $d['paid_at']
                    || ($d['payment_type'] === 'monthly' && substr((string) $existing->applied_period, 0, 7) !== $d['applied_period'])
                    || $existing->status !== 'applied') {
                    return response()->json(['message' => 'La clave de operación ya fue utilizada en un movimiento distinto o cancelado.'], 409);
                }
                return response()->json(['data' => $existing, 'duplicate_request' => true]);
            }
            $periodDate = null;
            $dueDate = null;
            if ($d['payment_type'] === 'monthly') {
                $periods = $this->monthRows($plan, DB::table('andarma_client_payments')->where('lot_id', $lotId)->get());
                $current = collect($periods)->firstWhere('period', $d['applied_period']);
                if (!$current) return response()->json(['message' => 'El mes no forma parte del calendario de financiamiento.'], 422);
                if ($amount > $this->cents($current['pending'])) return response()->json([
                    'message' => 'El monto supera el importe pendiente de ese mes.'], 422);
                $periodDate = $d['applied_period'] . '-01';
                $dueDate = $current['due_date'];
            } else {
                $paid = DB::table('andarma_client_payments')->where('lot_id', $lotId)
                    ->where('status', 'applied')->where('payment_type', 'down_payment')->sum('amount');
                $remaining = $this->cents($plan->down_payment_amount) - $this->cents($paid);
                if ($amount > $remaining) return response()->json([
                    'message' => 'El pago excede el enganche pendiente acordado.'], 422);
            }
            $id = DB::table('andarma_client_payments')->insertGetId([
                'client_id' => $lot->client_id, 'lot_id' => $lotId,
                'paid_at' => $d['paid_at'], 'amount' => $d['amount'], 'payment_type' => $d['payment_type'],
                'payment_method' => $d['payment_method'], 'reference' => $d['reference'] ?? null,
                'folio' => $d['folio'] ?? null, 'notes' => $d['notes'] ?? null,
                'applied_period' => $periodDate, 'applied_due_date' => $dueDate,
                'request_key' => $d['request_key'], 'status' => 'applied',
                'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return response()->json(['message' => 'Pago registrado correctamente.',
                'data' => DB::table('andarma_client_payments')->find($id)], 201);
        });
    }

    // ANDARMA_F18_AUDITORIA_FINANCIERA: historial de solo lectura; no altera pagos.
    public function audit(Request $request)
    {
        // Restringir la consulta a usuarios autorizados para gestionar cancelaciones.
        abort_unless(\App\Support\AndarmaAccess::can($request->user(), 'payments.delete'),
            403, 'No tienes permiso para consultar la auditoría financiera.');

        $data = $request->validate([
            'action' => ['nullable', Rule::in(['all', 'registered', 'cancelled'])],
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'per_page' => 'nullable|integer|min:1|max:50',
            'page' => 'nullable|integer|min:1',
        ]);
        if (!empty($data['from']) && !empty($data['to']) && $data['to'] < $data['from']) {
            throw ValidationException::withMessages(['to' => 'La fecha final no puede ser anterior a la inicial.']);
        }

        // Cada evento incluye únicamente datos de auditoría. Un pago cancelado
        // aparece dos veces: al registrarlo y al cancelarlo (dos operaciones reales).
        $registered = DB::table('andarma_client_payments as p')
            ->leftJoin('users as u', 'u.id', '=', 'p.created_by')
            ->select('p.id as payment_id', 'p.lot_id', 'p.client_id',
                'p.amount', 'p.payment_type', 'p.paid_at', 'p.status',
                'p.created_by as actor_id', 'u.name as actor_name')
            ->selectRaw("'registered' as action, p.created_at as occurred_at, NULL as reason");

        $cancelled = DB::table('andarma_client_payments as p')
            ->leftJoin('users as u', 'u.id', '=', 'p.cancelled_by')
            ->whereNotNull('p.cancelled_at')
            ->select('p.id as payment_id', 'p.lot_id', 'p.client_id',
                'p.amount', 'p.payment_type', 'p.paid_at', 'p.status',
                'p.cancelled_by as actor_id', 'u.name as actor_name')
            ->selectRaw("'cancelled' as action, p.cancelled_at as occurred_at, p.cancel_reason as reason");

        $query = DB::query()->fromSub($registered->unionAll($cancelled), 'audit');
        if (($data['action'] ?? 'all') !== 'all') $query->where('action', $data['action']);
        if (!empty($data['from'])) $query->where('occurred_at', '>=', $data['from'] . ' 00:00:00');
        if (!empty($data['to'])) $query->where('occurred_at', '<=', $data['to'] . ' 23:59:59');
        $page = $query->orderByDesc('occurred_at')->orderByDesc('payment_id')
            ->paginate($data['per_page'] ?? 20);

        return response()->json([
            'data' => $page->items(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }

    public function cancelPayment(Request $request, int $paymentId)
    {
        $d = $request->validate(['reason' => 'required|string|min:10|max:1000']);
        return DB::transaction(function () use ($request, $d, $paymentId) {
            $p = DB::table('andarma_client_payments')->where('id', $paymentId)->lockForUpdate()->first();
            abort_unless($p, 404);
            if ($p->status !== 'applied') return response()->json(['message' => 'El movimiento ya está cancelado.'], 409);
            DB::table('andarma_client_payments')->where('id', $paymentId)->update([
                'status' => 'cancelled', 'cancel_reason' => trim($d['reason']),
                'cancelled_at' => now(), 'cancelled_by' => $request->user()->id,
                'updated_at' => now(),
            ]);
            return response()->json(['message' => 'Movimiento cancelado; permanece en el historial.']);
        });
    }
}
