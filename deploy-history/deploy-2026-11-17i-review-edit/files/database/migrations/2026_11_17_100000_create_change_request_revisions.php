<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** History of a pending change request: every time a reviewer edits it, the previous payload is kept here. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('change_request_revisions')) {
            return;
        }
        Schema::create('change_request_revisions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('change_request_id')->index();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->json('payload');
            $t->json('changed_fields')->nullable();
            $t->string('note', 500)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_request_revisions');
    }
};
