<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AndarmaAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AndarmaSettingsController extends Controller {
    private const FIELDS=[
        'business_name'=>'nullable|string|max:150',
        'phone'=>'nullable|string|max:30',
        'whatsapp'=>'nullable|string|max:30',
        'email'=>'nullable|email|max:180',
        'address'=>'nullable|string|max:255',
        'neighborhood'=>'nullable|string|max:120',
        'city'=>'nullable|string|max:120',
        'state'=>'nullable|string|max:120',
        'postal_code'=>'nullable|string|max:12',
        'maps_url'=>'nullable|url|max:1500',
        'facebook_url'=>'nullable|url|max:500',
        'instagram_url'=>'nullable|url|max:500',
        'tiktok_url'=>'nullable|url|max:500',
        'youtube_url'=>'nullable|url|max:500',
        'website_url'=>'nullable|url|max:500',
        'hours'=>'nullable|string|max:200',
        'whatsapp_message'=>'nullable|string|max:350',
    ];
    private function values(): array {
        $values=array_fill_keys(array_keys(self::FIELDS),'');
        $stored=DB::table('andarma_settings')->pluck('value','key');
        foreach ($values as $key=>$_) $values[$key]=(string)($stored[$key]??'');
        return $values;
    }
    public function index(Request $r) {
        abort_unless(AndarmaAccess::can($r->user(),'settings.view'),403);
        return response()->json(['data'=>$this->values()]);
    }
    public function update(Request $r) {
        abort_unless(AndarmaAccess::can($r->user(),'settings.edit'),403);
        $rules=[];foreach(self::FIELDS as $k=>$rule)$rules[$k]= 'present|'.$rule;
        $v=Validator::make($r->all(),$rules)->validate();
        foreach (['phone','whatsapp'] as $key){
            if (!empty($v[$key])) {
                $digits=preg_replace('/\\D+/','',(string)$v[$key]);
                if (strlen($digits)<10||strlen($digits)>15)
                    throw ValidationException::withMessages([$key=>'Captura entre 10 y 15 dígitos. Puedes incluir +52.']);
            }
        }
        DB::transaction(function()use($v){
            foreach ($v as $key=>$value){
                DB::table('andarma_settings')->updateOrInsert(['key'=>$key],['value'=>$value===null?'':trim((string)$value),'updated_at'=>now()]);
            }
        });
        return response()->json(['message'=>'Configuración guardada correctamente.','data'=>$this->values()]);
    }
    public function publicSettings(){
        // Únicamente datos empresariales públicos; ninguna credencial o token.
        return response()->json(['data'=>$this->values()]);
    }
}
