<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AndarmaAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
class AndarmaAdminAuthController extends Controller {
  private function identity(User $user):array {
    $role=DB::table('andarma_roles')->where('id',$user->andarma_role_id)->first();
    return ['id'=>$user->id,'name'=>$user->name,'email'=>$user->email,
        'role'=>$role?['id'=>$role->id,'name'=>$role->name,'slug'=>$role->slug]:null,
        'permissions'=>AndarmaAccess::permissions($user)];
  }
  public function login(Request $request){
    $data=$request->validate(['email'=>['required','email'],'password'=>['required','string']]);
    $user=User::where('email',$data['email'])->first();
    if (!$user || !$user->is_andarma_admin || !$user->andarma_enabled || !$user->andarma_role_id || !Hash::check($data['password'],$user->password))
        return response()->json(['message'=>'Credenciales incorrectas o sin autorización.'],401);
    // ANDARMA_FASE17_TOKEN_SEGURO: solo tokens nuevos de este administrador.
    // Duracion configurable por entorno, limitada a un rango seguro de 1 a 168 horas.
    $hours = max(1, min(168, (int) env('ANDARMA_ADMIN_TOKEN_HOURS', 12)));
    $token = $user->createToken('andarma-admin', ['*'], now()->addHours($hours));
    return response()->json(['token'=>$token->plainTextToken,'user'=>$this->identity($user)]);
  }
  public function me(Request $request){return response()->json(['user'=>$this->identity($request->user())]);}
  public function logout(Request $request){$request->user()->currentAccessToken()?->delete();return response()->json(['message'=>'Sesión finalizada.']);}
}
