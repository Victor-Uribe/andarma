<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Exclusivamente datos comerciales. No llamar al controlador administrativo. */
class AndarmaPublicMapsController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['development_id' => 'sometimes|integer|min:1']);
        $query = DB::table('andarma_plans as p')
            ->join('andarma_developments as d', 'd.id', '=', 'p.development_id')
            ->where('d.status', 'active')->where('p.is_active', 1)
            ->select('p.id', 'p.development_id', 'p.title', 'p.image_path');
        if (isset($filters['development_id'])) $query->where('p.development_id', $filters['development_id']);
        $plans = $query->orderBy('p.id')->get()->map(function ($plan) {
            return [
                'id' => $plan->id,
                'development_id' => $plan->development_id,
                'title' => $plan->title,
                'image_url' => Storage::disk('public')->url($plan->image_path),
            ];
        });
        return response()->json(['data' => $plans]);
    }

    public function show(int $id)
    {
        $plan = DB::table('andarma_plans as p')
            ->join('andarma_developments as d', 'd.id', '=', 'p.development_id')
            ->where('p.id', $id)->where('p.is_active', 1)->where('d.status', 'active')
            ->select('p.id', 'p.development_id', 'p.title', 'p.image_path')->first();
        abort_unless($plan, 404);
        $polygons = DB::table('andarma_lot_geometries as g')
            ->join('andarma_lots as l', 'l.id', '=', 'g.lot_id')
            ->where('g.plan_id', $plan->id)
            ->where('l.development_id', $plan->development_id)
            ->whereIn('l.status', ['available', 'reserved', 'sold'])
            ->select('g.lot_id', 'g.points', 'l.code', 'l.block', 'l.area_m2', 'l.price', 'l.status')
            ->orderBy('l.code')->get()->map(function ($row) {
                $points = json_decode($row->points, true);
                if (!is_array($points) || count($points) < 3) return null;
                // Never expose sensitive columns: client_id, buyer, phone, notes, payment details.
                return [
                    'lot_id' => $row->lot_id,
                    'code' => $row->code,
                    'block' => $row->block,
                    'area_m2' => $row->area_m2,
                    'price' => $row->status === 'available' ? $row->price : null,
                    'status' => $row->status,
                    'points' => array_values(array_map(function ($point) {
                        return [
                            'x' => max(0, min(1000, (float)($point['x'] ?? 0))),
                            'y' => max(0, min(1000, (float)($point['y'] ?? 0))),
                        ];
                    }, $points)),
                ];
            })->filter()->values();
        return response()->json([
            'data' => [
                'id' => $plan->id,
                'development_id' => $plan->development_id,
                'title' => $plan->title,
                'image_url' => Storage::disk('public')->url($plan->image_path),
            ],
            'polygons' => $polygons,
        ]);
    }
}
