<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AndarmaClientsController extends Controller {
 // ANDARMA_FASE20_AUDITORIA_CLIENTES: registro dentro de la transaccion del cambio.
 private function auditChange(string $action, ?int $clientId, ?int $lotId, array $changes): void {
  DB::table('andarma_client_audit')->insert([
   'actor_user_id'=>auth()->id(), 'action'=>$action, 'client_id'=>$clientId,
   'lot_id'=>$lotId, 'changes'=>json_encode($changes, JSON_UNESCAPED_UNICODE),
   'created_at'=>now(),
  ]);
 }
 // Solo responsables con permisos de modificar clientes pueden consultar la bitacora.
 public function auditIndex(Request $request) {
  abort_unless(\App\Support\AndarmaAccess::can($request->user(),'clients.edit'),403,
   'No tienes permiso para consultar la auditoria de clientes.');
  $d=$request->validate([
   'action'=>['nullable',Rule::in(['client.updated','lot.assigned','lot.released'])],
   'client_id'=>'nullable|integer|min:1', 'lot_id'=>'nullable|integer|min:1',
   'from'=>'nullable|date_format:Y-m-d', 'to'=>'nullable|date_format:Y-m-d',
   'per_page'=>'nullable|integer|min:1|max:100',
  ]);
  if(!empty($d['from']) && !empty($d['to']) && $d['from']>$d['to'])
   throw ValidationException::withMessages(['to'=>'La fecha final debe ser posterior a la inicial.']);
  $q=DB::table('andarma_client_audit as a')
   ->leftJoin('users as u','u.id','=','a.actor_user_id')
   ->select('a.*','u.name as actor_name');
  if(!empty($d['action']))$q->where('a.action',$d['action']);
  if(!empty($d['client_id']))$q->where('a.client_id',$d['client_id']);
  if(!empty($d['lot_id']))$q->where('a.lot_id',$d['lot_id']);
  if(!empty($d['from']))$q->whereDate('a.created_at','>=',$d['from']);
  if(!empty($d['to']))$q->whereDate('a.created_at','<=',$d['to']);
  return response()->json($q->orderByDesc('a.id')->paginate($d['per_page']??20));
 }

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
 public function update(Request $request,int $id){
  $data=$this->validated($request);
  return DB::transaction(function()use($request,$id,$data){
   $before=DB::table('andarma_clients')->where('id',$id)->lockForUpdate()->first();abort_unless($before,404);
   $changes=[];
   // Evitar copiar telefonos, correos o notas a una bitacora adicional.
   foreach(['name','phone','email','alternate_phone','notes','status'] as $field){
    if((string)($before->$field??'')!==(string)($data[$field]??''))$changes[]=$field;
   }
   if($changes){
    DB::table('andarma_clients')->where('id',$id)->update([...$data,'updated_at'=>now()]);
    $this->auditChange('client.updated',$id,null,['fields'=>$changes]);
   }
   return response()->json(['data'=>DB::table('andarma_clients')->find($id)]);
  });
 }
 public function destroy(int $id){return DB::transaction(function()use($id){$c=DB::table('andarma_clients')->where('id',$id)->lockForUpdate()->first();abort_unless($c,404);
  // ANDARMA_FASE2_INTEGRIDAD_V1: conservar contratos y trazabilidad histórica.
  if(DB::table('andarma_lots')->where('client_id',$id)->exists()
    || DB::table('andarma_client_payments')->where('client_id',$id)->exists()
    || DB::table('andarma_lot_financing')->where('client_id',$id)->exists()
    || DB::table('andarma_client_audit')->where('client_id',$id)->exists())
      return response()->json(['message'=>'El cliente tiene propiedades, contratos, pagos o historial. Desactívalo para conservar su expediente.'],409);
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
  return DB::transaction(function()use($id,$data){
   // ANDARMA_FASE2_INTEGRIDAD_V1: cliente activo y bloqueo del terreno durante la asignación.
   $client=DB::table('andarma_clients')->where('id',$id)->lockForUpdate()->first(['id','status']);
   abort_unless($client,404);
   if($client->status!=='active')return response()->json(['message'=>'No se pueden asignar propiedades a un cliente inactivo.'],409);
   $lot=DB::table('andarma_lots')->where('id',$data['lot_id'])->lockForUpdate()->first();abort_unless($lot,404);
   if($lot->client_id!==null||!in_array($lot->status,['available','reserved'],true))return response()->json(['message'=>'Esta propiedad ya fue asignada o no está disponible.'],409);
   if(DB::table('andarma_lot_financing')->where('lot_id',$lot->id)->exists()
      || DB::table('andarma_client_payments')->where('lot_id',$lot->id)->exists())
      return response()->json(['message'=>'El terreno conserva un financiamiento o pagos anteriores. Requiere conciliación antes de reasignar.'],409);
   DB::table('andarma_lots')->where('id',$lot->id)->update(['client_id'=>$id,'status'=>$data['status'],'updated_at'=>now()]);
   $this->auditChange('lot.assigned',$id,(int)$lot->id,[
    'previous_client_id'=>$lot->client_id,'new_client_id'=>$id,
    'previous_status'=>$lot->status,'new_status'=>$data['status'],
   ]);
   return response()->json(['message'=>'Propiedad asignada correctamente.']);
  });
 }
 public function releaseLot(int $id,int $lotId){return DB::transaction(function()use($id,$lotId){$lot=DB::table('andarma_lots')->where('id',$lotId)->lockForUpdate()->first();abort_unless($lot&& (int)$lot->client_id===$id,404);
  // ANDARMA_FASE1_PREPROD_V1: conservar contrato de financiamiento aunque aun no existan pagos.
  if(DB::table('andarma_lot_financing')->where('lot_id',$lotId)->exists())return response()->json(['message'=>'No se puede liberar una propiedad con financiamiento registrado.'],409);
  if(DB::table('andarma_client_payments')->where('lot_id',$lotId)->exists())return response()->json(['message'=>'No se puede liberar una propiedad con pagos registrados.'],409);
  DB::table('andarma_lots')->where('id',$lotId)->update(['client_id'=>null,'status'=>'available','updated_at'=>now()]);
  $this->auditChange('lot.released',$id,$lotId,[
   'previous_client_id'=>$id,'new_client_id'=>null,
   'previous_status'=>$lot->status,'new_status'=>'available',
  ]);return response()->json(['message'=>'Propiedad liberada.']);});}
}
