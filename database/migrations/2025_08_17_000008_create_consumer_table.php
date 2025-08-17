<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->string('customer_id')->primary(); 
            $table->string('phone')->unique(); 
            $table->string('name');
            $table->integer('purchase_count')->default(0);
            $table->timestamp('last_purchase_at')->nullable();
            $table->timestamps();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('customer_id')->nullable()->after('cashier_id');
            $table->foreign('customer_id')->references('customer_id')->on('customers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
        
        Schema::dropIfExists('customers');
    }
};
