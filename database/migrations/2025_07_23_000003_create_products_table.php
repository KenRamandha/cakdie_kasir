<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('categories')->onDelete('restrict');
            $table->decimal('price', 12, 2); 
            $table->decimal('cost_price', 12, 2)->nullable(); 
            $table->integer('stock')->default(0);
            $table->integer('min_stock')->default(0); 
            $table->string('unit')->default('pcs'); 
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index(['category_id', 'is_active']);
            $table->index('stock');
        });
    }

    public function down(): void {
        Schema::dropIfExists('products');
    }
};