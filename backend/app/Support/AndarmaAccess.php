<?php
namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AndarmaAccess
{
    public const MODULES = [
        'dashboard' => ['label'=>'Dashboard','actions'=>['view']],
        'developments' => ['label'=>'Desarrollos','actions'=>['view','create','edit','delete']],
        'properties' => ['label'=>'Propiedades','actions'=>['view','create','edit','delete']],
        'maps' => ['label'=>'Mapas','actions'=>['view','create','edit','delete']],
        'prospects' => ['label'=>'Prospectos','actions'=>['view','create','edit','delete']],
        'clients' => ['label'=>'Clientes','actions'=>['view','create','edit','delete']],
        'payments' => ['label'=>'Pagos y cobranza','actions'=>['view','create','edit','delete']],
        'banners' => ['label'=>'Banners','actions'=>['view','create','edit','delete']],
        'testimonials' => ['label'=>'Testimonios','actions'=>['view','create','edit','delete']],
        'media' => ['label'=>'Multimedia','actions'=>['view','create','delete']],
        'users' => ['label'=>'Usuarios','actions'=>['view','create','edit','delete']],
        'roles' => ['label'=>'Roles y permisos','actions'=>['view','create','edit','delete']],
        'settings' => ['label'=>'Configuración','actions'=>['view','edit']],
    ];

    public static function catalog(): array
    {
        return array_map(fn ($m)=>$m, self::MODULES);
    }
    public static function validPermissions(): array
    {
        $out=[];
        foreach (self::MODULES as $module=>$def)
            foreach ($def['actions'] as $action) $out[]="$module.$action";
        return $out;
    }
    public static function permissions(User $user): array
    {
        if (!$user->is_andarma_admin || !$user->andarma_role_id || !$user->andarma_enabled) return [];
        $role=DB::table('andarma_roles')->where('id',$user->andarma_role_id)->first();
        if (!$role) return [];
        if ($role->is_system && $role->slug==='superadmin') return self::validPermissions();
        return DB::table('andarma_role_permissions')->where('role_id',$role->id)->pluck('permission')->all();
    }
    public static function can(User $user, string $permission): bool
    {
        $assigned=self::permissions($user);
        if (in_array($permission,$assigned,true)) return true;
        // El editor de imágenes necesita subir fotografías sin exigir un permiso técnico adicional.
        if ($permission==='media.create') {
            foreach (['banners.create','banners.edit','testimonials.create','testimonials.edit',
                      'developments.create','developments.edit'] as $candidate)
                if (in_array($candidate,$assigned,true)) return true;
        }
        return false;
    }
    public static function isSuperadmin(User $user): bool
    {
        return $user->andarma_enabled && $user->andarma_role_id &&
            DB::table('andarma_roles')->where('id',$user->andarma_role_id)
              ->where('slug','superadmin')->where('is_system',true)->exists();
    }
    /** Solo rutas explicitamente conocidas: cualquier endpoint nuevo queda DENEGADO hasta clasificarlo. */
    public static function required(Request $request): ?string
    {
        $method=$request->method();
        $path=trim($request->path(),'/');
        $path=preg_replace('~^api/~','',$path);
        if (!str_starts_with($path,'admin/')) return null;
        $parts=explode('/',substr($path,6));
        $first=$parts[0]??'';
        if (in_array($first,['me','logout'],true)) return null;
        if ($first==='dashboard' && $method==='GET') return 'dashboard.view';
        if ($first==='analytics' && $method==='GET') return 'dashboard.view';
        if ($first==='lookup' && $method==='GET') return 'clients.view';
        if ($first==='site-content') abort(404, 'Editor de contenido web deshabilitado.');
        if ($first==='roles') return 'roles.'.match($method){'GET'=>'view','POST'=>'create','PUT','PATCH'=>'edit','DELETE'=>'delete',default=>abort(405)};
        if ($first==='users') return 'users.'.match($method){'GET'=>'view','POST'=>'create','PUT','PATCH'=>'edit','DELETE'=>'delete',default=>abort(405)};
        if ($first==='settings') return 'settings.'.match($method){'GET'=>'view','PUT','PATCH'=>'edit',default=>abort(405)};
        if ($first==='user-role-options' && $method==='GET') return 'users.view';
        // ANDARMA_FASE3B_GALERIA_RBAC: usar permisos del módulo Desarrollos; ninguna ruta nueva queda sin clasificar.
        if ($first==='development-gallery') {
            return 'developments.'.match($method){
                'GET'=>'view',
                'POST','PUT','PATCH','DELETE'=>'edit',
                default=>abort(405),
            };
        }
        if ($first==='media') return 'media.'.match($method){'GET'=>'view','POST'=>'create','DELETE'=>'delete',default=>abort(405)};
        if ($first==='clients') {
            if ($method==='GET') return 'clients.view';
            if (in_array('lots',$parts,true) && in_array($method,['POST','DELETE'],true)) return 'clients.edit';
            return 'clients.'.match($method){'POST'=>'create','PUT','PATCH'=>'edit','DELETE'=>'delete',default=>abort(405)};
        }
        if ($first==='leads' && ($parts[2]??'')==='convert') return 'prospects.edit';
        if (in_array($first,['banners','developments','lots','testimonials','leads'],true)) {
            $module=['lots'=>'properties','leads'=>'prospects'][$first]??$first;
            return $module.'.'.match($method){'GET'=>'view','POST'=>'create','PUT','PATCH'=>'edit','DELETE'=>'delete',default=>abort(405)};
        }
        if ($first==='im') {
            $which=$parts[1]??'';
            if ($which==='permissions') return 'maps.view';
            if ($which==='developments') {
                if (in_array('reorder',$parts,true) || ($method==='POST' && ($parts[2]??'')!=='save' && in_array('save',$parts,true))) return 'developments.edit';
                return 'developments.'.match($method){'GET'=>'view','POST'=>'create','PUT','PATCH'=>'edit','DELETE'=>'delete',default=>abort(405)};
            }
            if ($which==='inventory') return 'properties.'.match($method){'GET'=>'view','POST'=>'create','PUT','PATCH'=>'edit','DELETE'=>'delete',default=>abort(405)};
            if ($which==='maps') {
                if (in_array('polygons',$parts,true) && $method==='POST') return 'maps.edit';
                if (in_array('polygons',$parts,true) && $method==='DELETE') return 'maps.delete';
                if (in_array('polygons',$parts,true) && $method==='PUT') return 'maps.edit';
                return 'maps.'.match($method){'GET'=>'view','POST'=>'create','PUT','PATCH'=>'edit','DELETE'=>'delete',default=>abort(405)};
            }
        }
        if ($first==='finance') {
            if ($method==='GET') return 'payments.view';
            if ($method==='PUT' && in_array('terms',$parts,true)) return 'payments.edit';
            if ($method==='POST' && in_array('cancel',$parts,true)) return 'payments.delete';
            if ($method==='POST' && in_array('payments',$parts,true)) return 'payments.create';
        }
        abort(403, 'Ruta administrativa sin permisos definidos.');
    }
}
