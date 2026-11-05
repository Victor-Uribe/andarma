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
    return response()->json(['token'=>$user->createToken('andarma-admin')->plainTextToken,'user'=>$this->identity($user)]);
  }
  public function me(Request $request){return response()->json(['user'=>$this->identity($request->user())]);}
  public function logout(Request $request){$request->user()->currentAccessToken()?->delete();return response()->json(['message'=>'Sesión finalizada.']);}
}
