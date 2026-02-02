<?php

namespace potrans\translator;

use DeepL\DeepLException;
use Gettext\Translation;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * There are two global variable $input and $output
 *
 * Usage:
 *
 * ./bin/potrans deepl ./tests/example-cs_CZ.po ~/Downloads --apikey=123456 --translator=src/translator/DeepLTranslatorEscaped.php --no-cache -vvv
 *
 * @var InputInterface $input
 * @var OutputInterface $output
 * @see \potrans\commands\DeepLTranslatorCommand for more information
 */
class DeepLTranslatorEscaped extends TranslatorAbstract {

	private \DeepL\Translator $translator;

	public function __construct(\DeepL\Translator $translator) {
		$this->translator = $translator;
	}

	/**
	 * @throws DeepLException
	 */
	public function getTranslation(Translation $sentence): string {
		$response = $this->translator->translateText(
			preg_replace('/\$([^$]+)\$/i', '<keep>$1</keep>', $sentence->getOriginal()),
			$this->from,
			$this->to,
			[
				'tag_handling' => 'xml',
				'ignore_tags' => 'keep',
			]
		);

		return preg_replace('/<\/?keep>/i', '$', $response->text);
	}
}

$apikey = (string) $input->getOption('apikey');

/**
 * Return custom translator instance
 */
return new DeepLTranslatorEscaped(
	new \DeepL\Translator($apikey),
);