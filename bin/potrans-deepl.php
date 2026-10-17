<?php

/**
 * Custom DeepL translator for potrans, used by "composer translate":
 *
 *   vendor/bin/potrans deepl --translator=bin/potrans-deepl.php ...
 *
 * Unlike the potrans default, it escapes texts for XML only (htmlentities() creates
 * entities like "&eacute;" which DeepL rejects), uses DeepL codes for regional
 * locales and translates prefixed element types like "brew::cafe" by their name.
 *
 * Included by potrans in the scope of DeepLTranslatorCommand::execute() where
 * $apikey, $potTrans and $input are available.
 */

namespace Aimeos\Cms\Potrans;

use Gettext\Translation;
use Gettext\Translations;
use potrans\translator\TranslatorAbstract;


class DeepLTranslator extends TranslatorAbstract
{
    // DeepL target codes of the locales whose upper case name DeepL doesn't accept
    private const CODES = ['ZH-TW' => 'ZH-HANT'];


    public function __construct(
        private \DeepL\Translator $translator,
        private ?Translations $pot = null,
        private ?string $regex = null
    ) {
    }


    public function getTranslation(Translation $sentence): string
    {
        $text = $this->pot?->find($sentence->getContext(), $sentence->getOriginal())?->getTranslation() ?: $sentence->getOriginal();
        $type = $sentence->getContext() === 'st' && str_contains($text, '::');

        if ($type) {
            // element type without label, e.g. "brew::cafe" is translated as "cafe"
            $text = str_replace(['-', '_'], ' ', substr($text, strrpos($text, '::') + 2));
        }

        $text = htmlspecialchars($text, ENT_XML1 | ENT_NOQUOTES, 'UTF-8');
        $text = $this->regex ? preg_replace('/(' . $this->regex . ')/', '<keep>$1</keep>', $text) : $text;

        $response = $this->translator->translateText($text, $this->from, self::CODES[$this->to] ?? $this->to, [
            'tag_handling' => 'xml',
            'ignore_tags' => 'keep',
        ]);

        $result = htmlspecialchars_decode(preg_replace('/<\/?keep>/i', '', $response->text), ENT_XML1 | ENT_NOQUOTES);

        return $type ? mb_strtoupper(mb_substr($result, 0, 1)) . mb_substr($result, 1) : $result;
    }
}


return new DeepLTranslator(new \DeepL\Translator($apikey), $potTrans, $input->getOption('ignore'));
