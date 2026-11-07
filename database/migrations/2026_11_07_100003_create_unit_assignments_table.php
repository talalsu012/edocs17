<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * التكليفات: من يمثل الجهة ومتى. الصندوق ملك الجهة، والتكليف يحدد من يملك مفتاحه.
 * التكليف النشط: start_date <= اليوم و (end_date فارغ أو >= اليوم).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 20)->default('original')->comment('App\Enums\AssignmentKind');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('decision_no', 100)->nullable()->comment('رقم قرار النقل/التكليف');
            $table->date('decision_date')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['unit_id', 'start_date', 'end_date']);
            $table->index(['user_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_assignments');
    }
};
