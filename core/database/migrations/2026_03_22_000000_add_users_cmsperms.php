<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    /**
     * Old bitmask-to-name mapping (hardcoded to remain stable).
     */
    private array $bitmask = [
        0b00000000_00000000_00000000_00000001 => 'page:view',
        0b00000000_00000000_00000000_00000010 => 'page:save',
        0b00000000_00000000_00000000_00000100 => 'page:add',
        0b00000000_00000000_00000000_00001000 => 'page:drop',
        0b00000000_00000000_00000000_00010000 => 'page:keep',
        0b00000000_00000000_00000000_00100000 => 'page:purge',
        0b00000000_00000000_00000000_01000000 => 'page:publish',
        0b00000000_00000000_00000000_10000000 => 'page:move',

        0b00000000_00000000_00000001_00000000 => 'element:view',
        0b00000000_00000000_00000010_00000000 => 'element:save',
        0b00000000_00000000_00000100_00000000 => 'element:add',
        0b00000000_00000000_00001000_00000000 => 'element:drop',
        0b00000000_00000000_00010000_00000000 => 'element:keep',
        0b00000000_00000000_00100000_00000000 => 'element:purge',
        0b00000000_00000000_01000000_00000000 => 'element:publish',

        0b00000000_00000001_00000000_00000000 => 'file:view',
        0b00000000_00000010_00000000_00000000 => 'file:save',
        0b00000000_00000100_00000000_00000000 => 'file:add',
        0b00000000_00001000_00000000_00000000 => 'file:drop',
        0b00000000_00010000_00000000_00000000 => 'file:keep',
        0b00000000_00100000_00000000_00000000 => 'file:purge',
        0b00000000_01000000_00000000_00000000 => 'file:publish',
        0b00000000_10000000_00000000_00000000 => 'file:describe',

        0b00000001_00000000_00000000_00000000 => 'config:page',

        0b00000000_00000000_00000000_00000001_00000000_00000000_00000000_00000000 => 'page:metrics',
        0b00000000_00000000_00000000_00000010_00000000_00000000_00000000_00000000 => 'page:synthesize',
        0b00000000_00000000_00000000_00000100_00000000_00000000_00000000_00000000 => 'page:refine',

        0b00000000_00000000_00000000_01000000_00000000_00000000_00000000_00000000 => 'text:translate',
        0b00000000_00000000_00000000_10000000_00000000_00000000_00000000_00000000 => 'text:write',

        0b00000000_00000000_00000001_00000000_00000000_00000000_00000000_00000000 => 'audio:transcribe',

        0b00000000_00000001_00000000_00000000_00000000_00000000_00000000_00000000 => 'image:imagine',
        0b00000000_00000010_00000000_00000000_00000000_00000000_00000000_00000000 => 'image:inpaint',
        0b00000000_00000100_00000000_00000000_00000000_00000000_00000000_00000000 => 'image:isolate',
        0b00000000_00001000_00000000_00000000_00000000_00000000_00000000_00000000 => 'image:repaint',
        0b00000000_00010000_00000000_00000000_00000000_00000000_00000000_00000000 => 'image:erase',
        0b00000000_00100000_00000000_00000000_00000000_00000000_00000000_00000000 => 'image:uncrop',
        0b00000000_01000000_00000000_00000000_00000000_00000000_00000000_00000000 => 'image:upscale',
    ];


    public function up(): void
    {
        if (!Schema::hasColumn('users', 'cmsperms'))
        {
            Schema::table('users', function (Blueprint $table) {
                $table->json('cmsperms')->nullable();
            });
        }

        if (Schema::hasColumn('users', 'cmseditor'))
        {
            DB::table('users')->where('cmseditor', '>', 0)->chunkById(100, function ($users) {

                foreach ($users as $user)
                {
                    $perms = [];

                    foreach ($this->bitmask as $bit => $name)
                    {
                        if ($user->cmseditor & $bit) {
                            $perms[] = $name;
                        }
                    }

                    DB::table('users')->where('id', $user->id)->update([
                        'cmsperms' => json_encode(array_values($perms)),
                    ]);
                }
            }, 'id');

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('cmseditor');
            });
        }
    }
};
