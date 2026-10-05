<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Permission;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Request;


/**
 * Base class for CMS tools that checks the required permissions.
 *
 * The same check decides if the tool is registered for the user and is
 * enforced again when the tool is executed, e.g. by the AI chat which
 * doesn't use the MCP registration.
 */
abstract class Tool extends \Laravel\Mcp\Server\Tool
{
    /**
     * Permissions the user must all have, an empty list denies access.
     *
     * @var array<int, string>
     */
    protected const PERMISSIONS = [];


    /**
     * Checks the permissions and handles the tool request.
     *
     * CMS errors are returned as structured error so the client sees the reason
     * instead of the generic error laravel/mcp returns if debug mode is off.
     *
     * @param Request $request The incoming tool request
     * @return ResponseFactory Tool response
     */
    final public function handle( Request $request ) : ResponseFactory
    {
        if( !$this->allowed( $request->user() ) ) {
            throw new \Aimeos\Cms\Exception( 'Insufficient permissions' );
        }

        try {
            return $this->run( $request );
        } catch( ModelNotFoundException $e ) {
            return Response::structured( ['error' => class_basename( $e->getModel() ) . ' not found.'] );
        } catch( \Aimeos\Cms\Exception $e ) {
            return Response::structured( ['error' => $e->getMessage()] );
        }
    }


    /**
     * Determine if the tool should be registered.
     *
     * @param Request $request The incoming request to check permissions for.
     * @return bool TRUE if the tool should be registered, FALSE otherwise.
     */
    public function shouldRegister( Request $request ) : bool
    {
        return $this->allowed( $request->user() );
    }


    /**
     * Checks if the user has all required permissions.
     *
     * @param Authenticatable|null $user Authenticated user
     * @return bool TRUE if the user has all permissions, FALSE otherwise
     */
    protected function allowed( ?Authenticatable $user ) : bool
    {
        foreach( static::PERMISSIONS as $action )
        {
            if( !Permission::can( $action, $user ) ) {
                return false;
            }
        }

        return static::PERMISSIONS !== [];
    }


    /**
     * Handles the tool request after the permissions have been checked.
     *
     * @param Request $request The incoming tool request
     * @return ResponseFactory Tool response
     */
    abstract protected function run( Request $request ) : ResponseFactory;
}
