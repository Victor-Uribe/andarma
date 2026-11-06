<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('andarma_daily_analytics', function (Blueprint $table) {
            $table->id();
            $table->date('event_date');
            $table->string('event_type', 20);
            $table->unsignedBigInteger('total')->default(0);
            $table->timestamps();
            $table->unique(['event_date', 'event_type'], 'andarma_analytics_daily_unique');
        });
    }
    public function down(): void { Schema::dropIfExists('andarma_daily_analytics'); }
};
