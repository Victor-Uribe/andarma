<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class EnsureAndarmaAdmin{
 public function handle(Request $request,Closure $next){abort_unless($request->user()?->is_andarma_admin,403,'Acceso restringido a administradores.');return $next($request);}
}
