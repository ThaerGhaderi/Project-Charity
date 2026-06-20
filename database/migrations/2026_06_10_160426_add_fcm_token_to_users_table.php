<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'fcm_token')) {
                $table->text('fcm_token')->nullable()->after('remember_token');
            }
            if (!Schema::hasColumn('users', 'device_type')) {
                $table->string('device_type')->nullable()->after('fcm_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['fcm_token', 'device_type']);
        });
    }
};