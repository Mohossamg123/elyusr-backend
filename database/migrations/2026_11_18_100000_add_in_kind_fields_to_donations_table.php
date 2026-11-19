<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// In-kind donations (furniture, appliances, clothes...) are collected by the pickup agent.
// Safe to run twice: every column is added only if it does not exist yet.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            if (! Schema::hasColumn('donations', 'is_in_kind')) {
                $table->boolean('is_in_kind')->default(false)->after('amount');
            }

            if (! Schema::hasColumn('donations', 'in_kind_category')) {
                $table->string('in_kind_category', 100)->nullable()->after('is_in_kind');
            }

            if (! Schema::hasColumn('donations', 'in_kind_description')) {
                $table->text('in_kind_description')->nullable()->after('in_kind_category');
            }
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            foreach (['in_kind_description', 'in_kind_category', 'is_in_kind'] as $column) {
                if (Schema::hasColumn('donations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
