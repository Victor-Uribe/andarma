<?php
// ANDARMA_FASE4_PRUEBAS_REGRESION_V1
namespace Tests\Feature;

use App\Http\Middleware\EnsureAndarmaAdmin;
use App\Support\AndarmaAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Regresión de rutas y RBAC sin escritura ni migración de la BD.
 * Estas pruebas no reemplazan E2E con una base de pruebas separada.
 */
class AndarmaAccessRoutesRegressionTest extends TestCase
{
    public function test_todas_las_rutas_administrativas_exigen_autenticacion_y_middleware_admin(): void
    {
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            $uri = trim($route->uri(), '/');
            if (!str_starts_with($uri, 'api/admin/')) continue;
            // El login es el único acceso público a la zona administrativa.
            if ($uri === 'api/admin/login') continue;

            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:sanctum', $middleware, "Sin Sanctum: {$uri}");
            $this->assertContains(EnsureAndarmaAdmin::class, $middleware, "Sin validación administrativa: {$uri}");
            $checked++;
        }
        $this->assertGreaterThanOrEqual(30, $checked, 'Se detectaron pocas rutas; comprueba el registro de rutas API.');
    }

    public function test_las_operaciones_sensibles_tienen_clasificacion_rbac_explicita(): void
    {
        $cases = [
            ['GET',  '/api/admin/im/inventory', 'properties.view'],
            ['POST', '/api/admin/im/inventory', 'properties.create'],
            ['PUT',  '/api/admin/im/inventory/1', 'properties.edit'],
            ['DELETE', '/api/admin/im/inventory/1', 'properties.delete'],
            ['GET', '/api/admin/clients', 'clients.view'],
            ['POST', '/api/admin/clients/2/lots', 'clients.edit'],
            ['DELETE', '/api/admin/clients/2/lots/1', 'clients.edit'],
            ['GET', '/api/admin/finance/payments', 'payments.view'],
            ['PUT', '/api/admin/finance/lots/1/terms', 'payments.edit'],
            ['POST', '/api/admin/finance/lots/1/payments', 'payments.create'],
            ['POST', '/api/admin/finance/payments/1/cancel', 'payments.delete'],
            ['POST', '/api/admin/roles', 'roles.create'],
            ['PUT', '/api/admin/users/2', 'users.edit'],
            ['DELETE', '/api/admin/users/2', 'users.delete'],
            ['PUT', '/api/admin/settings', 'settings.edit'],
        ];
        foreach ($cases as [$method, $url, $permission]) {
            $this->assertSame(
                $permission,
                AndarmaAccess::required(Request::create($url, $method)),
                "Permiso mal clasificado para {$method} {$url}"
            );
        }
    }

    public function test_ruta_administrativa_desconocida_se_deniega_por_defecto(): void
    {
        $this->expectException(HttpException::class);
        AndarmaAccess::required(Request::create('/api/admin/operacion-desconocida', 'POST'));
    }

    public function test_legacy_no_permite_ventas_ni_desarrollos_por_endpoints_genericos(): void
    {
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            $uri = trim($route->uri(), '/');
            if (!in_array($uri, ['api/admin/{resource}', 'api/admin/{resource}/{id}'], true)) continue;
            if (!array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE'])) continue;
            $checked++;
            $rule = $route->wheres['resource'] ?? null;
            $this->assertNotEmpty($rule, "Ruta genérica de escritura sin filtro: {$uri}");
            foreach (['lots', 'developments'] as $blocked) {
                $this->assertSame(0, preg_match('~^(?:'.$rule.')$~D', $blocked),
                    "La escritura genérica permite {$blocked}: {$uri}");
            }
        }
        // Cero también es válido: es aún más seguro haber eliminado las rutas genéricas.
        $this->assertGreaterThanOrEqual(0, $checked);
    }

    public function test_sin_credenciales_no_se_puede_acceder_a_las_rutas_administrativas(): void
    {
        // Solo usa rutas sin parámetros. Un usuario invitado debe recibir 401 JSON
        // antes de que los controladores hagan cualquier consulta de negocio.
        $this->getJson('/api/admin/me')->assertUnauthorized();
        $this->getJson('/api/admin/im/inventory')->assertUnauthorized();
        $this->getJson('/api/admin/finance/payments')->assertUnauthorized();
        $this->getJson('/api/admin/users')->assertUnauthorized();
        $this->postJson('/api/admin/im/inventory', [])->assertUnauthorized();
    }
}
