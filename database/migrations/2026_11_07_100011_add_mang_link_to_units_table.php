<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربط الكليات والأقسام في edocs بترميزاتها في قاعدة الموظفين mang (employee_settings).
 * الجهات الإدارية التي لا توجد في mang تبقى بلا ربط.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('mang_group', 30)->nullable()->after('type')->comment('faculty | departments');
            $table->string('mang_code', 30)->nullable()->after('mang_group')->comment('employee_settings.code');
            $table->unique(['mang_group', 'mang_code']);
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique(['mang_group', 'mang_code']);
            $table->dropColumn(['mang_group', 'mang_code']);
        });
    }
};
