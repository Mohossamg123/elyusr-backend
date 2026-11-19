<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Article versions: before an admin saves an article, the version being replaced is kept here so it can be restored. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('blog_post_revisions')) {
            return;
        }
        Schema::create('blog_post_revisions', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 190)->index();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->json('snapshot');
            $t->string('title', 300)->nullable();
            $t->string('note', 500)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_revisions');
    }
};
