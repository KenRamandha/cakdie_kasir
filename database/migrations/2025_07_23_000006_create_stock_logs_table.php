<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stock_logs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->enum('type', ['in', 'out', 'adjustment']); 
            $table->integer('quantity');
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->text('notes')->nullable();
            $table->string('reference_type')->nullable(); 
            $table->unsignedBigInteger('reference_id')->nullable(); 
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            
            // Indexes
            $table->index(['product_id', 'type', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('stock_logs');
    }
};
