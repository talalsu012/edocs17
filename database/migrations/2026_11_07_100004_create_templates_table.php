<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نماذج الخطابات: نص ثابت + حقول {{متغيرة}}.
 * كل تعديل = صف جديد بنفس code ورقم إصدار أعلى، حتى لا يتغير شكل الخطابات القديمة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->comment('مفتاح ثابت يجمع إصدارات النموذج');
            $table->unsignedInteger('version')->default(1);
            $table->string('name');
            $table->string('category', 50)->nullable();
            $table->string('subject_pattern')->nullable()->comment('قالب الموضوع');
            $table->longText('body_with_fields');
            $table->json('fields_json')->nullable()->comment('تعريف الحقول: الاسم، النوع، إلزامي، تعبئة تلقائية');
            $table->foreignId('default_recipient_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('allowed_for', 10)->default('both')->comment('App\Enums\TemplateAudience');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['code', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
