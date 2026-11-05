<?php
// ANDARMA_FASE20_AUDITORIA_CLIENTES
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('andarma_client_audit', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('action', 60)->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->unsignedBigInteger('lot_id')->nullable()->index();
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            // Sin llaves foraneas: conservar historia aunque se desactiven usuarios.
        });
    }
    public function down(): void {
        Schema::dropIfExists('andarma_client_audit');
    }
};
