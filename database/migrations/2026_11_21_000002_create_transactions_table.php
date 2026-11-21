<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Invoice number: Ymd + 5-digit daily sequence. Not unique in v1 data
            // (duplicates and NULLs exist), so indexed only.
            $table->unsignedBigInteger('key')->nullable()->index();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            // Customer name as typed on this order (kept even if the customer record changes).
            $table->string('customer', 100)->nullable();
            $table->unsignedInteger('total_item');
            $table->unsignedInteger('total_price'); // gross, before discount/DP
            $table->unsignedInteger('discount')->nullable();
            $table->unsignedInteger('downpayment')->nullable();
            // NULL = ongoing, 'Paid' = paid
            $table->string('status', 10)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
