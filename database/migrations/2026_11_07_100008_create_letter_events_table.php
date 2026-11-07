<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجل الحركة والتدقيق (لا يُعدَّل ولا يُحذف):
 * إنشاء، اعتماد، إرسال، فتح، استلام، إحالة، توجيه، إنجاز، حفظ، إلغاء...
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained('letters')->restrictOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('letter_recipients')->nullOnDelete();
            $table->string('action', 30)->index();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['letter_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_events');
    }
};
