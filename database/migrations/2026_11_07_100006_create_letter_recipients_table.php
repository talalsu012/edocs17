<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المستلمون: حالة مستقلة لكل مستلم (جهة أو شخص)، مع رقم وارد خاص بكل جهة.
 * الإحالة = صف جديد بدور referred يشير إلى صف المستلم الذي أحال.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained('letters')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete()
                ->comment('مستلم شخصي (رد على طلب موظف)');
            $table->string('role', 20)->default('to')->comment('App\Enums\RecipientRole');
            $table->foreignId('referred_from_id')->nullable()->constrained('letter_recipients')->nullOnDelete();
            $table->string('referral_note', 500)->nullable()->comment('للإفادة، للاطلاع والإجراء...');

            $table->string('incoming_no', 100)->nullable();
            $table->unsignedSmallInteger('incoming_year')->nullable();
            $table->unsignedInteger('incoming_seq')->nullable();

            $table->string('status', 20)->default('pending')->index()->comment('App\Enums\RecipientStatus');
            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['unit_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['letter_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_recipients');
    }
};
