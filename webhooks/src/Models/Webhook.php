<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Concerns\HasUuids;
use Aimeos\Cms\Concerns\Tenancy;
use Illuminate\Database\Eloquent\Model;


/**
 * Tenant-scoped webhook subscription.
 *
 * @property string $id
 * @property string $tenant_id
 * @property bool $status
 * @property int $revision
 * @property int $failures
 * @property string $url
 * @property string $secret
 * @property list<string> $events
 * @property array<string, mixed>|null $last_error
 * @property string $editor
 * @method static \Illuminate\Database\Eloquent\Builder<static> withoutTenancy()
 */
class Webhook extends Model
{
    use HasUuids;
    use Tenancy;

    protected $table = 'cms_webhooks';
    protected $guarded = ['id', 'tenant_id', 'secret', 'failures', 'revision', 'last_error'];
    protected $hidden = ['url', 'secret', 'revision'];
    protected $appends = ['endpoint'];


    /**
     * @return array<string, string>
     */
    protected function casts() : array
    {
        return [
            'status' => 'boolean',
            'revision' => 'integer',
            'failures' => 'integer',
            'url' => 'encrypted',
            'secret' => 'encrypted',
            'events' => 'array',
            'last_error' => 'encrypted:array',
        ];
    }


    public function getConnectionName() : string
    {
        return config( 'cms.db', 'sqlite' );
    }


    public function getEndpointAttribute() : string
    {
        try {
            $parts = parse_url( $this->url );
            $scheme = strtolower( (string) ( $parts['scheme'] ?? 'https' ) );
            $host = strtolower( (string) ( $parts['host'] ?? '' ) );
            $port = isset( $parts['port'] ) ? ':' . $parts['port'] : '';

            return $host === '' ? '[invalid endpoint]' : $scheme . '://' . $host . $port . '/…';
        } catch( \Throwable ) {
            return '[invalid endpoint]';
        }
    }
}
