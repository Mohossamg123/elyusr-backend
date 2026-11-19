<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $t) {
            if (! Schema::hasColumn('donations', 'reviewed_at')) {
                $t->timestamp('reviewed_at')->nullable();
            }
            if (! Schema::hasColumn('donations', 'reviewed_by')) {
                $t->unsignedBigInteger('reviewed_by')->nullable();
            }
            if (! Schema::hasColumn('donations', 'deleted_at')) {
                $t->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $t) {
            $t->dropColumn(['reviewed_at', 'reviewed_by', 'deleted_at']);
        });
    }
};
