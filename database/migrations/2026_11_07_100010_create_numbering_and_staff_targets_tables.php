<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // تسلسل أرقام الصادر والوارد لكل جهة ولكل سنة
        Schema::create('numbering', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('direction', 3)->comment('out | in');
            $table->unsignedInteger('last_no')->default(0);
            $table->timestamps();

            $table->unique(['unit_id', 'year', 'direction']);
        });

        // الجهات التي يحق للموظف العادي مراسلتها (الموارد البشرية، الشؤون المالية...)
        Schema::create('staff_allowed_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->unique()->constrained('units')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_allowed_targets');
        Schema::dropIfExists('numbering');
    }
};
