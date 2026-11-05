<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('users',function(Blueprint $t){$t->boolean('is_andarma_admin')->default(false);});
  Schema::create('andarma_banners',function(Blueprint $t){$t->id();$t->string('title');$t->text('subtitle')->nullable();$t->string('image_url',2048)->nullable();$t->string('button_text')->nullable();$t->string('button_url',1024)->nullable();$t->unsignedInteger('sort_order')->default(0);$t->boolean('is_active')->default(true);$t->timestamps();});
  Schema::create('andarma_developments',function(Blueprint $t){$t->id();$t->string('name');$t->string('slug')->unique();$t->text('description')->nullable();$t->string('location')->nullable();$t->string('image_url',2048)->nullable();$t->enum('status',['active','upcoming','inactive'])->default('upcoming');$t->boolean('is_featured')->default(false);$t->timestamps();});
  Schema::create('andarma_lots',function(Blueprint $t){$t->id();$t->foreignId('development_id')->constrained('andarma_developments')->restrictOnDelete();$t->string('code');$t->decimal('area_m2',12,2)->nullable();$t->decimal('price',14,2)->nullable();$t->enum('status',['available','reserved','sold','inactive'])->default('available');$t->text('description')->nullable();$t->decimal('latitude',10,7)->nullable();$t->decimal('longitude',10,7)->nullable();$t->timestamps();$t->unique(['development_id','code']);});
  Schema::create('andarma_testimonials',function(Blueprint $t){$t->id();$t->string('name');$t->string('role')->nullable();$t->text('quote');$t->string('image_url',2048)->nullable();$t->unsignedTinyInteger('rating')->default(5);$t->unsignedInteger('sort_order')->default(0);$t->boolean('is_active')->default(true);$t->timestamps();});
  Schema::create('andarma_leads',function(Blueprint $t){$t->id();$t->foreignId('development_id')->nullable()->constrained('andarma_developments')->nullOnDelete();$t->string('name');$t->string('email')->nullable();$t->string('phone')->nullable();$t->text('message')->nullable();$t->enum('status',['new','contacted','qualified','closed'])->default('new');$t->text('notes')->nullable();$t->timestamps();});
  Schema::create('andarma_media',function(Blueprint $t){$t->id();$t->string('original_name');$t->string('path',1024);$t->string('mime_type');$t->unsignedBigInteger('size');$t->timestamps();});
 }
 public function down(): void {foreach(['andarma_media','andarma_leads','andarma_testimonials','andarma_lots','andarma_developments','andarma_banners'] as $table)Schema::dropIfExists($table);Schema::table('users',fn(Blueprint $t)=>$t->dropColumn('is_andarma_admin'));}
};
