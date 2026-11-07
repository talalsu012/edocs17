<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حقول الموظف: الرقم الوظيفي، الهاتف، جهة العمل كموظف (home_unit)، مدير النظام.
 * الحقل name الافتراضي في Laravel يُستخدم كالاسم الكامل.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_no', 30)->nullable()->unique()->after('name');
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('job_title')->nullable()->after('phone');
            $table->foreignId('home_unit_id')->nullable()->after('job_title')
                ->constrained('units')->nullOnDelete()->comment('جهة عمل الموظف');
            $table->boolean('is_admin')->default(false)->after('home_unit_id');
            $table->boolean('is_active')->default(true)->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('home_unit_id');
            $table->dropUnique(['employee_no']);
            $table->dropColumn(['employee_no', 'phone', 'job_title', 'is_admin', 'is_active']);
        });
    }
};
