<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('print_logs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('sale_id')->constrained('sales')->onDelete('cascade');
            $table->foreignId('printed_by')->constrained('users')->onDelete('restrict');
            $table->timestamp('printed_at')->useCurrent();
            $table->string('printer_name')->nullable();
            $table->enum('print_type', ['receipt', 'invoice'])->default('receipt');
            $table->boolean('is_reprint')->default(false);
            $table->timestamps();
            $table->index(['sale_id', 'printed_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('print_logs');
    }
};