<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('andarma_clients',function(Blueprint $t){
   $t->id();$t->foreignId('lead_id')->nullable()->unique()->constrained('andarma_leads')->nullOnDelete();
   $t->string('name',255);$t->string('phone',50);$t->string('email')->nullable();
   $t->string('alternate_phone',50)->nullable();$t->text('notes')->nullable();
   $t->enum('status',['active','inactive'])->default('active');$t->timestamps();
   $t->index('name');$t->index('phone');
  });
  Schema::table('andarma_lots',function(Blueprint $t){$t->foreignId('client_id')->nullable()->after('development_id')->constrained('andarma_clients')->restrictOnDelete();});
  Schema::create('andarma_client_payments',function(Blueprint $t){
   $t->id();$t->foreignId('client_id')->constrained('andarma_clients')->restrictOnDelete();
   $t->foreignId('lot_id')->nullable()->constrained('andarma_lots')->restrictOnDelete();
   $t->date('paid_at');$t->decimal('amount',14,2);$t->string('payment_type',40)->default('monthly');
   $t->string('payment_method',60)->nullable();$t->string('reference',150)->nullable();
   $t->string('status',20)->default('applied');$t->text('notes')->nullable();
   $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamps();
   $t->index(['client_id','paid_at']);
  });
 }
 public function down():void {
  Schema::dropIfExists('andarma_client_payments');
  Schema::table('andarma_lots',fn(Blueprint $t)=>$t->dropConstrainedForeignId('client_id'));
  Schema::dropIfExists('andarma_clients');
 }
};
