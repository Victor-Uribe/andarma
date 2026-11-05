<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;
use Carbon\CarbonImmutable;

class AndarmaInventoryMapsController extends Controller
{
    private function superAdmin(Request $r): void {
        abort_unless(\App\Support\AndarmaAccess::can($r->user(), 'maps.edit') || \App\Support\AndarmaAccess::can($r->user(), 'maps.create') || \App\Support\AndarmaAccess::can($r->user(), 'maps.delete'), 403, 'No tienes permisos para modificar planos y polígonos.');
    }
    private function planRow(int $id) {
        $p=DB::table('andarma_plans as p')->join('andarma_developments as d','d.id','=','p.development_id')
          ->where('p.id',$id)->select('p.*','d.name as development_name')->first();
        abort_unless($p,404);
        $p->image_url=Storage::disk('public')->url($p->image_path);
        return $p;
    }
    public function permissions(Request $r) {
        return response()->json(['superadmin'=>\App\Support\AndarmaAccess::can($r->user(), 'maps.edit'), 'can_create'=>\App\Support\AndarmaAccess::can($r->user(), 'maps.create'), 'can_delete'=>\App\Support\AndarmaAccess::can($r->user(), 'maps.delete')]);
    }
    public function developments() {
        return response()->json(['data'=>DB::table('andarma_developments')->select('id','name','status')->orderBy('name')->get()]);
    }
    public function inventory(Request $r) {
        $d=$r->validate(['q'=>'nullable|string|max:120','development_id'=>'nullable|integer','status'=>'nullable|in:available,reserved,sold,inactive','mapped'=>'nullable|in:yes,no','page'=>'nullable|integer|min:1','per_page'=>'nullable|integer|min:1|max:100']);
        $query=DB::table('andarma_lots as l')->join('andarma_developments as d','d.id','=','l.development_id')
          ->leftJoin('andarma_lot_geometries as g','g.lot_id','=','l.id')
          ->leftJoin('andarma_clients as c','c.id','=','l.client_id')
          ->select('l.*','d.name as development_name','g.plan_id as mapped_plan_id',
                   'c.name as buyer_name','c.phone as buyer_phone');
        if(!empty($d['q'])) {$like='%'.addcslashes($d['q'],'%_').'%';$query->where(function($w)use($like){$w->where('l.code','like',$like)->orWhere('l.block','like',$like)->orWhere('d.name','like',$like);});}
        if(!empty($d['development_id']))$query->where('l.development_id',$d['development_id']);
        if(!empty($d['status']))$query->where('l.status',$d['status']);
        if(($d['mapped']??'')==='yes')$query->whereNotNull('g.id');
        if(($d['mapped']??'')==='no')$query->whereNull('g.id');
        // ANDARMA_V40_INVENTORY_PAYMENT_TRAFFIC
        // Calculamos solo los terrenos de la página, sin N+1 consultas.
        $page = $query->orderByDesc('l.id')->paginate($d['per_page']??20);
        $lots = $page->getCollection();
        $ids = $lots->pluck('id')->all();
        if ($ids) {
            $plans = DB::table('andarma_lot_financing')
                ->whereIn('lot_id', $ids)->get()->keyBy('lot_id');
            $payments = DB::table('andarma_client_payments')
                ->whereIn('lot_id', $ids)
                ->select('lot_id','client_id','status','payment_type','applied_period','amount')
                ->get()->groupBy('lot_id');
            $today = CarbonImmutable::now('America/Mexico_City')->startOfDay();
            foreach ($lots as $lot) {
                $lot->overdue_months = null;
                $lot->payment_tone = 'neutral';
                $lot->payment_label = 'Sin plan';
                if (!$lot->client_id || !in_array($lot->status, ['sold','reserved'], true)) {
                    $lot->payment_label = 'No aplica';
                    continue;
                }
                $plan = $plans->get($lot->id);
                if (!$plan || (int)$plan->client_id !== (int)$lot->client_id) continue;
                $lotPayments = $payments->get($lot->id, collect())->filter(
                    fn($p) => (int)$p->client_id === (int)$lot->client_id && $p->status === 'applied'
                );
                // No mostrar al corriente sin conocer a qué meses pertenecen pagos antiguos.
                if ($lotPayments->contains(fn($p) => $p->payment_type === 'monthly' && !$p->applied_period)) {
                    $lot->payment_label = 'Revisar pagos';
                    continue;
                }
                $installment = (int)round((float)$plan->monthly_payment_amount * 100);
                $financed = (int)round(((float)$plan->sale_price - (float)$plan->discount_amount - (float)$plan->down_payment_amount) * 100);
                if ($financed <= 0 || $installment <= 0 || !$plan->first_payment_period) {
                    $lot->payment_label = 'Sin mensualidades';
                    continue;
                }
                $count = (int)ceil($financed / $installment);
                if ($count > 600) {
                    $lot->payment_label = 'Revisar plan';
                    continue;
                }
                $paidByMonth = [];
                foreach ($lotPayments as $payment) {
                    if ($payment->payment_type !== 'monthly' || !$payment->applied_period) continue;
                    $key = substr((string)$payment->applied_period, 0, 7);
                    $paidByMonth[$key] = ($paidByMonth[$key] ?? 0) + (int)round((float)$payment->amount * 100);
                }
                $start = CarbonImmutable::parse($plan->first_payment_period)->startOfMonth();
                $overdue = 0;
                for ($i=0; $i<$count; $i++) {
                    $month = $start->addMonthsNoOverflow($i);
                    $due = $month->day(min((int)$plan->payment_due_day, $month->daysInMonth));
                    if ($due->greaterThanOrEqualTo($today)) continue;
                    $dueCents = min($installment, $financed - $installment*$i);
                    if ($dueCents > ($paidByMonth[$month->format('Y-m')] ?? 0)) $overdue++;
                }
                $lot->overdue_months = $overdue;
                $lot->payment_tone = $overdue >= 4 ? 'red' : ($overdue >= 1 ? 'yellow' : 'green');
                $lot->payment_label = $overdue === 0 ? 'Al corriente' : ($overdue.' '.($overdue===1?'mes':'meses').' de atraso');
            }
        }
        return response()->json($page);
    }
    private function validatedLot(Request $r,?int $id=null): array {
        return $r->validate([
            'development_id'=>'required|integer|exists:andarma_developments,id',
            'code'=>['required','string','max:255',Rule::unique('andarma_lots','code')->where('development_id',$r->input('development_id'))->where('block',trim((string)$r->input('block','')))->ignore($id)],
            'block'=>'nullable|string|max:120',
            'area_m2'=>'nullable|numeric|min:0', 'front_m'=>'nullable|numeric|min:0','depth_m'=>'nullable|numeric|min:0',
            'price'=>'nullable|numeric|min:0',
            'status'=>['required',Rule::in(['available','reserved','sold','inactive'])],
            'description'=>'nullable|string|max:5000'
        ]);
    }
    public function createLot(Request $r) {
        $data=$this->validatedLot($r);
        $data['block']=trim((string)($data['block']??''));
        $id=DB::table('andarma_lots')->insertGetId([...$data,'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['data'=>DB::table('andarma_lots')->find($id)],201);
    }
    public function updateLot(Request $r,int $id) {
        $lot=DB::table('andarma_lots')->find($id);abort_unless($lot,404);
        $data=$this->validatedLot($r,$id);
        $data['block']=trim((string)($data['block']??''));
        if(property_exists($lot,'client_id') && $lot->client_id && ($data['status']==='available' || $data['development_id']!=$lot->development_id))
            return response()->json(['message'=>'La propiedad tiene un cliente vinculado. No se puede liberarla ni cambiar de desarrollo desde este formulario.'],409);
        if(DB::table('andarma_lot_geometries')->where('lot_id',$id)->exists() && $data['development_id']!=$lot->development_id)
            return response()->json(['message'=>'El lote está mapeado. Desvincula el polígono antes de cambiar el desarrollo.'],409);
        DB::table('andarma_lots')->where('id',$id)->update([...$data,'updated_at'=>now()]);
        return response()->json(['data'=>DB::table('andarma_lots')->find($id)]);
    }
    public function deleteLot(int $id) {
        $lot=DB::table('andarma_lots')->find($id);abort_unless($lot,404);
        if((property_exists($lot,'client_id') && $lot->client_id) || DB::table('andarma_lot_geometries')->where('lot_id',$id)->exists())
            return response()->json(['message'=>'No se puede eliminar: el lote tiene un comprador o un polígono.'],409);
        if(Schema::hasTable('andarma_client_payments') && DB::table('andarma_client_payments')->where('lot_id',$id)->exists())
            return response()->json(['message'=>'No se puede eliminar: existen pagos relacionados.'],409);
        DB::table('andarma_lots')->where('id',$id)->delete();return response()->json(['message'=>'Lote eliminado.']);
    }
// ANDARMA_V36_CONTROLLERS
    public function developmentSummary() {
        $items=DB::table('andarma_developments as d')->select('d.*')
          ->selectSub(DB::table('andarma_lots as l')->selectRaw('count(*)')->whereColumn('l.development_id','d.id'),'lots_count')
          ->selectSub(DB::table('andarma_lots as l')->selectRaw('count(*)')->whereColumn('l.development_id','d.id')->where('l.status','available'),'available_count')
          ->selectSub(DB::table('andarma_plans as p')->selectRaw('count(*)')->whereColumn('p.development_id','d.id'),'plans_count')
          ->selectSub(DB::table('andarma_leads as a')->selectRaw('count(*)')->whereColumn('a.development_id','d.id'),'leads_count')
          ->orderBy('d.sort_order')->orderBy('d.id')->get();
        return response()->json(['data'=>$items]);
    }
    public function deleteDevelopment(Request $r,int $id) {
        return DB::transaction(function()use($id){
            $row=DB::table('andarma_developments')->where('id',$id)->lockForUpdate()->first();
            abort_unless($row,404);
            if(DB::table('andarma_lots')->where('development_id',$id)->exists()
               ||DB::table('andarma_plans')->where('development_id',$id)->exists()
               ||DB::table('andarma_leads')->where('development_id',$id)->exists()) {
                return response()->json(['message'=>'No se puede eliminar un desarrollo con propiedades, mapas o prospectos asociados. Cambia su estado a Inactivo.'],409);
            }
            DB::table('andarma_developments')->where('id',$id)->delete();
            return response()->json(['message'=>'Desarrollo eliminado correctamente.']);
        });
    }
    public function renamePlan(Request $r,int $id) {
        $this->superAdmin($r);
        $data=$r->validate(['title'=>'required|string|min:3|max:180']);
        $title=trim($data['title']);
        if(mb_strlen($title)<3)throw ValidationException::withMessages(['title'=>'El nombre debe contener al menos tres caracteres.']);
        abort_unless(DB::table('andarma_plans')->where('id',$id)->exists(),404);
        DB::table('andarma_plans')->where('id',$id)->update(['title'=>$title,'updated_at'=>now()]);
        return response()->json(['data'=>$this->planRow($id),'message'=>'Nombre del mapa actualizado.']);
    }

    public function plans() {
        $plans=DB::table('andarma_plans as p')->join('andarma_developments as d','d.id','=','p.development_id')
          ->leftJoin('andarma_lot_geometries as g','g.plan_id','=','p.id')
          ->groupBy('p.id','p.development_id','p.title','p.image_path','p.is_active','p.created_at','p.updated_at','d.name')
          ->select('p.*','d.name as development_name')->selectRaw('COUNT(g.id) as geometry_count')->orderByDesc('p.id')->get();
        foreach($plans as $p)$p->image_url=Storage::disk('public')->url($p->image_path);
        return response()->json(['data'=>$plans]);
    }
    public function createPlan(Request $r) {
        $this->superAdmin($r);
        $data=$r->validate(['development_id'=>'required|integer|exists:andarma_developments,id','title'=>'required|string|max:180','image'=>'required|file|mimes:jpeg,jpg,png,webp|max:5120']);
        $file=$r->file('image');
        $dimensions=@getimagesize($file->getRealPath());
        if(!$dimensions || $dimensions[0]>9000 || $dimensions[1]>9000)
            throw ValidationException::withMessages(['image'=>'El plano debe ser una imagen válida de hasta 9000 × 9000 píxeles.']);
        $path=$file->store('andarma-plans','public');
        try {
            $id=DB::table('andarma_plans')->insertGetId(['development_id'=>$data['development_id'],'title'=>$data['title'],'image_path'=>$path,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        } catch(\Throwable $e) {Storage::disk('public')->delete($path);throw $e;}
        return response()->json(['data'=>$this->planRow($id)],201);
    }
    public function plan(int $id) {
        $p=$this->planRow($id);
        $polygons=DB::table('andarma_lot_geometries as g')->join('andarma_lots as l','l.id','=','g.lot_id')
            ->where('g.plan_id',$id)->select('g.id','g.lot_id','g.points','l.code','l.block','l.area_m2','l.price','l.status')->get();
        foreach($polygons as $poly)$poly->points=json_decode($poly->points,true)??[];
        $available=DB::table('andarma_lots as l')->leftJoin('andarma_lot_geometries as g','g.lot_id','=','l.id')
            ->where('l.development_id',$p->development_id)->whereNull('g.id')
            ->select('l.id','l.code','l.block','l.status','l.area_m2')->orderBy('l.code')->limit(2000)->get();
        return response()->json(['data'=>$p,'polygons'=>$polygons,'available_lots'=>$available]);
    }
    private function points(Request $r): array {
        $data=$r->validate(['lot_id'=>'required|integer|exists:andarma_lots,id','points'=>'required|array|min:3|max:40','points.*.x'=>'required|numeric|between:0,1000','points.*.y'=>'required|numeric|between:0,1000']);
        $p=$data['points'];
        $area=0.0;
        for($i=0;$i<count($p);$i++){$j=($i+1)%count($p);$area+=(float)$p[$i]['x']*(float)$p[$j]['y']-(float)$p[$j]['x']*(float)$p[$i]['y'];}
        if(abs($area)<10)throw ValidationException::withMessages(['points'=>'El polígono es demasiado pequeño o sus puntos están alineados.']);
        return $data;
    }
    public function savePolygon(Request $r,int $id) {
        $this->superAdmin($r);
        $data=$this->points($r);
        $p=$this->planRow($id);
        return DB::transaction(function()use($data,$p){
            $lot=DB::table('andarma_lots')->where('id',$data['lot_id'])->lockForUpdate()->first();abort_unless($lot,404);
            if((int)$lot->development_id!==(int)$p->development_id)return response()->json(['message'=>'El terreno pertenece a otro desarrollo.'],422);
            if(DB::table('andarma_lot_geometries')->where('lot_id',$lot->id)->exists())return response()->json(['message'=>'Este terreno ya tiene un polígono asignado.'],409);
            $id=DB::table('andarma_lot_geometries')->insertGetId(['plan_id'=>$p->id,'lot_id'=>$lot->id,'points'=>json_encode($data['points']),'created_at'=>now(),'updated_at'=>now()]);
            return response()->json(['id'=>$id,'message'=>'Polígono guardado.'],201);
        });
    }
    public function updatePolygon(Request $r,int $id,int $geometryId) {
        $this->superAdmin($r);
        $data=$r->validate(['points'=>'required|array|min:3|max:40','points.*.x'=>'required|numeric|between:0,1000','points.*.y'=>'required|numeric|between:0,1000']);
        // Reutiliza las validaciones geométricas sin cambiar el lote asociado.
        $geom=DB::table('andarma_lot_geometries')->where('plan_id',$id)->where('id',$geometryId)->first();abort_unless($geom,404);
        $r->merge(['lot_id'=>$geom->lot_id]);$this->points($r);
        DB::table('andarma_lot_geometries')->where('id',$geometryId)->update(['points'=>json_encode($data['points']),'updated_at'=>now()]);
        return response()->json(['message'=>'Polígono actualizado.']);
    }
    public function deletePolygon(Request $r,int $id,int $geometryId) {
        $this->superAdmin($r);
        $deleted=DB::table('andarma_lot_geometries')->where('plan_id',$id)->where('id',$geometryId)->delete();abort_unless($deleted,404);
        return response()->json(['message'=>'Se eliminó únicamente el trazo visual. El lote, el cliente y sus pagos permanecen intactos.']);
    }
    public function deletePlan(Request $r,int $id) {
        $this->superAdmin($r);
        $p=DB::table('andarma_plans')->where('id',$id)->first();abort_unless($p,404);
        if(DB::table('andarma_lot_geometries')->where('plan_id',$id)->exists())return response()->json(['message'=>'Primero desvincula los polígonos de este plano.'],409);
        DB::table('andarma_plans')->where('id',$id)->delete();Storage::disk('public')->delete($p->image_path);
        return response()->json(['message'=>'Plano eliminado.']);
    }
}
