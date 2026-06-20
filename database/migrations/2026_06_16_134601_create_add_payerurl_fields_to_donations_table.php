<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            if (!Schema::hasColumn('donations', 'payment_gateway')) {
                $table->string('payment_gateway')->default('local')->after('payment_method');
            }
            if (!Schema::hasColumn('donations', 'gateway_payment_id')) {
                $table->string('gateway_payment_id')->nullable()->after('payment_gateway');
            }
            if (!Schema::hasColumn('donations', 'gateway_status')) {
                $table->string('gateway_status')->nullable()->after('gateway_payment_id');
            }
            if (!Schema::hasColumn('donations', 'crypto_currency')) {
                $table->string('crypto_currency')->nullable()->after('gateway_status');
            }
            if (!Schema::hasColumn('donations', 'crypto_amount')) {
                $table->string('crypto_amount')->nullable()->after('crypto_currency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropColumn([
                'payment_gateway',
                'gateway_payment_id',
                'gateway_status',
                'crypto_currency',
                'crypto_amount'
            ]);
        });
    }
};