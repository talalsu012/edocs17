<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** تنبيهات داخل النظام (قناة database في Laravel) + منع تكرار التذكير لكل مستلم. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::table('letter_recipients', function (Blueprint $table) {
            $table->timestamp('due_reminded_at')->nullable()->after('archived_at');
            $table->timestamp('overdue_notified_at')->nullable()->after('due_reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('letter_recipients', function (Blueprint $table) {
            $table->dropColumn(['due_reminded_at', 'overdue_notified_at']);
        });
        Schema::dropIfExists('notifications');
    }
};
