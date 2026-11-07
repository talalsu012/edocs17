<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الخطاب نفسه. مرتبط بالجهة (sender_unit_id) لا بالشخص، مع حفظ الشخص الفعلي للمساءلة.
 * origin_unit_id: الجهة التي نشأ منها الخطاب (جهة المرسل، أو جهة عمل الموظف في الطلب الشخصي)،
 * وعليها تُحسب رؤية المدير المباشر والأجداد.
 * الخطاب المرسَل لا يُحذف أبدًا؛ يُلغى مع ذكر السبب.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->string('outgoing_no', 100)->nullable()->unique()->comment('مثل جك/م ب ش/2026/0153 — يُعطى عند الإرسال');
            $table->unsignedSmallInteger('outgoing_year')->nullable();
            $table->unsignedInteger('outgoing_seq')->nullable();
            $table->string('type', 20)->index()->comment('App\Enums\LetterType');
            $table->string('subject');
            $table->longText('body')->nullable();
            $table->string('priority', 20)->default('normal')->comment('App\Enums\Priority');
            $table->boolean('is_confidential')->default(false);
            $table->date('due_date')->nullable()->comment('موعد الرد المطلوب');

            $table->foreignId('sender_unit_id')->nullable()->constrained('units')->restrictOnDelete()
                ->comment('فارغ في الطلب الشخصي');
            $table->foreignId('origin_unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->restrictOnDelete()
                ->comment('الشخص الفعلي الذي أنشأ الخطاب');
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('is_acting')->default(false)->comment('صدر بالإنابة');

            $table->foreignId('parent_letter_id')->nullable()->constrained('letters')->restrictOnDelete()
                ->comment('رد على / إلحاقًا لخطاب سابق');
            $table->foreignId('template_id')->nullable()->constrained('templates')->nullOnDelete();
            $table->json('template_values')->nullable();

            $table->string('status', 20)->default('draft')->index()->comment('App\Enums\LetterStatus');
            $table->timestamp('sent_at')->nullable()->index();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['origin_unit_id', 'status']);
            $table->index(['sender_unit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letters');
    }
};
