<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('andarma_testimonials')) return;
        if (!Schema::hasColumn('andarma_testimonials', 'property_image_url')) {
            Schema::table('andarma_testimonials', function (Blueprint $table) {
                $table->string('property_image_url', 2048)->nullable()->after('image_url');
            });
        }
    }
    public function down(): void {
        if (Schema::hasTable('andarma_testimonials') && Schema::hasColumn('andarma_testimonials', 'property_image_url')) {
            Schema::table('andarma_testimonials', fn (Blueprint $table) => $table->dropColumn('property_image_url'));
        }
    }
};
