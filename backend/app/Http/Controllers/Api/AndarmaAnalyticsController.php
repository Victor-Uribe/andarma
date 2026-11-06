<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AndarmaAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AndarmaAnalyticsController extends Controller
{
    // Solo contadores agregados. No se guardan IP, fingerprint, nombres ni teléfonos.
    public function record(Request $request, string $event)
    {
        abort_unless(in_array($event, ['visit', 'whatsapp'], true), 404);
        $date = CarbonImmutable::now('America/Mexico_City')->toDateString();
        $now = now()->toDateTimeString();
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('INSERT INTO andarma_daily_analytics (event_date, event_type, total, created_at, updated_at) VALUES (?, ?, 1, ?, ?) ON DUPLICATE KEY UPDATE total = total + 1, updated_at = VALUES(updated_at)', [$date, $event, $now, $now]);
        } elseif ($driver === 'sqlite' || $driver === 'pgsql') {
            DB::statement('INSERT INTO andarma_daily_analytics (event_date, event_type, total, created_at, updated_at) VALUES (?, ?, 1, ?, ?) ON CONFLICT (event_date, event_type) DO UPDATE SET total = andarma_daily_analytics.total + 1, updated_at = EXCLUDED.updated_at', [$date, $event, $now, $now]);
        } else {
            abort(501, 'Motor de base de datos no compatible con el contador atomico.');
        }
        return response()->noContent();
    }

    public function index(Request $request)
    {
        abort_unless(AndarmaAccess::can($request->user(), 'dashboard.view'), 403);
        $data = $request->validate(['period' => 'sometimes|in:7d,30d']);
        $days = ($data['period'] ?? '7d') === '30d' ? 30 : 7;
        $today = CarbonImmutable::now('America/Mexico_City')->startOfDay();
        $since = $today->subDays($days - 1)->toDateString();
        $rows = DB::table('andarma_daily_analytics')
            ->where('event_date', '>=', $since)
            ->where('event_date', '<=', $today->toDateString())
            ->get(['event_date', 'event_type', 'total']);
        $index = [];
        foreach ($rows as $row) {
            $date = substr((string) $row->event_date, 0, 10);
            $index[$date][$row->event_type] = (int) $row->total;
        }
        $views = []; $clicks = []; $totalViews = 0; $totalClicks = 0;
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $today->subDays($i)->toDateString();
            $v = $index[$date]['visit'] ?? 0;
            $c = $index[$date]['whatsapp'] ?? 0;
            $views[] = ['date' => $date, 'day' => substr($date, 5), 'views' => $v];
            $clicks[] = ['date' => $date, 'day' => substr($date, 5), 'clicks' => $c];
            $totalViews += $v;
            $totalClicks += $c;
        }
        return response()->json(['data' => [
            'period' => $days === 30 ? '30d' : '7d',
            'timezone' => 'America/Mexico_City',
            'page_views' => $totalViews,
            'whatsapp_clicks' => $totalClicks,
            'daily_views' => $views,
            'daily_clicks' => $clicks,
        ]]);
    }
}
