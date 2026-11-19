
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_page_sections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('program_id')
                ->constrained('programs')
                ->cascadeOnDelete();

            $table->string('section_key', 50);

            $table->string('title_ar')->nullable();
            $table->string('title_en')->nullable();

            $table->string('subtitle_ar')->nullable();
            $table->string('subtitle_en')->nullable();

            $table->json('content_ar')->nullable();
            $table->json('content_en')->nullable();

            $table->json('settings')->nullable();

            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(
                ['program_id', 'section_key'],
                'program_page_sections_program_key_unique'
            );

            $table->index(['program_id', 'is_enabled', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_page_sections');
    }
};
