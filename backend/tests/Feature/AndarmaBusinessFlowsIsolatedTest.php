<?php
// ANDARMA_FASE5_PRUEBAS_FUNCIONALES_V1
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pruebas API de negocio con SQLite :memory: EN CONEXION AISLADA.
 * NO RefreshDatabase, NO migraciones ni conexión MySQL/producción.
 * El esquema mínimo se construye aquí para evitar migraciones exclusivas MySQL.
 * No prueba concurrencia real ni sustituye QA MySQL en ambiente independiente.
 */
class AndarmaBusinessFlowsIsolatedTest extends TestCase
{
    private const CONNECTION = 'andarma_f5_isolated';
    private ?string $originalConnection = null;
    private int $developmentId;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('La extensión pdo_sqlite es necesaria para pruebas aisladas.');
        }
        $this->originalConnection = DB::getDefaultConnection();
        config()->set('database.connections.'.self::CONNECTION, [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge(self::CONNECTION);
        DB::setDefaultConnection(self::CONNECTION);
        if (DB::getDefaultConnection() !== self::CONNECTION
            || DB::connection()->getDriverName() !== 'sqlite'
            || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \LogicException('BLOQUEADO: La conexión no es SQLite aislado en memoria.');
        }
        $this->createMinimalSchema();
        $role = DB::table('andarma_roles')->insertGetId([
            'name' => 'Superadministrador', 'slug' => 'superadmin', 'is_system' => true,
        ]);
        $this->admin = User::query()->create([
            'name' => 'Pruebas ANDARMA', 'email' => 'andarma-fixture@example.invalid',
            'password' => 'un-password-de-prueba', 'is_andarma_admin' => true,
        ]);
        $this->admin->forceFill([
            'andarma_role_id' => $role, 'andarma_enabled' => true,
            'is_andarma_superadmin' => true,
        ])->save();
        Sanctum::actingAs($this->admin, ['*']);
        $this->developmentId = DB::table('andarma_developments')->insertGetId([
            'name' => 'Desarrollo ensayo aislado', 'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->originalConnection !== null) {
            DB::setDefaultConnection($this->originalConnection);
            DB::purge(self::CONNECTION);
        }
        parent::tearDown();
    }

    private function createMinimalSchema(): void
    {
        $s = Schema::connection(self::CONNECTION);
        $s->create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->unique();
            $t->string('password'); $t->boolean('is_andarma_admin')->default(false);
            $t->boolean('is_andarma_superadmin')->default(false);
            $t->boolean('andarma_enabled')->default(true);
            $t->unsignedBigInteger('andarma_role_id')->nullable(); $t->timestamps();
        });
        $s->create('andarma_roles', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('slug')->unique();
            $t->boolean('is_system')->default(false);
        });
        $s->create('andarma_role_permissions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('role_id'); $t->string('permission');
        });
        $s->create('andarma_developments', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('status')->default('active');
            $t->timestamps();
        });
        $s->create('andarma_clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('phone');
            $t->string('status')->default('active'); $t->timestamps();
        });
        $s->create('andarma_lots', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('development_id');
            $t->unsignedBigInteger('client_id')->nullable();
            $t->string('code'); $t->string('block')->default('');
            $t->decimal('area_m2', 12, 2)->nullable();
            $t->decimal('front_m', 10, 2)->nullable();
            $t->decimal('depth_m', 10, 2)->nullable();
            $t->decimal('price', 14, 2)->nullable();
            $t->string('status')->default('available');
            $t->text('description')->nullable(); $t->timestamps();
        });
        $s->create('andarma_lot_geometries', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('plan_id');
            $t->unsignedBigInteger('lot_id')->unique(); $t->text('points');
            $t->timestamps();
        });
        $s->create('andarma_client_audit', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('actor_user_id')->nullable();
            $t->string('action'); $t->unsignedBigInteger('client_id')->nullable();
            $t->unsignedBigInteger('lot_id')->nullable();
            $t->text('changes')->nullable(); $t->timestamp('created_at')->nullable();
        });
        $s->create('andarma_lot_financing', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('lot_id')->unique();
            $t->unsignedBigInteger('client_id');
            $t->decimal('sale_price', 14, 2);
            $t->decimal('discount_amount', 14, 2)->default(0);
            $t->decimal('down_payment_amount', 14, 2)->default(0);
            $t->decimal('monthly_payment_amount', 14, 2)->default(0);
            $t->unsignedTinyInteger('payment_due_day')->default(1);
            $t->date('first_payment_period')->nullable();
            $t->date('sale_date')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
        });
        $s->create('andarma_client_payments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('client_id');
            $t->unsignedBigInteger('lot_id')->nullable();
            $t->date('paid_at'); $t->decimal('amount', 14, 2);
            $t->string('payment_type'); $t->string('payment_method')->nullable();
            $t->string('reference')->nullable(); $t->string('folio')->nullable();
            $t->text('notes')->nullable();
            $t->date('applied_period')->nullable();
            $t->date('applied_due_date')->nullable();
            $t->uuid('request_key')->unique();
            $t->string('status')->default('applied');
            $t->text('cancel_reason')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->unsignedBigInteger('cancelled_by')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });
    }

    private function createLot(string $status = 'available', ?int $clientId = null): int
    {
        return DB::table('andarma_lots')->insertGetId([
            'development_id' => $this->developmentId,
            'client_id' => $clientId, 'code' => 'PRUEBA-01', 'block' => 'TEST',
            'price' => '1000.00', 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function lotPayload(string $status): array
    {
        return ['development_id' => $this->developmentId, 'code' => 'PRUEBA-01',
                'block' => 'TEST', 'price' => '1000.00', 'status' => $status];
    }

    private function createClient(string $status = 'active'): int
    {
        return DB::table('andarma_clients')->insertGetId([
            'name' => 'Comprador de ensayo', 'phone' => '0000000000',
            'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function createFinancing(int $lotId, int $clientId): void
    {
        DB::table('andarma_lot_financing')->insert([
            'lot_id' => $lotId, 'client_id' => $clientId,
            'sale_price' => '1000.00', 'discount_amount' => '0.00',
            'down_payment_amount' => '200.00', 'monthly_payment_amount' => '200.00',
            'payment_due_day' => 9, 'first_payment_period' => '2026-11-01',
            'updated_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_no_permite_crear_terreno_directamente_como_vendido(): void
    {
        $this->postJson('/api/admin/im/inventory', $this->lotPayload('sold'))
            ->assertStatus(422);
        $this->assertSame(0, DB::table('andarma_lots')->count());
    }

    public function test_no_permite_marcar_vendido_terreno_sin_comprador(): void
    {
        $lot = $this->createLot();
        $this->putJson('/api/admin/im/inventory/'.$lot, $this->lotPayload('sold'))
            ->assertStatus(422);
        $this->assertSame('available', DB::table('andarma_lots')->where('id', $lot)->value('status'));
    }

    public function test_no_permite_asignar_un_cliente_inactivo(): void
    {
        $lot = $this->createLot();
        $client = $this->createClient('inactive');
        $this->postJson('/api/admin/clients/'.$client.'/lots', [
            'lot_id' => $lot, 'status' => 'sold',
        ])->assertStatus(409);
        $this->assertNull(DB::table('andarma_lots')->where('id', $lot)->value('client_id'));
    }

    public function test_asignacion_valida_vende_el_lote_y_no_permite_duplicarla(): void
    {
        $lot = $this->createLot();
        $client = $this->createClient();
        $this->postJson('/api/admin/clients/'.$client.'/lots', [
            'lot_id' => $lot, 'status' => 'sold',
        ])->assertOk();
        $row = DB::table('andarma_lots')->find($lot);
        $this->assertSame('sold', $row->status);
        $this->assertSame($client, (int) $row->client_id);
        $this->assertSame(1, DB::table('andarma_client_audit')->where('action', 'lot.assigned')->count());
        $this->postJson('/api/admin/clients/'.$client.'/lots', [
            'lot_id' => $lot, 'status' => 'sold',
        ])->assertStatus(409);
        $this->assertSame(1, DB::table('andarma_client_audit')->count());
    }

    public function test_contrato_impide_liberacion_y_degradacion_de_venta(): void
    {
        $client = $this->createClient();
        $lot = $this->createLot('sold', $client);
        $this->createFinancing($lot, $client);
        $this->deleteJson('/api/admin/clients/'.$client.'/lots/'.$lot)->assertStatus(409);
        $this->putJson('/api/admin/im/inventory/'.$lot, $this->lotPayload('reserved'))
            ->assertStatus(409);
        $this->assertSame('sold', DB::table('andarma_lots')->where('id', $lot)->value('status'));
        $this->assertSame($client, (int) DB::table('andarma_lots')->where('id', $lot)->value('client_id'));
    }

    public function test_no_permite_importes_con_mas_de_dos_decimales(): void
    {
        $client = $this->createClient();
        $lot = $this->createLot('sold', $client);
        $this->createFinancing($lot, $client);
        $this->postJson('/api/admin/finance/lots/'.$lot.'/payments', [
            'paid_at' => '2026-11-09', 'amount' => '20.123',
            'payment_type' => 'down_payment', 'payment_method' => 'cash',
            'request_key' => (string) Str::uuid(),
        ])->assertStatus(422);
        $this->assertSame(0, DB::table('andarma_client_payments')->count());
        $this->putJson('/api/admin/finance/lots/'.$lot.'/terms', [
            'sale_price' => '1000.001', 'discount_amount' => '0.00',
            'down_payment_amount' => '200.00', 'monthly_payment_amount' => '200.00',
            'payment_due_day' => 9, 'first_payment_period' => '2026-11',
        ])->assertStatus(422);
        $this->assertSame('1000', (string) (int) DB::table('andarma_lot_financing')->where('lot_id', $lot)->value('sale_price'));
    }

    public function test_pago_es_idempotente_y_cancelar_conserva_el_historial(): void
    {
        $client = $this->createClient();
        $lot = $this->createLot('sold', $client);
        $this->createFinancing($lot, $client);
        $key = (string) Str::uuid();
        $payload = [
            'paid_at' => '2026-11-09', 'amount' => '100.00',
            'payment_type' => 'down_payment', 'payment_method' => 'transfer',
            'reference' => 'TRANSFERENCIA-TEST', 'request_key' => $key,
        ];
        $result = $this->postJson('/api/admin/finance/lots/'.$lot.'/payments', $payload)
            ->assertCreated()->json();
        $paymentId = $result['data']['id'];
        $this->postJson('/api/admin/finance/lots/'.$lot.'/payments', $payload)
            ->assertOk()->assertJsonPath('duplicate_request', true);
        $this->assertSame(1, DB::table('andarma_client_payments')->count());
        $altered = $payload;
        $altered['payment_method'] = 'cash';
        $this->postJson('/api/admin/finance/lots/'.$lot.'/payments', $altered)->assertStatus(409);
        $this->assertSame(1, DB::table('andarma_client_payments')->count());
        $this->postJson('/api/admin/finance/payments/'.$paymentId.'/cancel', [
            'reason' => 'Cancelación de prueba automatizada.',
        ])->assertOk();
        $this->postJson('/api/admin/finance/payments/'.$paymentId.'/cancel', [
            'reason' => 'Intento de cancelación repetida.',
        ])->assertStatus(409);
        $this->postJson('/api/admin/finance/lots/'.$lot.'/payments', $payload)
            ->assertStatus(409);
        $this->assertSame(1, DB::table('andarma_client_payments')->count());
        $this->assertSame('cancelled', DB::table('andarma_client_payments')->where('id', $paymentId)->value('status'));
    }

    public function test_inventario_oculta_datos_personales_sin_permiso_clientes(): void
    {
        $client = $this->createClient();
        $this->createLot('sold', $client);
        $role = DB::table('andarma_roles')->insertGetId([
            'name' => 'Inventario', 'slug' => 'inventario', 'is_system' => false,
        ]);
        DB::table('andarma_role_permissions')->insert([
            'role_id' => $role, 'permission' => 'properties.view',
        ]);
        $viewer = User::query()->create([
            'name' => 'Consulta inventario', 'email' => 'consulta-f5@example.invalid',
            'password' => 'clave-temporal-fixture', 'is_andarma_admin' => true,
        ]);
        $viewer->forceFill([
            'andarma_role_id' => $role, 'andarma_enabled' => true,
            'is_andarma_superadmin' => false,
        ])->save();
        Sanctum::actingAs($viewer, ['*']);
        $row = $this->getJson('/api/admin/im/inventory')->assertOk()->json('data.0');
        $this->assertTrue((bool) ($row['has_buyer'] ?? false));
        $this->assertArrayNotHasKey('client_id', $row);
        $this->assertNull($row['buyer_name'] ?? null);
        $this->assertNull($row['buyer_phone'] ?? null);
        $this->assertArrayNotHasKey('payment_data', $row);
    }
}
