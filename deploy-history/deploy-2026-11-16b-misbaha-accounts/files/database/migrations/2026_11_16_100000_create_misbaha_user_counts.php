<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('misbaha_user_counts')) {
            Schema::create('misbaha_user_counts', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->index();
                $t->unsignedBigInteger('room_id')->default(0); // 0 = the general misbaha
                $t->date('day');
                $t->unsignedBigInteger('n')->default(0);
                $t->timestamps();
                $t->unique(['user_id', 'room_id', 'day']);
            });
        }
        if (! Schema::hasTable('misbaha_guest_days')) {
            Schema::create('misbaha_guest_days', function (Blueprint $t) {
                $t->date('day');
                $t->string('scope', 8); // general | rooms
                $t->unsignedBigInteger('n')->default(0);
                $t->primary(['day', 'scope']);
            });
        }
        if (! Schema::hasColumn('misbaha_rooms', 'user_id')) {
            Schema::table('misbaha_rooms', function (Blueprint $t) {
                $t->unsignedBigInteger('user_id')->nullable()->after('creator_ip_hash')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('misbaha_user_counts');
        Schema::dropIfExists('misbaha_guest_days');
    }
};
