<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private function indexExists(string $table, string $index): bool {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $index]
        );
        return (int) ($row->total ?? 0) > 0;
    }

    public function up(): void {
        // Una ejecución anterior de MySQL pudo crear esta columna antes de fallar.
        if (!Schema::hasColumn('users', 'is_andarma_superadmin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_andarma_superadmin')->default(false);
            });
        }

        // Primero preparar todas las columnas, respetando las ya creadas.
        if (!Schema::hasColumn('andarma_lots', 'block')) {
            Schema::table('andarma_lots', function (Blueprint $table) {
                $table->string('block', 120)->default('');
            });
        }
        if (!Schema::hasColumn('andarma_lots', 'front_m')) {
            Schema::table('andarma_lots', function (Blueprint $table) {
                $table->decimal('front_m', 10, 2)->nullable();
            });
        }
        if (!Schema::hasColumn('andarma_lots', 'depth_m')) {
            Schema::table('andarma_lots', function (Blueprint $table) {
                $table->decimal('depth_m', 10, 2)->nullable();
            });
        }

        // CRÍTICO: el índice antiguo sirve a una clave foránea de development_id.
        // Crear antes el índice sustituto, cuyo primer campo también es development_id.
        if (!$this->indexExists('andarma_lots', 'andarma_lots_dev_block_code_unique')) {
            Schema::table('andarma_lots', function (Blueprint $table) {
                $table->unique(['development_id', 'block', 'code'], 'andarma_lots_dev_block_code_unique');
            });
        }
        if ($this->indexExists('andarma_lots', 'andarma_lots_development_id_code_unique')) {
            Schema::table('andarma_lots', function (Blueprint $table) {
                $table->dropUnique('andarma_lots_development_id_code_unique');
            });
        }

        if (!Schema::hasTable('andarma_plans')) {
            Schema::create('andarma_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('development_id')->constrained('andarma_developments')->restrictOnDelete();
                $table->string('title', 180);
                $table->string('image_path', 500);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('andarma_lot_geometries')) {
            Schema::create('andarma_lot_geometries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('plan_id')->constrained('andarma_plans')->cascadeOnDelete();
                $table->foreignId('lot_id')->unique()->constrained('andarma_lots')->cascadeOnDelete();
                $table->json('points');
                $table->timestamps();
            });
        }
    }

    public function down(): void {
        // Mantener el comportamiento de reversión, pero con el orden seguro.
        Schema::dropIfExists('andarma_lot_geometries');
        Schema::dropIfExists('andarma_plans');

        if (!$this->indexExists('andarma_lots', 'andarma_lots_development_id_code_unique')) {
            Schema::table('andarma_lots', function (Blueprint $table) {
                $table->unique(['development_id', 'code'], 'andarma_lots_development_id_code_unique');
            });
        }
        if ($this->indexExists('andarma_lots', 'andarma_lots_dev_block_code_unique')) {
            Schema::table('andarma_lots', function (Blueprint $table) {
                $table->dropUnique('andarma_lots_dev_block_code_unique');
            });
        }
        $columns = array_values(array_filter(
            ['block', 'front_m', 'depth_m'],
            fn ($col) => Schema::hasColumn('andarma_lots', $col)
        ));
        if ($columns) {
            Schema::table('andarma_lots', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
        if (Schema::hasColumn('users', 'is_andarma_superadmin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_andarma_superadmin');
            });
        }
    }
};
