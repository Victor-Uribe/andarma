<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('andarma_developments','sort_order')) {
            Schema::table('andarma_developments', function(Blueprint $table) {
                $table->unsignedInteger('sort_order')->default(0)->index();
            });
            // Orden inicial: primero los registros más antiguos; sin tocar sus datos comerciales.
            $ids=DB::table('andarma_developments')->orderBy('id')->pluck('id');
            foreach($ids as $position=>$id) DB::table('andarma_developments')->where('id',$id)->update(['sort_order'=>$position+1]);
        }
    }
    public function down(): void
    {
        if (Schema::hasColumn('andarma_developments','sort_order')) {
            Schema::table('andarma_developments',fn(Blueprint $table)=>$table->dropColumn('sort_order'));
        }
    }
};
