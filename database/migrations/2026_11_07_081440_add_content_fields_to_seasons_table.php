<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
  public function up(): void
{
    Schema::table('seasons', function (Blueprint $table) {
        $table->string('slug')->nullable()->unique();

        $table->string('badge_ar')->nullable();
        $table->string('badge_en')->nullable();

        $table->string('donate_url', 2048)->nullable();

        $table->json('pricing')->nullable();
        $table->json('items')->nullable();

        $table->string('meta_title_ar')->nullable();
        $table->string('meta_title_en')->nullable();

        $table->text('meta_description_ar')->nullable();
        $table->text('meta_description_en')->nullable();
    });
}

public function down(): void
{
    Schema::table('seasons', function (Blueprint $table) {
        $table->dropUnique(['slug']);

        $table->dropColumn([
            'slug',
            'badge_ar',
            'badge_en',
            'donate_url',
            'pricing',
            'items',
            'meta_title_ar',
            'meta_title_en',
            'meta_description_ar',
            'meta_description_en',
        ]);
    });
}


};
