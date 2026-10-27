<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    /**
     * Adds the writable languages of the users, NULL is unrestricted.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'cmslangs'))
        {
            Schema::table('users', function (Blueprint $table) {
                $table->json('cmslangs')->nullable();
            });
        }
    }
};
