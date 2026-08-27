<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Concerns;


/**
 * Configures the payment-derived access projection on the host user model.
 *
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 */
trait CashierAccess
{
    /**
     * Casts and protects the reserved users.access projection.
     */
    public function initializeCashierAccess(): void
    {
        $this->mergeCasts( ['access' => 'array'] );
        $this->mergeGuarded( ['access'] );
        $this->setHidden( array_unique( [...$this->getHidden(), 'access'] ) );
    }
}
