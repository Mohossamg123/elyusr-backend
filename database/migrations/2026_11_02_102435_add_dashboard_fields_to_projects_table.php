<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            
            if (!Schema::hasColumn('projects', 'hide_from_audience')) {
                $table->boolean('hide_from_audience')->default(false);
            }

            if (!Schema::hasColumn('projects', 'donation_available')) {
                $table->boolean('donation_available')->default(true);
            }

            if (!Schema::hasColumn('projects', 'description_en')) {
                $table->longText('description_en')->nullable();
            }

            if (!Schema::hasColumn('projects', 'content_ar')) {
                $table->longText('content_ar')->nullable();
            }

            if (!Schema::hasColumn('projects', 'content_en')) {
                $table->longText('content_en')->nullable();
            }

            if (!Schema::hasColumn('projects', 'benefits_ar')) {
                $table->json('benefits_ar')->nullable();
            }

            if (!Schema::hasColumn('projects', 'benefits_en')) {
                $table->json('benefits_en')->nullable();
            }

            if (!Schema::hasColumn('projects', 'meta_title_ar')) {
                $table->string('meta_title_ar', 70)->nullable();
            }

            if (!Schema::hasColumn('projects', 'meta_title_en')) {
                $table->string('meta_title_en', 70)->nullable();
            }

            if (!Schema::hasColumn('projects', 'meta_description_ar')) {
                $table->string('meta_description_ar', 170)->nullable();
            }

            if (!Schema::hasColumn('projects', 'meta_description_en')) {
                $table->string('meta_description_en', 170)->nullable();
            }

            if (!Schema::hasColumn('projects', 'keywords_ar')) {
                $table->text('keywords_ar')->nullable();
            }

            if (!Schema::hasColumn('projects', 'keywords_en')) {
                $table->text('keywords_en')->nullable();
            }

            if (!Schema::hasColumn('projects', 'canonical_url')) {
                $table->string('canonical_url', 2048)->nullable();
            }

            if (!Schema::hasColumn('projects', 'schema_type')) {
                $table->string('schema_type')->nullable();
            }

            if (!Schema::hasColumn('projects', 'x_card_type')) {
                $table->string('x_card_type')->nullable();
            }

            if (!Schema::hasColumn('projects', 'noindex')) {
                $table->boolean('noindex')->default(false);
            }

            if (!Schema::hasColumn('projects', 'nofollow')) {
                $table->boolean('nofollow')->default(false);
            }

            if (!Schema::hasColumn('projects', 'geo_region')) {
                $table->string('geo_region')->nullable();
            }
            

            if (!Schema::hasColumn('projects', 'geo_placename')) {
                $table->string('geo_placename')->nullable();
            }

            if (!Schema::hasColumn('projects', 'geo_position')) {
                $table->string('geo_position')->nullable();
            }

            if (!Schema::hasColumn('projects', 'geo_position_label')) {
                $table->string('geo_position_label')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $columns = [
                'hide_from_audience',
                'donation_available',
                'description_en',
                'content_ar',
                'content_en',
                'benefits_ar',
                'benefits_en',
                'meta_title_ar',
                'meta_title_en',
                'meta_description_ar',
                'meta_description_en',
                'keywords_ar',
                'keywords_en',
                'canonical_url',
                'schema_type',
                'x_card_type',
                'noindex',
                'nofollow',
                'geo_region',
                'geo_placename',
                'geo_position',
                'geo_position_label',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('projects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};