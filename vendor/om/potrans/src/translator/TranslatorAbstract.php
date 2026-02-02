<?php

namespace potrans\translator;

abstract class TranslatorAbstract implements Translator {

	protected string $to;
	protected string $from;

	/**
	 * @param string $from
	 * @return Translator
	 */
	public function from(string $from): Translator {
		$this->from = $from;
		return $this;
	}

	/**
	 * @param string $to
	 * @return Translator
	 */
	public function to(string $to): Translator {
		$this->to = $to;
		return $this;
	}

}