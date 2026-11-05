<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\AndarmaAccess;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('andarma_roles')) Schema::create('andarma_roles',function(Blueprint $t){
            $t->id();$t->string('name',100);$t->string('slug',100)->unique();$t->boolean('is_system')->default(false);$t->timestamps();
        });
        if (!Schema::hasTable('andarma_role_permissions')) Schema::create('andarma_role_permissions',function(Blueprint $t){
            $t->id();$t->foreignId('role_id')->constrained('andarma_roles')->cascadeOnDelete();
            $t->string('permission',90);$t->unique(['role_id','permission']);
        });
        if (!Schema::hasColumn('users','andarma_role_id')) Schema::table('users',function(Blueprint $t){
            $t->foreignId('andarma_role_id')->nullable()->constrained('andarma_roles')->nullOnDelete();
        });
        if (!Schema::hasColumn('users','andarma_enabled')) Schema::table('users',function(Blueprint $t){
            $t->boolean('andarma_enabled')->default(true);
        });
        $now=now();
        foreach ([['Superadministrador','superadmin',1],['Administrador','administrator',1]] as [$name,$slug,$system])
            DB::table('andarma_roles')->updateOrInsert(['slug'=>$slug],['name'=>$name,'is_system'=>$system,'updated_at'=>$now,'created_at'=>$now]);
        $super=DB::table('andarma_roles')->where('slug','superadmin')->value('id');
        $admin=DB::table('andarma_roles')->where('slug','administrator')->value('id');
        $permissions=array_filter(AndarmaAccess::validPermissions(), fn($p)=>!str_starts_with($p,'roles.') && !str_starts_with($p,'users.') && !in_array($p,['maps.create','maps.edit','maps.delete','media.delete'],true));
        foreach ($permissions as $permission) DB::table('andarma_role_permissions')->updateOrInsert(['role_id'=>$admin,'permission'=>$permission],[]);
        $isSuper=Schema::hasColumn('users','is_andarma_superadmin');
        $users=DB::table('users')->where('is_andarma_admin',true)->whereNull('andarma_role_id')->orderBy('id')->get();
        // Mantener el acceso de los administradores existentes. Si aún no hay
        // ningún superadministrador, promover al administrador más antiguo.
        $hasSuper=$isSuper && DB::table('users')->where('is_andarma_admin',true)->where('is_andarma_superadmin',true)->exists();
        $firstId=$users->first()?->id;
        foreach ($users as $user) {
            $grantSuper=($isSuper && $user->is_andarma_superadmin) || (!$hasSuper && $user->id===$firstId);
            DB::table('users')->where('id',$user->id)->update(['andarma_role_id'=>$grantSuper ? $super : $admin]);
            if ($grantSuper && $isSuper) DB::table('users')->where('id',$user->id)->update(['is_andarma_superadmin'=>true]);
        }
    }
    // Los roles y permisos se conservan deliberadamente ante rollback para prevenir pérdida accidental.
    public function down(): void {}
};
