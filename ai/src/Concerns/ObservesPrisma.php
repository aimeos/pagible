<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\Events\Generated;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Watch as CmsWatch;
use Aimeos\Prisma\Values\Observation;
use Illuminate\Support\Facades\Auth;


/**
 * Builds Prisma observers that record AI provider calls as Generated audit events.
 *
 * Shared by the AI mutations, MCP tools and the chat controller: pass observer() to Prisma's
 * observe() method and every provider operation (success or failure) is dispatched as a Generated
 * event with its operation, provider/model, duration and token usage.
 */
trait ObservesPrisma
{
    /**
     * Returns a Prisma observer callback that dispatches a Generated audit event per operation.
     *
     * The editor and tenant are captured now (editor resolved from the authenticated user when
     * null) so they are correct when the callback fires after the provider call completes.
     *
     * @param string|null $editor Editor identifier; resolved from the authenticated user when null
     * @param string|null $mutation Operation key override
     * @return \Closure(Observation): void Observer for Prisma::...->observe()
     */
    protected function observer( ?string $editor = null, ?string $mutation = null ) : \Closure
    {
        $editor ??= Utils::editor( Auth::user() );
        $tenant = Tenancy::value();

        return function( Observation $observation ) use ( $editor, $tenant, $mutation ) {
            CmsWatch::dispatchWhen( null, fn() => new Generated(
                mutation: $mutation ?? $observation->operation,
                provider: $observation->provider,
                model: $observation->model ?? '',
                durationMs: $observation->durationMs,
                editor: $editor,
                tenant: $tenant,
                success: $observation->error === null,
                error: $observation->error?->getMessage(),
                extra: array_filter( [
                    'inputTokens' => $observation->usage?->promptTokens(),
                    'outputTokens' => $observation->usage?->completionTokens(),
                ], fn( $tokens ) => $tokens !== null ),
            ) );
        };
    }
}
