<?php

namespace potrans\translator;

use Gettext\Translation;

interface Translator {

	/**
	 * @param string $from
	 * @return Translator
	 */
	public function from(string $from): Translator;

	/**
	 * @param string $to
	 * @return Translator
	 */
	public function to(string $to): Translator;

	/**
	 * @param Translation $sentence
	 * @return string
	 */
	public function getTranslation(Translation $sentence): string;
}