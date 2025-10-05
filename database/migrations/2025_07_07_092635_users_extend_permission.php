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
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('cmseditor')->default(0)->change();
        });
    }


    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('cmseditor')->change();
        });
    }
};
