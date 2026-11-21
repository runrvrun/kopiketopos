<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inactive stores (e.g. a closed outlet) take no new transactions but keep
     * their history in reports.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
