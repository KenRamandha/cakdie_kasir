<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('sale_id');
            $table->foreign('sale_id')->references('code')->on('sales')->onDelete('restrict');
            $table->string('product_id');
            $table->foreign('product_id')->references('code')->on('products')->onDelete('restrict');;
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2); 
            $table->decimal('total_price', 12, 2); 
            $table->decimal('discount', 12, 2)->default(0);
            $table->timestamps();
            $table->index(['sale_id', 'product_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('sale_items');
    }
};