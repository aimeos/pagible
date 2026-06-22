<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::table('cms_files', function (Blueprint $table) {
            if(Schema::hasColumn('cms_files', 'transcription')) {
                $table->dropColumn('transcription');
            }
        });
    }


    public function up(): void
    {
        Schema::table('cms_files', function (Blueprint $table) {
            if(!Schema::hasColumn('cms_files', 'transcription')) {
                $table->json('transcription')->default('{}');
            }
        });
    }
};
