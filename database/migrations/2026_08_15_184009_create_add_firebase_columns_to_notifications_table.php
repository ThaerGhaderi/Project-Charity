<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // ✅ أضف الأعمدة الجديدة بعد is_read
            $table->boolean('firebase_sent')->default(false)->after('is_read');
            $table->timestamp('firebase_sent_at')->nullable()->after('firebase_sent');
            $table->text('firebase_error')->nullable()->after('firebase_sent_at');
            $table->string('priority')->default('normal')->after('firebase_error');

            // ✅ أضف مؤشرات للبحث السريع
            $table->index('firebase_sent');
            $table->index(['user_id', 'firebase_sent']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn([
                'firebase_sent',
                'firebase_sent_at',
                'firebase_error',
                'priority'
            ]);
        });
    }
};
