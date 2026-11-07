<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * التعميم يُعرَّف بأهدافه (الكل / فرع / نوع جهة / كل الموظفين) دون إنشاء آلاف الصفوف،
 * ويُسجَّل من ضغط «علمت» في broadcast_reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained('letters')->cascadeOnDelete();
            $table->string('scope', 20)->comment('App\Enums\BroadcastScope');
            $table->foreignId('root_unit_id')->nullable()->constrained('units')->restrictOnDelete()
                ->comment('جذر الفرع عند scope=subtree');
            $table->string('unit_type', 30)->nullable()->comment('عند scope=unit_type');
            $table->timestamps();

            $table->index(['scope', 'root_unit_id']);
        });

        Schema::create('broadcast_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained('letters')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete()
                ->comment('الجهة التي كان يمثلها عند الاطلاع');
            $table->timestamp('read_at');

            $table->unique(['letter_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_reads');
        Schema::dropIfExists('broadcast_targets');
    }
};
