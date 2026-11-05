<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('andarma_settings')) Schema::create('andarma_settings',function(Blueprint $table){
            $table->string('key',80)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        // No borrar configuración corporativa accidentalmente en rollback.
    }
};
