<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class AndarmaAdminController extends Controller {
 private const TABLES=['banners'=>'andarma_banners','developments'=>'andarma_developments','lots'=>'andarma_lots','testimonials'=>'andarma_testimonials','leads'=>'andarma_leads'];
 private function table(string $resource):string{abort_unless(isset(self::TABLES[$resource]),404);return self::TABLES[$resource];}
 public function dashboard(){return response()->json(['data'=>['developments'=>DB::table('andarma_developments')->count(),'lots'=>DB::table('andarma_lots')->count(),'available_lots'=>DB::table('andarma_lots')->where('status','available')->count(),'leads'=>DB::table('andarma_leads')->count(),'banners'=>DB::table('andarma_banners')->count(),'testimonials'=>DB::table('andarma_testimonials')->count()]]);}
 public function index(string $resource){
  $table=$this->table($resource);
  if($resource==='leads'){
    $leads=DB::table('andarma_leads as l')
      ->leftJoin('andarma_clients as c','c.lead_id','=','l.id')
      ->select('l.*','c.id as converted_client_id')
      ->orderByDesc('l.id')->limit(500)->get();
    return response()->json(['data'=>$leads]);
  }
  return response()->json(['data'=>DB::table($table)->orderByDesc('id')->limit(500)->get()]);
 }
 private function validated(Request $request,string $resource,?int $id=null):array{
  return match($resource){
   'banners'=>$request->validate(['title'=>'required|string|max:255','subtitle'=>'nullable|string|max:3000','image_url'=>'nullable|string|max:2048','button_text'=>'nullable|string|max:255','button_url'=>'nullable|string|max:1024','sort_order'=>'nullable|integer|min:0','is_active'=>'boolean']),
   'developments'=>$request->validate(['name'=>'required|string|max:255','slug'=>['nullable','string','max:255',Rule::unique('andarma_developments','slug')->ignore($id)],'description'=>'nullable|string|max:10000','location'=>'nullable|string|max:255','image_url'=>'nullable|string|max:2048','status'=>['required',Rule::in(['active','upcoming','inactive'])],'is_featured'=>'boolean']),
   'lots'=>$request->validate(['development_id'=>'required|integer|exists:andarma_developments,id','code'=>['required','string','max:255',Rule::unique('andarma_lots','code')->where('development_id',$request->input('development_id'))->ignore($id)],'area_m2'=>'nullable|numeric|min:0','price'=>'nullable|numeric|min:0','status'=>['required',Rule::in(['available','reserved','sold','inactive'])],'description'=>'nullable|string|max:5000','latitude'=>'nullable|numeric|between:-90,90','longitude'=>'nullable|numeric|between:-180,180']),
   'testimonials'=>$request->validate(['name'=>'required|string|max:255','role'=>'nullable|string|max:255','quote'=>'required|string|max:4000','image_url'=>'nullable|string|max:2048','rating'=>'required|integer|between:1,5','sort_order'=>'nullable|integer|min:0','is_active'=>'boolean']),
   'leads'=>$request->validate(['name'=>'required|string|max:255','email'=>'nullable|email|max:255','phone'=>'nullable|string|max:50','development_id'=>'nullable|integer|exists:andarma_developments,id','message'=>'nullable|string|max:5000','status'=>['required',Rule::in(['new','contacted','qualified','closed'])],'notes'=>'nullable|string|max:5000']),
   default=>abort(404)
  };
 }
 public function store(Request $request,string $resource){$table=$this->table($resource);$data=$this->validated($request,$resource);if($resource==='developments')$data['slug']=$data['slug']?:Str::slug($data['name']).'-'.Str::lower(Str::random(5));$id=DB::table($table)->insertGetId([...$data,'created_at'=>now(),'updated_at'=>now()]);return response()->json(['data'=>DB::table($table)->find($id)],201);}
 public function update(Request $request,string $resource,int $id){$table=$this->table($resource);abort_unless(DB::table($table)->where('id',$id)->exists(),404);if($resource==='leads' && DB::table('andarma_clients')->where('lead_id',$id)->exists())return response()->json(['message'=>'El prospecto ya fue convertido a cliente. Edita su expediente de cliente.'],409);$data=$this->validated($request,$resource,$id);if($resource==='developments')$data['slug']=$data['slug']?:Str::slug($data['name']).'-'.$id;DB::table($table)->where('id',$id)->update([...$data,'updated_at'=>now()]);return response()->json(['data'=>DB::table($table)->find($id)]);}
 public function destroy(string $resource,int $id){
  $table=$this->table($resource);
  if($resource==='leads' && DB::table('andarma_clients')->where('lead_id',$id)->exists()){
    return response()->json(['message'=>'No se puede eliminar un prospecto convertido a cliente. Conserva su historial.'],409);
  }
  try{$deleted=DB::table($table)->where('id',$id)->delete();}
  catch(\Illuminate\Database\QueryException $e){return response()->json(['message'=>'No se puede eliminar: existen registros asociados.'],409);}
  abort_unless($deleted,404);
  return response()->json(['message'=>'Registro eliminado.']);
 }
 public function media(){return response()->json(['data'=>DB::table('andarma_media')->orderByDesc('id')->get()->map(fn($m)=>[...((array)$m),'url'=>Storage::disk('public')->url($m->path)])]);}
 public function upload(Request $request){$request->validate(['file'=>'required|file|mimes:jpg,jpeg,png,webp,mp4|max:20480']);$file=$request->file('file');$path=$file->store('andarma-media','public');$id=DB::table('andarma_media')->insertGetId(['original_name'=>$file->getClientOriginalName(),'path'=>$path,'mime_type'=>$file->getMimeType(),'size'=>$file->getSize(),'created_at'=>now(),'updated_at'=>now()]);return response()->json(['id'=>$id,'url'=>Storage::disk('public')->url($path)],201);}
 public function deleteMedia(int $id){$media=DB::table('andarma_media')->find($id);abort_unless($media,404);Storage::disk('public')->delete($media->path);DB::table('andarma_media')->where('id',$id)->delete();return response()->json(['message'=>'Archivo eliminado.']);}
 public function publicBanners(){return response()->json(['data'=>DB::table('andarma_banners')->where('is_active',1)->orderBy('sort_order')->get()]);}
 public function publicDevelopments(){return response()->json(['data'=>DB::table('andarma_developments')->where('status','active')->orderBy('sort_order')->orderBy('id')->get()]);}
 public function publicLots(Request $r){$query=DB::table('andarma_lots')->where('status','available');if($r->filled('development_id'))$query->where('development_id',$r->query('development_id'));return response()->json(['data'=>$query->get()]);}
 public function publicTestimonials(){return response()->json(['data'=>DB::table('andarma_testimonials')->where('is_active',1)->orderBy('sort_order')->get()]);}
 public function publicLead(Request $r){$d=$r->validate(['name'=>'required|string|max:255','email'=>'nullable|email|max:255','phone'=>'nullable|string|max:50','development_id'=>'nullable|exists:andarma_developments,id','message'=>'nullable|string|max:5000']);DB::table('andarma_leads')->insert([...$d,'status'=>'new','created_at'=>now(),'updated_at'=>now()]);return response()->json(['message'=>'Solicitud recibida.'],201);}
}
