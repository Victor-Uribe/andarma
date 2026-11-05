<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Consulta transversal: solo información de búsqueda. Los importes se consultan en Finanzas con payments.view. */
class AndarmaLookupController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => 'required|string|min:2|max:120']);
        $term = trim($data['q']);
        // Permite escribir "lote 0181", "manzana M5" o simplemente "0181".
        $normalized = preg_replace('/^(?:lote|terreno|manzana|mz\.?|lt\.?)\s*[#:\-]?\s*/iu', '', $term);
        $needle = trim($normalized) !== '' ? trim($normalized) : $term;
        $like = '%' . addcslashes($term, '%_\\') . '%';
        $lotLike = '%' . addcslashes($needle, '%_\\') . '%';

        $lots = DB::table('andarma_lots as l')
            ->join('andarma_developments as d', 'd.id', '=', 'l.development_id')
            ->leftJoin('andarma_clients as c', 'c.id', '=', 'l.client_id')
            ->select('l.id as lot_id', 'l.code', 'l.block', 'l.status',
                     'l.client_id', 'c.name as client_name', 'c.phone as client_phone',
                     'd.id as development_id', 'd.name as development_name')
            ->where(function ($query) use ($like, $lotLike) {
                $query->where('l.code', 'like', $lotLike)
                    ->orWhere('l.block', 'like', $lotLike)
                    ->orWhere('d.name', 'like', $like)
                    ->orWhere('c.name', 'like', $like)
                    ->orWhere('c.phone', 'like', $like);
            })->orderBy('d.name')->orderBy('l.block')->orderBy('l.code')->limit(35)->get();

        $clients = DB::table('andarma_clients as c')
            ->select('c.id as client_id', 'c.name as client_name', 'c.phone as client_phone')
            ->where(function ($query) use ($like) {
                $query->where('c.name', 'like', $like)->orWhere('c.phone', 'like', $like)
                    ->orWhere('c.email', 'like', $like);
            })->orderBy('c.name')->limit(15)->get();

        return response()->json(['data' => ['lots' => $lots, 'clients' => $clients]]);
    }
}
