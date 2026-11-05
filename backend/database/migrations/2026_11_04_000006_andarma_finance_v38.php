<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('andarma_lot_financing')) {
            Schema::create('andarma_lot_financing', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lot_id')->unique()->constrained('andarma_lots')->restrictOnDelete();
                $table->foreignId('client_id')->constrained('andarma_clients')->restrictOnDelete();
                $table->decimal('sale_price', 14, 2);
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('down_payment_amount', 14, 2)->default(0);
                $table->decimal('monthly_payment_amount', 14, 2)->default(0);
                $table->unsignedTinyInteger('payment_due_day')->default(1);
                $table->date('first_payment_period')->nullable();
                $table->date('sale_date')->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
        if (Schema::hasTable('andarma_client_payments')) {
            $columns = [
                'applied_period' => fn (Blueprint $table) => $table->date('applied_period')->nullable(),
                'applied_due_date' => fn (Blueprint $table) => $table->date('applied_due_date')->nullable(),
                'folio' => fn (Blueprint $table) => $table->string('folio', 150)->nullable(),
                'request_key' => fn (Blueprint $table) => $table->uuid('request_key')->nullable()->unique(),
                'cancel_reason' => fn (Blueprint $table) => $table->text('cancel_reason')->nullable(),
                'cancelled_at' => fn (Blueprint $table) => $table->timestamp('cancelled_at')->nullable(),
                'cancelled_by' => fn (Blueprint $table) => $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete(),
            ];
            foreach ($columns as $name => $add) {
                if (!Schema::hasColumn('andarma_client_payments', $name)) {
                    Schema::table('andarma_client_payments', $add);
                }
            }
        }
    }

    public function down(): void
    {
        // No eliminamos pagos ni historial contable mediante un rollback accidental.
        // Las columnas nuevas se dejan intactas por seguridad; solo se elimina la
        // configuración de financiamiento si se revierte explícitamente esta migración.
        Schema::dropIfExists('andarma_lot_financing');
    }
};
