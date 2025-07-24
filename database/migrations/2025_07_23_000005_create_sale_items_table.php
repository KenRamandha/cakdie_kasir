<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); 
            $table->foreignId('sale_id')->constrained('sales')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2); 
            $table->decimal('total_price', 12, 2); 
            $table->decimal('discount', 12, 2)->default(0);
            $table->timestamps();
            
            // Indexes
            $table->index(['sale_id', 'product_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('sale_items');
    }
};