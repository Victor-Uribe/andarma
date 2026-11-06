<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('andarma_development_gallery', function (Blueprint $table) {
            $table->id();
            $table->foreignId('development_id')->constrained('andarma_developments')->cascadeOnDelete();
            $table->string('image_path', 512);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['development_id', 'sort_order']);
        });
    }
    public function down(): void { Schema::dropIfExists('andarma_development_gallery'); }
};
