<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
class AndarmaAdminAuthController extends Controller {
 public function login(Request $request){
  $data=$request->validate(['email'=>['required','email'],'password'=>['required','string']]);
  $user=User::where('email',$data['email'])->first();
  if(!$user || !$user->is_andarma_admin || !Hash::check($data['password'],$user->password))return response()->json(['message'=>'Credenciales incorrectas o sin autorización.'],401);
  return response()->json(['token'=>$user->createToken('andarma-admin')->plainTextToken,'user'=>['id'=>$user->id,'name'=>$user->name,'email'=>$user->email]]);
 }
 public function me(Request $request){$user=$request->user();abort_unless($user->is_andarma_admin,403);return response()->json(['user'=>['id'=>$user->id,'name'=>$user->name,'email'=>$user->email]]);}
 public function logout(Request $request){$request->user()->currentAccessToken()?->delete();return response()->json(['message'=>'Sesión finalizada.']);}
}
