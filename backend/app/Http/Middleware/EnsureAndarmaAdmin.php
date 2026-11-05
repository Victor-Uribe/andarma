<?php
namespace App\Http\Middleware;
use App\Support\AndarmaAccess;
use Closure;
use Illuminate\Http\Request;
class EnsureAndarmaAdmin {
    public function handle(Request $request, Closure $next) {
        $user=$request->user();
        abort_unless($user && $user->is_andarma_admin && $user->andarma_enabled && $user->andarma_role_id,403,'Cuenta sin acceso administrativo.');
        $required=AndarmaAccess::required($request);
        if ($required) {
            $allowed=AndarmaAccess::can($user,$required);
            $path=trim(preg_replace('~^api/~','',$request->path()),'/');
            if ($path==='admin/user-role-options' && $request->isMethod('GET'))
                $allowed=AndarmaAccess::can($user,'users.view') || AndarmaAccess::can($user,'users.create') || AndarmaAccess::can($user,'users.edit');
            $path=trim(preg_replace('~^api/~','',$request->path()),'/');
            // Catálogos de apoyo compartidos entre módulos.
            if (!$allowed && $request->isMethod('GET') && in_array($path,['admin/im/developments','admin/developments'],true))
                $allowed=AndarmaAccess::can($user,'properties.view') || AndarmaAccess::can($user,'maps.view') || AndarmaAccess::can($user,'prospects.view');
            abort_unless($allowed,403,'No tienes permiso para realizar esta operación.');
            if ($path==='admin/leads/'.($request->route('leadId')??'').'/convert')
                abort_unless(AndarmaAccess::can($user,'clients.create'),403,'Necesitas permiso para crear clientes.');
            if (preg_match('~^admin/clients/[0-9]+/lots(?:/[0-9]+)?$~',$path) && !$request->isMethod('GET'))
                abort_unless(AndarmaAccess::can($user,'properties.edit'),403,'Necesitas permiso para modificar propiedades.');
        }
        return $next($request);
    }
}
