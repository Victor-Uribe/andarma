<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AndarmaAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AndarmaRbacController extends Controller
{
    private function super(Request $r): void {
        abort_unless(AndarmaAccess::isSuperadmin($r->user()),403,'Solo el superadministrador puede administrar roles y usuarios.');
    }
    private function roleRow($role): array {
        return ['id'=>$role->id,'name'=>$role->name,'slug'=>$role->slug,'is_system'=>(bool)$role->is_system,
          'permissions'=>DB::table('andarma_role_permissions')->where('role_id',$role->id)->pluck('permission')->all()];
    }
    public function roles(Request $r) {
        $this->super($r);
        return response()->json(['data'=>DB::table('andarma_roles')->orderBy('id')->get()->map(fn($role)=>$this->roleRow($role)),
            'modules'=>AndarmaAccess::MODULES]);
    }
    private function roleData(Request $r,?int $id=null):array {
        $d=$r->validate(['name'=>['required','string','min:3','max:100',Rule::unique('andarma_roles','name')->ignore($id)],
            'permissions'=>'present|array','permissions.*'=>'required|string|max:90']);
        $unknown=array_diff($d['permissions'],AndarmaAccess::validPermissions());
        // ANDARMA_V51_2: roles.* solo para superadministrador; users.* delegable.
        if ($unknown) throw ValidationException::withMessages(['permissions'=>'Permisos no reconocidos: '.implode(', ',array_slice(array_values($unknown),0,8))]);
        if (array_filter($d['permissions'],fn($p)=>str_starts_with($p,'roles.')))
            throw ValidationException::withMessages(['permissions'=>'Los permisos de Roles y permisos son exclusivos del Superadministrador.']);
        return $d;
    }
    public function storeRole(Request $r) {
        $this->super($r);$d=$this->roleData($r);
        return DB::transaction(function()use($d){
            $slug=Str::slug($d['name']);if (!$slug)$slug='rol';
            if (DB::table('andarma_roles')->where('slug',$slug)->exists()) $slug.='-'.Str::lower(Str::random(5));
            $id=DB::table('andarma_roles')->insertGetId(['name'=>$d['name'],'slug'=>$slug,'is_system'=>false,'created_at'=>now(),'updated_at'=>now()]);
            foreach(array_unique($d['permissions']) as $p)DB::table('andarma_role_permissions')->insert(['role_id'=>$id,'permission'=>$p]);
            return response()->json(['data'=>$this->roleRow(DB::table('andarma_roles')->find($id))],201);
        });
    }
    public function updateRole(Request $r,int $id) {
        $this->super($r);$role=DB::table('andarma_roles')->find($id);abort_unless($role,404);
        abort_if($role->slug==='superadmin',403,'El rol Superadministrador no se puede modificar.');
        $d=$this->roleData($r,$id);
        if ($role->slug==='administrator' && $d['name']!==$role->name) throw ValidationException::withMessages(['name'=>'El nombre Administrador está reservado; puedes modificar sus permisos.']);
        return DB::transaction(function()use($d,$id){
            DB::table('andarma_roles')->where('id',$id)->update(['name'=>$d['name'],'updated_at'=>now()]);
            // ANDARMA_F14_REVOKE_ROLE_PERMISSIONS: al cambiar permisos, revocar sesiones del rol.
            $oldPermissions=DB::table('andarma_role_permissions')->where('role_id',$id)->pluck('permission')->all();
            $newPermissions=array_values(array_unique($d['permissions']));
            sort($oldPermissions);
            sort($newPermissions);
            DB::table('andarma_role_permissions')->where('role_id',$id)->delete();
            foreach($newPermissions as $p)DB::table('andarma_role_permissions')->insert(['role_id'=>$id,'permission'=>$p]);
            if ($oldPermissions !== $newPermissions) {
                User::where('is_andarma_admin',true)->where('andarma_role_id',$id)
                    ->chunkById(100,function($users){
                        foreach($users as $member) $member->tokens()->delete();
                    });
            }
            return response()->json(['data'=>$this->roleRow(DB::table('andarma_roles')->find($id))]);
        });
    }
    public function deleteRole(Request $r,int $id) {
        $this->super($r);$role=DB::table('andarma_roles')->find($id);abort_unless($role,404);
        abort_if($role->is_system,403,'No puedes eliminar los roles del sistema.');
        abort_if(DB::table('users')->where('andarma_role_id',$id)->exists(),409,'Primero reasigna a los usuarios de este rol.');
        DB::table('andarma_roles')->where('id',$id)->delete();return response()->json(['message'=>'Rol eliminado.']);
    }
    private function userRow(User $u):array {
        return ['id'=>$u->id,'name'=>$u->name,'email'=>$u->email,'andarma_role_id'=>$u->andarma_role_id,
          'andarma_enabled'=>(bool)$u->andarma_enabled,
          'role'=>DB::table('andarma_roles')->where('id',$u->andarma_role_id)->first(['id','name','slug'])];
    }
    private function userPermission(Request $r,string $action): void {
        abort_unless(AndarmaAccess::can($r->user(),'users.'.$action),403,'No tienes autorización para gestionar usuarios.');
    }
    private function roleSlug(int $id): ?string {
        return DB::table('andarma_roles')->where('id',$id)->value('slug');
    }
    // Para el selector de usuarios; nunca entrega permisos de roles ni expone el Superadministrador a usuarios normales.
    public function userRoleOptions(Request $r) {
        abort_unless(AndarmaAccess::can($r->user(),'users.view') || AndarmaAccess::can($r->user(),'users.create') || AndarmaAccess::can($r->user(),'users.edit'),403);
        $q=DB::table('andarma_roles')->select('id','name','slug')->orderBy('name');
        if (!AndarmaAccess::isSuperadmin($r->user())) $q->where('slug','!=','superadmin');
        return response()->json(['data'=>$q->get()]);
    }
    public function users(Request $r) {
        $this->userPermission($r,'view');
        $q=User::where('is_andarma_admin',true)->orderBy('name');
        if (!AndarmaAccess::isSuperadmin($r->user())) {
            $super=DB::table('andarma_roles')->where('slug','superadmin')->value('id');
            $q->where('andarma_role_id','!=',$super);
        }
        return response()->json(['data'=>$q->get()->map(fn($u)=>$this->userRow($u))]);
    }
    private function userData(Request $r,?int $id=null):array {
        $rules=['name'=>'required|string|max:255','email'=>['required','email','max:255',Rule::unique('users','email')->ignore($id)],
          'andarma_role_id'=>'required|integer|exists:andarma_roles,id','andarma_enabled'=>'required|boolean'];
        $rules['password']=$id?'nullable|string|min:10|max:200':'required|string|min:10|max:200';
        return $r->validate($rules);
    }
    public function storeUser(Request $r) {
        $this->userPermission($r,'create');$d=$this->userData($r);
        $requestedSlug=$this->roleSlug((int)$d['andarma_role_id']);
        abort_if($requestedSlug==='superadmin' && !AndarmaAccess::isSuperadmin($r->user()),403,'Solo Superadministrador puede dar de alta superadministradores.');
        $u=User::create(['name'=>$d['name'],'email'=>$d['email'],'password'=>$d['password'],'is_andarma_admin'=>true]);
        $u->andarma_role_id=$d['andarma_role_id'];$u->andarma_enabled=$d['andarma_enabled'];
        $u->is_andarma_superadmin=DB::table('andarma_roles')->where('id',$d['andarma_role_id'])->value('slug')==='superadmin';
        $u->save();return response()->json(['data'=>$this->userRow($u)],201);
    }
    public function updateUser(Request $r,int $id) {
        $this->userPermission($r,'edit');$u=User::findOrFail($id);abort_unless($u->is_andarma_admin,404);
        $d=$this->userData($r,$id);
        $currentSlug=$this->roleSlug((int)$u->andarma_role_id);
        $requestedSlug=$this->roleSlug((int)$d['andarma_role_id']);
        abort_if(!AndarmaAccess::isSuperadmin($r->user()) && ($currentSlug==='superadmin'||$requestedSlug==='superadmin'),403,
            'Solo Superadministrador puede modificar cuentas o asignaciones de Superadministrador.');
        if ($u->id===$r->user()->id && (!$d['andarma_enabled'] || (int)$d['andarma_role_id']!==(int)$u->andarma_role_id))
            throw ValidationException::withMessages(['andarma_role_id'=>'No puedes desactivarte o cambiar tu propio rol.']);
        $newSlug=DB::table('andarma_roles')->where('id',$d['andarma_role_id'])->value('slug');
        if ($u->is_andarma_superadmin && ($newSlug!=='superadmin' || !$d['andarma_enabled'])) $this->ensureAnotherSuper($u->id);
        // ANDARMA_F14_REVOKE_CHANGED_ACCOUNT: cerrar sesiones si cambia el acceso o la identidad.
        $accessChanged=(int)$u->andarma_role_id !== (int)$d['andarma_role_id']
            || (bool)$u->andarma_enabled !== (bool)$d['andarma_enabled'];
        $emailChanged=(string)$u->email !== (string)$d['email'];
        $u->name=$d['name'];$u->email=$d['email'];$u->andarma_role_id=$d['andarma_role_id'];
        $u->andarma_enabled=$d['andarma_enabled'];$u->is_andarma_superadmin=$newSlug==='superadmin';
        if (!empty($d['password'])) $u->password=Hash::make($d['password']);
        $u->save();
        if (!empty($d['password']) || !$d['andarma_enabled'] || $accessChanged || $emailChanged)
            $u->tokens()->delete();
        return response()->json(['data'=>$this->userRow($u)]);
    }
    private function ensureAnotherSuper(int $excluding):void {
        $id=DB::table('andarma_roles')->where('slug','superadmin')->value('id');
        abort_unless(User::where('is_andarma_admin',true)->where('andarma_enabled',true)
          ->where('andarma_role_id',$id)->where('id','!=',$excluding)->exists(),409,
          'No puedes dejar ANDARMA sin un superadministrador activo.');
    }
    public function deleteUser(Request $r,int $id) {
        $this->userPermission($r,'delete');$u=User::findOrFail($id);abort_unless($u->is_andarma_admin,404);
        abort_if(!AndarmaAccess::isSuperadmin($r->user()) && $this->roleSlug((int)$u->andarma_role_id)==='superadmin',403,
            'Solo Superadministrador puede desactivar cuentas de Superadministrador.');
        abort_if($u->id===$r->user()->id,409,'No puedes desactivar tu cuenta.');
        if ($u->is_andarma_superadmin) $this->ensureAnotherSuper($u->id);
        $u->andarma_enabled=false;$u->save();$u->tokens()->delete();
        return response()->json(['message'=>'Cuenta desactivada. Se conservará su historial.']);
    }
}
