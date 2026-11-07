<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * شجرة الجهات (إدارات، كليات، أقسام، مراكز).
 * path يُحسب تلقائيًا في الموديل بالشكل /1/4/17/ ويُستخدم لحساب الرؤية الهرمية.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->unique()->comment('الرمز المستخدم في الترقيم مثل م ب ش');
            $table->string('type', 30)->index()->comment('App\Enums\UnitType');
            $table->foreignId('parent_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->string('path', 500)->default('/')->index()->comment('مسار الجهة في الشجرة /1/4/17/');
            $table->unsignedTinyInteger('depth')->default(0);
            $table->string('head_title')->nullable()->comment('صفة رئيس الجهة: العميد، المدير، رئيس القسم');
            $table->boolean('can_broadcast_all')->default(false)->comment('مخوّلة بالتعميم على كل الجامعة');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
