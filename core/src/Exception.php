<?php

namespace Aimeos\Cms;

use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;


class Exception extends \Exception implements ClientAware, ProvidesExtensions
{
	/** @var array<string, mixed> */
	protected array $details = [];


	public function __construct(
		string $message = '',
		int $code = 0,
		?\Throwable $previous = null,
	) {
		parent::__construct( $message, $code, $previous );
	}


	/** @param array<string, mixed> $details */
	public function details( array $details ) : self
	{
		$this->details = $details;
		return $this;
	}


	public function getExtensions() : array
	{
		return $this->details;
	}


	public function isClientSafe() : bool
	{
		return true;
	}
}
