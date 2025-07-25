<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('cash_received', 12, 2)->nullable();
            $table->decimal('change_amount', 12, 2)->nullable();
            $table->enum('payment_method', ['cash', 'card', 'transfer'])->default('cash');
            $table->text('notes')->nullable();
            $table->string('cashier_id');
            $table->foreign('cashier_id')->references('user_id')->on('users')->onDelete('restrict');
            $table->timestamp('transaction_date')->useCurrent();
            $table->timestamps();
            $table->index(['cashier_id', 'transaction_date']);
            $table->index('transaction_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
