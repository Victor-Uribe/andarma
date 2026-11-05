<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    foreach (['title_accent','eyebrow','overline','footnote','tagline','editorial_label','side_title','side_description'] as $column) {
      if (!Schema::hasColumn('andarma_banners',$column)) {
        Schema::table('andarma_banners',function(Blueprint $table)use($column){$table->string($column,$column==='side_description'?500:255)->nullable();});
      }
    }
  }
  public function down(): void {
    // Mantener textos publicados al revertir despliegues; no eliminar información automáticamente.
  }
};
