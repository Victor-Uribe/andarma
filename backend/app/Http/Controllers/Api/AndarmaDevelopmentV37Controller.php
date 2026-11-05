<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AndarmaDevelopmentV37Controller extends Controller
{
    private function validateData(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => ['required','string','min:2','max:255'],
            'location' => ['nullable','string','max:255'],
            'description' => ['nullable','string','max:10000'],
            'status' => ['required',Rule::in(['active','upcoming','inactive'])],
            'photo' => ['nullable','file','mimes:webp','max:450'],
            'remove_image' => ['nullable','boolean'],
        ]);
    }

    private function validatedPhoto(Request $request): void
    {
        if (!$request->hasFile('photo')) return;
        $photo = $request->file('photo');
        $size = @getimagesize($photo->getRealPath());
        if (!$size || ($size[0] / max(1, $size[1])) < 1.7 || ($size[0] / max(1, $size[1])) > 1.85 || $size[0]>2000 || $size[1]>1200) {
            throw ValidationException::withMessages(['photo'=>'La fotografía debe ser WebP horizontal 16:9 y máximo 2000 × 1200 píxeles.']);
        }
    }

    private function slugFor(string $name, ?int $id = null): string
    {
        $base = Str::slug($name) ?: 'desarrollo';
        $candidate = $base;
        if ($id !== null) return $base.'-'.$id;
        while (DB::table('andarma_developments')->where('slug',$candidate)->exists()) {
            $candidate = $base.'-'.Str::lower(Str::random(5));
        }
        return $candidate;
    }

    private function discardOldOwnedImage(?string $url): void
    {
        if (!$url) return;
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $marker = '/storage/andarma-developments/';
        $pos = strpos($path, $marker);
        if ($pos === false) return; // Nunca eliminar imágenes externas ni de otros módulos.
        $storagePath = 'andarma-developments/'.basename($path);
        if (DB::table('andarma_developments')->where('image_url',$url)->exists()) return;
        Storage::disk('public')->delete($storagePath);
    }

    public function create(Request $request)
    {
        $data=$this->validateData($request);
        $this->validatedPhoto($request);
        $uploaded=null;
        try {
            if ($request->hasFile('photo')) $uploaded=$request->file('photo')->store('andarma-developments','public');
            $slug=$this->slugFor($data['name']);
            $id=DB::table('andarma_developments')->insertGetId([
                'name'=>trim($data['name']),'slug'=>$slug,
                'description'=>$data['description']??null,'location'=>$data['location']??null,
                'image_url'=>$uploaded ? Storage::disk('public')->url($uploaded) : null,
                'status'=>$data['status'],'is_featured'=>false,
                'sort_order'=>DB::table('andarma_developments')->max('sort_order')+1,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        } catch (\Throwable $e) {
            if ($uploaded) Storage::disk('public')->delete($uploaded);
            throw $e;
        }
        return response()->json(['data'=>DB::table('andarma_developments')->find($id)],201);
    }

    public function update(Request $request,int $id)
    {
        $data=$this->validateData($request,$id);
        $this->validatedPhoto($request);
        $row=DB::table('andarma_developments')->where('id',$id)->first();
        abort_unless($row,404);
        $uploaded=null;
        try {
            if ($request->hasFile('photo')) $uploaded=$request->file('photo')->store('andarma-developments','public');
            $remove=filter_var($data['remove_image']??false,FILTER_VALIDATE_BOOLEAN);
            $newUrl=$uploaded ? Storage::disk('public')->url($uploaded) : ($remove ? null : $row->image_url);
            // La URL pública no cambia al renombrar un desarrollo existente.
            $slug=$row->slug ?: $this->slugFor($data['name'],$id);
            DB::table('andarma_developments')->where('id',$id)->update([
                'name'=>trim($data['name']),'slug'=>$slug,
                'description'=>$data['description']??null,'location'=>$data['location']??null,
                'image_url'=>$newUrl,'status'=>$data['status'],'updated_at'=>now(),
            ]);
        } catch (\Throwable $e) {
            if ($uploaded) Storage::disk('public')->delete($uploaded);
            throw $e;
        }
        if (($uploaded || $remove) && $row->image_url && $row->image_url!==$newUrl) $this->discardOldOwnedImage($row->image_url);
        return response()->json(['data'=>DB::table('andarma_developments')->find($id)]);
    }

    public function reorder(Request $request)
    {
        $data=$request->validate(['ids'=>['required','array','min:1','max:2000'],'ids.*'=>['required','integer','distinct']]);
        $ids=array_map('intval',$data['ids']);
        return DB::transaction(function () use ($ids) {
            $existing=DB::table('andarma_developments')->orderBy('id')->lockForUpdate()->pluck('id')->map(fn($v)=>(int)$v)->all();
            $check=$ids;
            sort($existing);sort($check);
            if ($check!==$existing) return response()->json(['message'=>'La lista de desarrollos cambió. Actualiza el listado antes de guardar el nuevo orden.'],409);
            foreach($ids as $index=>$id) DB::table('andarma_developments')->where('id',$id)->update(['sort_order'=>$index+1]);
            return response()->json(['message'=>'Orden guardado correctamente.']);
        });
    }
}
