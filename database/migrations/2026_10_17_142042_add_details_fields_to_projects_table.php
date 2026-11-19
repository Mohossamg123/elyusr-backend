<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique();
            $table->string('name_en')->nullable();
            $table->text('short_description')->nullable();
            $table->text('short_description_en')->nullable();
            $table->decimal('starting_price', 10, 2)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->string('cover_image')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['slug']);

            $table->dropColumn([
                'slug',
                'name_en',
                'short_description',
                'short_description_en',
                'starting_price',
                'display_order',
                'cover_image',
            ]);
        });
    }
};