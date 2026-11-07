<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** وسم بيانات التدريب لتُحذف دفعة واحدة قبل التشغيل الفعلي (php artisan edocs:training --purge). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->boolean('is_training')->default(false)->index()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropIndex(['is_training']);
            $table->dropColumn('is_training');
        });
    }
};
