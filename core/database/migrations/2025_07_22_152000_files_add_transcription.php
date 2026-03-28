<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_files', function (Blueprint $table) {
            if(!Schema::hasColumn('cms_files', 'transcription')) {
                $table->json('transcription')->default('{}');
            }
        });
    }


    public function down(): void
    {
        Schema::table('cms_files', function (Blueprint $table) {
            if(Schema::hasColumn('cms_files', 'transcription')) {
                $table->dropColumn('transcription');
            }
        });
    }
};
