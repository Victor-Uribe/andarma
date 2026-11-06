<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AndarmaAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** ANDARMA_FASE3A_GALERIA_HERO: galeria independiente de la fotografia principal. */
class AndarmaDevelopmentGalleryController extends Controller
{
    private function authorizeEdit(Request $request): void
    {
        abort_unless(AndarmaAccess::can($request->user(), 'developments.edit'), 403);
    }
    // ANDARMA_FASE3D_GALERIA_UNIFICADA: mantener la foto original local
    // si todavía no está dentro de la galería. Seguro ante llamadas repetidas.
    private function importExistingMain(int $id): void
    {
        DB::transaction(function () use ($id) {
            $development=DB::table('andarma_developments')->where('id',$id)->lockForUpdate()->first();
            if (!$development || !$development->image_url) return;
            $urlPath=parse_url($development->image_url,PHP_URL_PATH) ?: '';
            $marker='/storage/';$position=strpos($urlPath,$marker);
            if ($position===false) return; // No importar URL externas ni ajenas al storage.
            $path=substr($urlPath,$position+strlen($marker));
            if (!str_starts_with($path,'andarma-developments/') && !str_starts_with($path,'andarma-development-gallery/')) return;
            if (!Storage::disk('public')->exists($path)) return;
            $already=DB::table('andarma_development_gallery')->where('development_id',$id)->where('image_path',$path)->exists();
            if ($already) return;
            // Importar la portada antigua aunque ya existan fotos del hero.
            // Las imágenes elegidas desde galería ya se encuentran vinculadas y no se duplican.
            DB::table('andarma_development_gallery')->insert([
                'development_id'=>$id,'image_path'=>$path,'sort_order'=>0,'created_at'=>now(),'updated_at'=>now(),
            ]);
        });
    }
    private function rows(int $id)
    {
        return DB::table('andarma_development_gallery')->where('development_id', $id)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'development_id' => $r->development_id,
                'url' => Storage::disk('public')->url($r->image_path),
                'sort_order' => $r->sort_order,
            ]);
    }
    private function syncMain(int $id): void
    {
        $main=DB::table('andarma_development_gallery')->where('development_id',$id)->orderBy('sort_order')->orderBy('id')->first();
        DB::table('andarma_developments')->where('id',$id)->update([
            'image_url'=>$main?Storage::disk('public')->url($main->image_path):null,
            'updated_at'=>now(),
        ]);
    }
    private function validatePhoto(Request $request): void
    {
        $request->validate(['photo'=>['required','file','mimes:webp','max:450']]);
        $photo=$request->file('photo');
        $size=@getimagesize($photo->getRealPath());
        $ratio=$size?$size[0]/max(1,$size[1]):0;
        if (!$size || $size[0]<760 || $size[1]<427 || $size[0]>2000 || $size[1]>1200 || $ratio<1.70 || $ratio>1.85) {
            throw ValidationException::withMessages(['photo'=>'La fotografía debe ser WebP horizontal 16:9 de máximo 450 KB.']);
        }
    }
    private function safeDeletePhoto(string $path): void
    {
        if (!str_starts_with($path,'andarma-development-gallery/') && !str_starts_with($path,'andarma-developments/')) return;
        if (DB::table('andarma_development_gallery')->where('image_path',$path)->exists()) return;
        $url=Storage::disk('public')->url($path);
        if (DB::table('andarma_developments')->where('image_url',$url)->exists()) return;
        Storage::disk('public')->delete($path);
    }
    public function index(Request $request, int $id)
    {
        abort_unless(DB::table('andarma_developments')->where('id', $id)->exists(), 404);
        $this->importExistingMain($id);
        return response()->json(['data' => $this->rows($id)]);
    }
    public function publicIndex(Request $request)
    {
        $v = $request->validate(['development_id' => 'required|integer|min:1']);
        $d = DB::table('andarma_developments')->where('id', $v['development_id'])->where('status', 'active')->first();
        abort_unless($d, 404);
        return response()->json(['data' => $this->rows((int) $d->id)]);
    }
    public function store(Request $request, int $id)
    {
        $this->authorizeEdit($request);
        abort_unless(DB::table('andarma_developments')->where('id',$id)->exists(),404);
        $this->validatePhoto($request);
        $this->importExistingMain($id);
        $path=$request->file('photo')->store('andarma-development-gallery/'.$id,'public');
        try {
            DB::transaction(function () use ($id,$path) {
                $order=(int) DB::table('andarma_development_gallery')->where('development_id',$id)->max('sort_order');
                DB::table('andarma_development_gallery')->insert([
                    'development_id'=>$id,'image_path'=>$path,'sort_order'=>$order+1,'created_at'=>now(),'updated_at'=>now(),
                ]);
                $this->syncMain($id);
            });
        } catch (\Throwable $e) {Storage::disk('public')->delete($path);throw $e;}
        return response()->json(['data'=>$this->rows($id)],201);
    }
    public function primary(Request $request,int $id,int $photoId)
    {
        $this->authorizeEdit($request);
        $this->importExistingMain($id);
        $all=DB::table('andarma_development_gallery')->where('development_id',$id)->orderBy('sort_order')->orderBy('id')->pluck('id')->map(fn($v)=>(int)$v)->all();
        abort_unless(in_array($photoId,$all,true),404);
        $order=array_values(array_unique([$photoId,...$all]));
        DB::transaction(function () use ($id,$order) {
            foreach ($order as $position=>$itemId) DB::table('andarma_development_gallery')->where('development_id',$id)->where('id',$itemId)
                ->update(['sort_order'=>$position+1,'updated_at'=>now()]);
            $this->syncMain($id);
        });
        return response()->json(['data'=>$this->rows($id)]);
    }
    public function replace(Request $request,int $id,int $photoId)
    {
        $this->authorizeEdit($request);
        $this->validatePhoto($request);
        $this->importExistingMain($id);
        $previous=DB::table('andarma_development_gallery')->where('development_id',$id)->where('id',$photoId)->first();
        abort_unless($previous,404);
        $path=$request->file('photo')->store('andarma-development-gallery/'.$id,'public');
        try {
            DB::transaction(function () use ($id,$photoId,$path) {
                DB::table('andarma_development_gallery')->where('development_id',$id)->where('id',$photoId)
                    ->update(['image_path'=>$path,'updated_at'=>now()]);
                $this->syncMain($id);
            });
        } catch (\Throwable $e) {Storage::disk('public')->delete($path);throw $e;}
        $this->safeDeletePhoto($previous->image_path);
        return response()->json(['data'=>$this->rows($id)]);
    }
    public function reorder(Request $request, int $id)
    {
        $this->authorizeEdit($request);
        $v = $request->validate(['ids' => ['required','array'], 'ids.*' => ['integer','distinct']]);
        $given = array_map('intval', $v['ids']);
        $actual = DB::table('andarma_development_gallery')->where('development_id',$id)->pluck('id')->map(fn($n)=>(int)$n)->all();
        $a=$actual;$b=$given;sort($a);sort($b);
        if ($a!==$b) return response()->json(['message'=>'La galería cambió. Actualiza y vuelve a ordenar.'],409);
        DB::transaction(function () use ($id,$given) {
            foreach ($given as $index=>$photoId) {
                DB::table('andarma_development_gallery')->where('development_id',$id)->where('id',$photoId)
                    ->update(['sort_order'=>$index+1,'updated_at'=>now()]);
            }
            $this->syncMain($id);
        });
        return response()->json(['data'=>$this->rows($id)]);
    }
    public function destroy(Request $request,int $id,int $photoId)
    {
        $this->authorizeEdit($request);
        $this->importExistingMain($id);
        $row=DB::table('andarma_development_gallery')->where('development_id',$id)->where('id',$photoId)->first();
        abort_unless($row,404);
        DB::transaction(function () use ($id,$photoId) {
            DB::table('andarma_development_gallery')->where('development_id',$id)->where('id',$photoId)->delete();
            $this->syncMain($id);
        });
        $this->safeDeletePhoto($row->image_path);
        return response()->json(['data'=>$this->rows($id)]);
    }
}
