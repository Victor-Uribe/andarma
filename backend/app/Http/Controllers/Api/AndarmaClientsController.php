<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AndarmaClientsController extends Controller {
 private function validated(Request $request):array {
  return $request->validate([
   'name'=>'required|string|max:255','phone'=>'required|string|max:50',
   'email'=>'nullable|email|max:255','alternate_phone'=>'nullable|string|max:50',
   'notes'=>'nullable|string|max:5000','status'=>['required',Rule::in(['active','inactive'])]
  ]);
 }
 public function index(Request $request) {
  $term=trim((string)$request->query('q',''));
  $perPage=min(50,max(1,(int)$request->query('per_page',15)));
  $query=DB::table('andarma_clients as c')->select('c.*')
   ->selectSub(DB::table('andarma_lots')->selectRaw('count(*)')->whereColumn('client_id','c.id'),'properties_count')
   ->selectSub(DB::table('andarma_client_payments')->selectRaw('count(*)')->whereColumn('client_id','c.id')->where('status','applied'),'payments_count');
  if($term!==''){$like='%'.str_replace(['%','_'],['\\%','\\_'],$term).'%';$query->where(function($q)use($like){$q->where('c.name','like',$like)->orWhere('c.phone','like',$like)->orWhere('c.email','like',$like)
       ->orWhereExists(function ($lots) use ($like) {
         $lots->selectRaw('1')->from('andarma_lots as l')
           ->join('andarma_developments as d', 'd.id', '=', 'l.development_id')
           ->whereColumn('l.client_id', 'c.id')
           ->where(function ($w) use ($like) {
             $w->where('l.code', 'like', $like)
               ->orWhere('l.block', 'like', $like)
               ->orWhere('d.name', 'like', $like);
           });
       });});}
  return response()->json($query->orderByDesc('c.id')->paginate($perPage));
 }
 public function store(Request $request){$data=$this->validated($request);$id=DB::table('andarma_clients')->insertGetId([...$data,'created_at'=>now(),'updated_at'=>now()]);return response()->json(['data'=>DB::table('andarma_clients')->find($id)],201);}
 public function show(int $id){$c=DB::table('andarma_clients')->find($id);abort_unless($c,404);
  $lots=DB::table('andarma_lots as l')->join('andarma_developments as d','d.id','=','l.development_id')->where('l.client_id',$id)->select('l.id','l.code','l.block','l.area_m2','l.price','l.status','l.development_id','d.name as development_name')->orderBy('l.id')->get();
  $payments=DB::table('andarma_client_payments')->where('client_id',$id)->orderByDesc('paid_at')->orderByDesc('id')->get();
  $total=DB::table('andarma_client_payments')->where('client_id',$id)->where('status','applied')->sum('amount');
  return response()->json(['data'=>$c,'properties'=>$lots,'payments'=>$payments,'paid_total'=>(float)$total,'payment_capture_enabled'=>false]);
 }
 public function update(Request $request,int $id){abort_unless(DB::table('andarma_clients')->where('id',$id)->exists(),404);$data=$this->validated($request);DB::table('andarma_clients')->where('id',$id)->update([...$data,'updated_at'=>now()]);return response()->json(['data'=>DB::table('andarma_clients')->find($id)]);}
 public function destroy(int $id){return DB::transaction(function()use($id){$c=DB::table('andarma_clients')->where('id',$id)->lockForUpdate()->first();abort_unless($c,404);
  if(DB::table('andarma_lots')->where('client_id',$id)->exists()||DB::table('andarma_client_payments')->where('client_id',$id)->exists())return response()->json(['message'=>'No se puede eliminar un cliente con propiedades o pagos. Desactívalo para conservar el historial.'],409);
  DB::table('andarma_clients')->where('id',$id)->delete();return response()->json(['message'=>'Cliente eliminado.']);});}
 public function convert(Request $request,int $leadId){$request->validate([]);
  return DB::transaction(function()use($leadId){$lead=DB::table('andarma_leads')->where('id',$leadId)->lockForUpdate()->first();abort_unless($lead,404);
   $existing=DB::table('andarma_clients')->where('lead_id',$leadId)->first();if($existing)return response()->json(['data'=>$existing,'already_converted'=>true]);
   if(!$lead->phone)return response()->json(['message'=>'Agrega el teléfono al prospecto antes de convertirlo.'],422);
   $id=DB::table('andarma_clients')->insertGetId(['lead_id'=>$lead->id,'name'=>$lead->name,'phone'=>$lead->phone,'email'=>$lead->email,'notes'=>$lead->notes,'status'=>'active','created_at'=>now(),'updated_at'=>now()]);
   DB::table('andarma_leads')->where('id',$leadId)->update(['status'=>'closed','updated_at'=>now()]);
   return response()->json(['data'=>DB::table('andarma_clients')->find($id),'already_converted'=>false],201);
  });
 }
 public function availableLots(){return response()->json(['data'=>DB::table('andarma_lots as l')->join('andarma_developments as d','d.id','=','l.development_id')->whereNull('l.client_id')->whereIn('l.status',['available','reserved'])->select('l.id','l.code','l.status','l.price','d.name as development_name')->orderBy('d.name')->orderBy('l.code')->limit(500)->get()]);}
 public function assignLot(Request $request,int $id){$data=$request->validate(['lot_id'=>'required|integer|exists:andarma_lots,id','status'=>['required',Rule::in(['reserved','sold'])]]);
  return DB::transaction(function()use($id,$data){abort_unless(DB::table('andarma_clients')->where('id',$id)->exists(),404);
   $lot=DB::table('andarma_lots')->where('id',$data['lot_id'])->lockForUpdate()->first();abort_unless($lot,404);
   if($lot->client_id!==null||!in_array($lot->status,['available','reserved'],true))return response()->json(['message'=>'Esta propiedad ya fue asignada o no está disponible.'],409);
   DB::table('andarma_lots')->where('id',$lot->id)->update(['client_id'=>$id,'status'=>$data['status'],'updated_at'=>now()]);
   return response()->json(['message'=>'Propiedad asignada correctamente.']);
  });
 }
 public function releaseLot(int $id,int $lotId){return DB::transaction(function()use($id,$lotId){$lot=DB::table('andarma_lots')->where('id',$lotId)->lockForUpdate()->first();abort_unless($lot&& (int)$lot->client_id===$id,404);
  if(DB::table('andarma_client_payments')->where('lot_id',$lotId)->exists())return response()->json(['message'=>'No se puede liberar una propiedad con pagos registrados.'],409);
  DB::table('andarma_lots')->where('id',$lotId)->update(['client_id'=>null,'status'=>'available','updated_at'=>now()]);return response()->json(['message'=>'Propiedad liberada.']);});}
}
