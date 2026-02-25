<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Console\Command;


class User extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:user
        {--disable : Disables the user as CMS editor}
        {--password= : Secret password of the account (will ask if user will be created)}
        {email : E-Mail of the user}';

    /**
     * Command description
     */
    protected $description = 'Authorization for CMS users';


    /**
     * Execute command
     */
    public function handle(): void
    {
        // @phpstan-ignore-next-line cast.string
        $email = (string) $this->argument( 'email' );
        $value = $this->option( 'disable' ) ? 0 : 0x7fffffffffffffff;

        if( ( $user = BaseUser::where( 'email', $email )->first() ) === null )
        {
            $user = (new BaseUser())->forceFill( [
                'password' => Hash::make( $this->option( 'password' ) ?: $this->secret( 'Password' ) ),
                'cmseditor' => $value,
                'email' => $email,
                'name' => $email,
            ] )->save();
        }
        else
        {
            $userdata = ['cmseditor' => $value];

            if( $this->input->hasParameterOption( '--password' ) ) {
                $userdata['password'] = Hash::make( $this->option( 'password' ) ?: $this->secret( 'Password' ) );
            }

            $user->forceFill( $userdata )->save();
        }

        if( $value ) {
            $this->info( sprintf( '  Enabled [%1$s] as CMS user', $email ) );
        } else {
            $this->info( sprintf( '  Disabled [%1$s] as CMS user', $email ) );
        }
    }
}
