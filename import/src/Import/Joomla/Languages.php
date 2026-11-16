<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;

use Aimeos\Cms\Concerns\Joomla\JoomlaRemembers;
use Aimeos\Cms\Import\Variants;
use Aimeos\Cms\Utils;
use Illuminate\Support\Collection;


/**
 * Maps the Joomla language tags to the languages of the pages and groups the associated articles, categories or menu items as translations.
 */
class Languages
{
    use JoomlaRemembers;

    /** Joomla language tag of the articles, categories and menu items for all languages */
    public const ALL = '*';
    /** Language tag of the Joomla site if none is configured */
    public const DEFAULT_TAG = 'en-GB';

    /** @var array<string, array{lang: string, domain: string}|null> Languages of the languages table and the options by Joomla language tag */
    protected array $languages = [];
    /** @var array<string, array{lang: string, domain: string}|null> Languages guessed from the other Joomla language tags */
    protected array $guessed = [];
    /** @var array<string, string> Joomla language tags of the --language options by their lower case language code, never guessed for other tags */
    protected array $optionCodes = [];
    /** @var list<string> Joomla language tags of the usable content languages in their order */
    protected array $tags = [];
    /** @var array<string, string> Meta descriptions of the usable content languages by Joomla language tag */
    protected array $descriptions = [];
    /** If languages missing from the languages table and the options are guessed, not with language filter and a languages table */
    protected bool $guesses;


    /**
     * Initializes the languages from the languages table and the --language options, which take precedence.
     *
     * Unpublished languages and the ones guests can't use are unknown because Joomla doesn't show their articles
     * unless they are added by the options. The language code is the part of the Joomla language tag, e.g. "zh"
     * for "zh-CN", or the whole tag if the URL code of the language is the tag too, e.g. "pt-BR" for "pt-br".
     * If several languages have the same part, e.g. "en-GB" and "en-US", only the one whose URL code is the part
     * or otherwise the first one gets the part, the others their whole tags because Joomla shows them on own sites.
     *
     * @param Collection<int, \stdClass> $rows Records of the languages table
     * @param array<mixed> $options Values of the --language option like "de-DE:de[:url]"
     * @param string $lang Language code of the imported pages
     * @param (\Closure(\stdClass): bool)|null $usable Returns if guests can use the language, e.g. its access level and its installed site language
     * @param bool $filter TRUE if the language filter of the site is enabled, so tags missing from a non-empty languages table are unknown
     * @throws \InvalidArgumentException If an option value is invalid or the language code of an option is used by another language tag too
     */
    public function __construct( Collection $rows, array $options, protected string $lang, ?\Closure $usable = null, bool $filter = false )
    {
        // with language filter, Joomla only shows the content of the content languages and for all languages,
        // but sites without languages table can't be checked
        $this->guesses = !$filter || $rows->isEmpty();
        $available = $rows->filter( fn( $row ) => $this->available( $row, $usable ) );
        // unavailable languages are unknown even if their language part is valid
        $unknown = $rows->diffKeys( $available )->mapWithKeys( fn( $row ) => [(string) $row->lang_code => null] )->all();

        $this->tags = array_values( array_unique( $available->map( fn( $row ) => (string) $row->lang_code )->all() ) );
        $this->descriptions = $available->mapWithKeys( fn( $row ) => [(string) $row->lang_code => Value::string( $row->metadesc ?? null )] )->all();
        $mapped = Variants::options( $options, 'Joomla' );
        $this->languages = $mapped + $unknown + $this->variants( $this->codes( $available ) );
        $this->unique( array_map( 'strval', array_keys( $mapped ) ) );

        foreach( $mapped as $tag => $language ) {
            $this->optionCodes[strtolower( $language['lang'] )] = (string) $tag;
        }
    }


    /**
     * Returns the Joomla language tags of the published content languages guests can use.
     *
     * @return list<string> Joomla language tags in the order of the content languages
     */
    public function tags(): array
    {
        return $this->tags;
    }


    /**
     * Returns the meta description of the published content language guests can use.
     *
     * @param string|null $tag Joomla language tag or NULL if there's no content language
     * @return string Meta description or an empty string if there's none or the language isn't usable
     */
    public function description( ?string $tag ): string
    {
        return $tag !== null ? $this->descriptions[$tag] ?? '' : '';
    }


    /**
     * Groups associated articles, categories or menu items in other languages as translations of their source.
     *
     * The source of an association is the one in the import language or for all languages ("*").
     * Associations without a source are kept as pages. If several associated rows are in the same
     * language, only the one with the lowest ID is used as translation, the others aren't translations
     * and are imported as pages of their own like the rows of associations without a source. They are
     * returned as duplicates, so they can be reported, e.g. if "de-AT" and "de-CH" both get "de".
     * Rows which are imported as pages anyway, e.g. the menu items of the imported menu, are never
     * translations, so the next row in their language is used instead.
     *
     * @param array<int|string, string> $tags Language tags of the published associated articles, categories or menu items by ID
     * @param array<int|string, array<int, int|string>> $associations Associated IDs grouped by association
     * @param array<int|string, mixed> $pages IDs of the rows imported as pages themselves as keys, which aren't translations of other rows
     * @return TranslationGroups IDs of the translations by source ID, the unknown language tags and the duplicates of skipped translations
     */
    public function group( array $tags, array $associations, array $pages = [] ): TranslationGroups
    {
        $translations = [];
        $duplicates = [];
        $unknown = [];

        foreach( $associations as $ids )
        {
            $ids = array_filter( $ids, fn( $id ) => isset( $tags[$id] ) );
            sort( $ids );

            // the first one in the imported language or for all languages is the source of the translations,
            // the other ones in these languages aren't translations and stay pages of their own
            $sources = array_values( array_filter( $ids, fn( $id ) => $this->source( $tags[$id] ) ) );

            if( $sources === [] ) {
                continue;
            }

            // each language has one variant, so only the first translation in the language is used
            $langs = [];

            foreach( array_diff( $ids, $sources ) as $id )
            {
                if( isset( $pages[$id] ) ) {
                    continue;
                }

                $tag = $tags[$id];

                if( !( $language = $this->language( $tag ) ) ) {
                    $unknown[$tag] = $tag;
                    continue;
                }

                if( isset( $langs[$language['lang']] ) )
                {
                    $duplicates[$id] = ['lang' => $language['lang'], 'tag' => $tag, 'used' => $langs[$language['lang']]];
                    continue;
                }

                $langs[$language['lang']] = $id;
                $translations[$sources[0]][] = $id;
            }
        }

        return new TranslationGroups( $translations, $unknown, $duplicates );
    }


    /**
     * Returns the language of a Joomla language tag.
     *
     * Unpublished languages are unknown. Tags which aren't in the languages table or the --language options use
     * their language part, e.g. "de" for "de-DE". With language filter, they are unknown too if the languages table
     * isn't empty because Joomla only shows the content of its content languages then, e.g. not of "en-AU" if only
     * "en-GB" exists. Tags whose language part is the code of a --language option of another tag aren't guessed
     * either, e.g. not "de" for "de-DE" with "--language=de-AT:de", because their translations would replace the
     * ones of the option, they must be mapped by an option of their own.
     *
     * @param string $tag Joomla language tag
     * @return array{lang: string, domain: string}|null Language or NULL if unknown
     */
    public function language( string $tag ): ?array
    {
        if( array_key_exists( $tag, $this->languages ) ) {
            return $this->languages[$tag];
        }

        if( !$this->guesses ) {
            return null;
        }

        return $this->remember( $this->guessed, $tag, function() use ( $tag ) {
            $code = $this->primary( $tag );
            return Utils::isValidLang( $code ) && !isset( $this->optionCodes[$code] ) ? Variants::language( $code ) : null;
        } );
    }


    /**
     * Returns the language code the texts of the article, category or field in the Joomla language are translated to.
     *
     * @param string $tag Joomla language tag
     * @return string Language code of the language or of the imported pages for all and unknown languages
     */
    public function locale( string $tag ): string
    {
        return $this->language( $tag )['lang'] ?? $this->lang;
    }


    /**
     * Tests if the Joomla language tag is the import language or for all languages.
     */
    public function source( string $tag ): bool
    {
        return $tag === self::ALL || ( $this->language( $tag )['lang'] ?? null ) === $this->lang;
    }


    /**
     * Tests if the Joomla language tag is a known language other than the import language.
     */
    public function other( string $tag ): bool
    {
        return !$this->source( $tag ) && $this->language( $tag ) !== null;
    }


    /**
     * Groups the Joomla language tags of the languages by their language codes.
     *
     * @param Collection<int, \stdClass> $rows Records of the usable languages
     * @return array<string, list<array{tag: string, trimmed: string}>> Stored and trimmed Joomla language tags grouped by language code
     */
    protected function codes( Collection $rows ): array
    {
        $codes = [];

        foreach( $rows as $row )
        {
            $tag = (string) $row->lang_code;

            // the URL code can be anything like "cn" for "zh-CN", so it's only used if it's the language code or a part of it
            $sef = strtolower( trim( (string) ( $row->sef ?? '' ) ) );
            // the stored tag is the key because the articles use it, the trimmed one the language code
            $trimmed = trim( $tag );
            $primary = $this->primary( $trimmed );

            if( $sef === $primary ) {
                $codes[$primary] = [['tag' => $tag, 'trimmed' => $trimmed], ...$codes[$primary] ?? []];
                continue;
            }

            $code = $sef === strtolower( $trimmed ) ? $trimmed : $primary;
            $codes[$code][] = ['tag' => $tag, 'trimmed' => $trimmed];
        }

        return $codes;
    }


    /**
     * Tests if the content language is published and guests can use it.
     *
     * @param \stdClass $row Record of the languages table
     * @param (\Closure(\stdClass): bool)|null $usable Returns if guests can use the language or NULL if all can be used
     */
    protected function available( \stdClass $row, ?\Closure $usable ): bool
    {
        return Row::published( $row ) && ( $usable === null || $usable( $row ) );
    }


    /**
     * Returns the Pagible languages of the Joomla languages.
     *
     * The first language tag of a code uses the code, the others their own language tag.
     * Tags which aren't valid language codes are unknown, so they aren't guessed from their language part.
     *
     * @param array<string, list<array{tag: string, trimmed: string}>> $codes Stored and trimmed Joomla language tags grouped by language code
     * @return array<string, array{lang: string, domain: string}|null> Languages or NULL for invalid ones by stored Joomla language tag
     */
    protected function variants( array $codes ): array
    {
        $languages = [];

        foreach( $codes as $code => $tags )
        {
            foreach( $tags as $pos => ['tag' => $tag, 'trimmed' => $trimmed] )
            {
                $value = $pos === 0 ? (string) $code : $trimmed;

                $languages[$tag] = Utils::isValidLang( $value ) ? Variants::language( $value ) : null;
            }
        }

        return $languages;
    }


    /**
     * Tests that the language codes of the --language options aren't used by other Joomla language tags too.
     *
     * Pages have one variant per language, so the translations of several tags with the same code would replace
     * each other. The languages derived from the languages table get unique codes, see codes(), but the ones
     * given by the options can be the same as another option or a derived language, e.g. "de-AT:de" and "de-DE".
     *
     * @param list<string> $tags Joomla language tags of the --language options
     * @throws \InvalidArgumentException If a language code of an option is used by another language tag too
     */
    protected function unique( array $tags ): void
    {
        $used = [];

        foreach( $this->languages as $tag => $language )
        {
            if( $language !== null ) {
                $used[$language['lang']][] = (string) $tag;
            }
        }

        foreach( $used as $lang => $list )
        {
            if( count( $list ) > 1 && array_intersect( $list, $tags ) !== [] ) {
                throw new \InvalidArgumentException( sprintf( 'Joomla languages %1$s use the same language code "%2$s", map each one to its own code with --language',
                    implode( ', ', $list ), $lang ) );
            }
        }
    }


    /**
     * Returns the primary language code of the Joomla language tag.
     *
     * @param string $tag Joomla language tag like "en-GB"
     * @return string Lower case language code like "en"
     */
    protected function primary( string $tag ): string
    {
        return strtolower( explode( '-', trim( $tag ) )[0] );
    }
}
