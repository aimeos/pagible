<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Database\Factories\HasCmsPermissions;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;


/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    use HasCmsPermissions;

    protected $model = User::class;


    /**
     * @return array<string, mixed>
     */
    public function definition() : array
    {
        return ['name' => 'Test', 'email' => uniqid() . '@example.com', 'password' => 'secret'];
    }
}
