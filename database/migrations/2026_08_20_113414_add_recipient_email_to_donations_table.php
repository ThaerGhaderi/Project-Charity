<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
       public function up()
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->string('recipient_email')->nullable()->after('on_behalf_of');
        });
    }

    /**
     * Reverse the migrations.
     */
     public function down()
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropColumn('recipient_email');
        });
    }
};
