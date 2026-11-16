<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Renders the values of the Joomla custom fields of the articles and categories as HTML.
 */
class JoomlaFields
{
    use JoomlaRemembers;

    /** Contexts of the custom fields of the articles and categories */
    public const ARTICLE_CONTEXT = 'com_content.article';
    public const CATEGORY_CONTEXT = 'com_content.categories';

    /** Positions of the fields on the article and category pages, "after title", "before content" and "after content" */
    public const AFTER_TITLE = 1;
    public const BEFORE_CONTENT = 2;
    public const AFTER_CONTENT = 3;

    /** Field group ID of the "{fieldgroup *}" shortcode showing all fields */
    public const ALL_GROUPS = '*';

    /** Field types whose values are the values of options */
    protected const OPTION_TYPES = ['list', 'radio', 'checkboxes'];

    /** Field types whose values aren't supported */
    protected const SKIPPED_TYPES = ['subform', 'repeatable'];

    /** Field types with special values */
    protected const TYPE_SQL = 'sql';
    protected const TYPE_USER = 'user';
    protected const TYPE_USER_GROUPS = 'usergrouplist';

    /** CSS class of the lists of the fields */
    protected const LIST_CLASS = 'fields';

    /** Regular expression matching block elements which can't be inside of a paragraph */
    protected const BLOCK_PATTERN = '#<(?:p|div|ul|ol|dl|table|h[1-6]|blockquote|pre|figure|hr)[\s/>]#i';

    /** Display options of the fields */
    protected const OPTION_DISPLAY = 'display';
    protected const OPTION_SHOW_LABEL = 'showlabel';
    protected const OPTION_PREFIX = 'prefix';
    protected const OPTION_SUFFIX = 'suffix';

    /** Options of the field types */
    protected const OPTION_QUERY = 'query';
    protected const OPTION_OPTIONS = 'options';

    /** @var array<int|string, \stdClass> Published fields guests can see by ID */
    protected array $fields = [];

    /** @var array<int|string, array<array-key, mixed>> Decoded display options of the fields like label and layout by field ID, decoded on first use */
    protected array $displayOptions = [];

    /** @var array<int|string, array<array-key, mixed>> Type options of the fields with the defaults of their field plugins by field ID, merged on first use */
    protected array $typeOptions = [];


    /**
     * Initializes the object.
     *
     * The options of the fields don't grow with the imported articles, so they are kept once decoded.
     *
     * @param iterable<\stdClass> $fields Published fields guests can see whose values are renderable() ordered by their position
     * @param JoomlaPlugins $plugins Options of the field plugins used if the fields don't set them
     * @param JoomlaFieldLookups $lookups Looks up the texts of the user, user group and SQL field values
     * @param JoomlaFieldTypes $types Renders the texts of the field values by their field type
     */
    public function __construct( iterable $fields, protected JoomlaPlugins $plugins, protected JoomlaFieldLookups $lookups,
        protected JoomlaFieldTypes $types )
    {
        foreach( $fields as $field ) {
            $this->fields[$field->id] = $field;
        }
    }


    /**
     * Returns the published fields guests can see.
     *
     * @return array<int|string, \stdClass> Fields by ID ordered by their position
     */
    public function all(): array
    {
        return $this->fields;
    }


    /**
     * Returns the published field guests can see.
     *
     * @param int|string $id ID of the field
     * @return \stdClass|null Field or NULL if it isn't shown
     */
    public function get( int|string $id ): ?\stdClass
    {
        return $this->fields[$id] ?? null;
    }


    /**
     * Tests if the field values are rendered.
     *
     * Fields without type have no field plugin which renders them and the values of some types aren't supported.
     */
    public static function renderable( \stdClass $field ): bool
    {
        $type = self::type( $field );
        return $type !== '' && !in_array( $type, self::SKIPPED_TYPES, true );
    }


    /**
     * Returns the type of the field.
     *
     * @return string Field type like "text" or "list", empty if the field has none
     */
    public static function type( \stdClass $field ): string
    {
        return JoomlaValue::string( $field->type ?? null );
    }


    /**
     * Returns the HTML of the fields Joomla shows at the position of the article or category page.
     *
     * @param int $position Position of the fields, one of the AFTER_TITLE, BEFORE_CONTENT and AFTER_CONTENT constants
     * @param JoomlaFieldContext $context Values of the shown fields, how their HTML is prepared and the language
     * @return string HTML list of the fields or an empty string if there are none
     */
    public function position( int $position, JoomlaFieldContext $context ): string
    {
        $fields = array_filter( $this->fields, fn( $field ) => (int) ( $this->displayOptions( $field )[self::OPTION_DISPLAY] ?? self::BEFORE_CONTENT ) === $position );
        return $this->list( $fields, $context );
    }


    /**
     * Returns the HTML of a field for the "{field ID}" shortcode.
     *
     * Fields with block elements like editor fields are a block themselves, so paragraphs aren't nested.
     *
     * @param int|string $id ID of the field
     * @param JoomlaFieldContext $context Values of the shown fields, how their HTML is prepared and the language
     * @return string HTML of the field or an empty string if it's unknown, not shown or has no value
     */
    public function field( int|string $id, JoomlaFieldContext $context ): string
    {
        $field = $this->get( $id );
        $html = $field ? $this->item( $field, $context ) : '';

        if( $html === '' ) {
            return '';
        }

        return preg_match( self::BLOCK_PATTERN, $html ) ? '<div>' . $html . '</div>' : '<p>' . $html . '</p>';
    }


    /**
     * Returns the HTML of the fields of a field group for the "{fieldgroup ID}" shortcode.
     *
     * @param int|string $id ID of the field group or ALL_GROUPS for all fields
     * @param JoomlaFieldContext $context Values of the shown fields, how their HTML is prepared and the language
     * @return string HTML list of the fields or an empty string if there are none
     */
    public function group( int|string $id, JoomlaFieldContext $context ): string
    {
        $fields = array_filter( $this->fields, fn( $field ) => $id === self::ALL_GROUPS || JoomlaValue::string( $field->group_id ?? '0' ) === (string) $id );
        return $this->list( $fields, $context );
    }


    /**
     * Returns the label, the prefix, the value and the suffix of the field as HTML.
     *
     * @param JoomlaFieldContext $context Values of the shown fields, how their HTML is prepared and the language
     * @return string HTML or an empty string if the field has no value
     */
    protected function item( \stdClass $field, JoomlaFieldContext $context ): string
    {
        if( !isset( $context->values[$field->id] ) || ( $value = $this->value( $field, $context->values[$field->id], $context ) ) === '' ) {
            return '';
        }

        $params = $this->displayOptions( $field );
        $label = JoomlaValue::enabled( $params, self::OPTION_SHOW_LABEL ) ? '<strong>' . e( $this->label( $field ) ) . ': </strong>' : '';

        return $label . $this->affix( $params, self::OPTION_PREFIX ) . $value . $this->affix( $params, self::OPTION_SUFFIX );
    }


    /**
     * Returns the label of the field shown before its value.
     *
     * @return string Label or the title of the field if it has no label
     */
    protected function label( \stdClass $field ): string
    {
        return JoomlaValue::string( $field->label ?? null ) ?: JoomlaValue::string( $field->title ?? null );
    }


    /**
     * Returns the prefix or suffix of the field as HTML.
     *
     * @param array<array-key, mixed> $params Options of the field
     * @param string $name Option name, "prefix" or "suffix"
     * @return string Escaped text, like Joomla an empty string for texts like "0"
     */
    protected function affix( array $params, string $name ): string
    {
        $text = $params[$name] ?? null;
        return is_string( $text ) && $text ? e( $text ) : '';
    }


    /**
     * Returns the HTML list of the fields.
     *
     * @param array<int|string, \stdClass> $fields Fields by ID
     * @param JoomlaFieldContext $context Values of the shown fields, how their HTML is prepared and the language
     * @return string HTML list or an empty string if no field has a value
     */
    protected function list( array $fields, JoomlaFieldContext $context ): string
    {
        $items = [];

        foreach( $fields as $field )
        {
            if( ( $html = $this->item( $field, $context ) ) !== '' ) {
                $items[] = $html;
            }
        }

        return JoomlaMarkup::list( $items, self::LIST_CLASS );
    }


    /**
     * Returns the values of the field as HTML like the layouts of the Joomla field plugins render them.
     *
     * @param list<string> $values Values of the field, the default value of the field is used if there's none or only an empty one
     * @param JoomlaFieldContext $context How the HTML of the field is prepared and the language
     * @return string HTML or an empty string if there's no supported value
     */
    protected function value( \stdClass $field, array $values, JoomlaFieldContext $context ): string
    {
        $type = self::type( $field );

        $params = $this->typeOptions( $field, $type );
        $texts = $this->texts( $field, $type, $params, $this->valuesOrDefault( $field, $values ) );
        $html = array_map( fn( $value ) => $this->types->render( $type, $value, $params, $context ), $texts );

        return implode( $this->types->images( $type ) ? '' : ', ', array_filter( $html, fn( $value ) => $value !== '' ) );
    }


    /**
     * Returns the type options of the field, the options of the field plugin are used if the field doesn't set them.
     *
     * @param string $type Field type like "list" or "url"
     * @return array<array-key, mixed> Type options
     */
    protected function typeOptions( \stdClass $field, string $type ): array
    {
        return $this->remember( $this->typeOptions, $field->id, fn() => JoomlaValue::filled( JoomlaValue::decode( $field->fieldparams ?? null ) )
            + ( $this->plugins->fieldParams( $type ) ?? [] ) );
    }


    /**
     * Returns the display options of the field like the label, the prefix, the suffix and the position.
     *
     * @return array<array-key, mixed> Display options
     */
    protected function displayOptions( \stdClass $field ): array
    {
        return $this->remember( $this->displayOptions, $field->id, fn() => JoomlaRow::params( $field ) );
    }


    /**
     * Returns the non-empty values of the field or its default value.
     *
     * @param list<string> $values Values of the field
     * @return list<string> Values without empty ones
     */
    protected function valuesOrDefault( \stdClass $field, array $values ): array
    {
        // like Joomla, only empty values are replaced by the default value while values with spaces show nothing
        $values = $values === [] || $values === [''] ? [(string) ( $field->default_value ?? '' )] : $values;
        return array_values( array_filter( $values, fn( $value ) => trim( $value ) !== '' ) );
    }


    /**
     * Returns the texts shown for the values of the field.
     *
     * Values of option fields are shown by the texts of their options in the order of the options, unknown values are skipped.
     *
     * @param string $type Field type like "list" or "user"
     * @param array<array-key, mixed> $params Field parameters
     * @param list<string> $values Values of the field
     * @return list<string> Texts of the values
     */
    protected function texts( \stdClass $field, string $type, array $params, array $values ): array
    {
        return match( true ) {
            in_array( $type, self::OPTION_TYPES, true ) => $this->choose( $this->choices( $params ), $values ),
            $type === self::TYPE_SQL => $this->choose( $this->lookups->sqlOptions( $field->id, JoomlaValue::string( $params[self::OPTION_QUERY] ?? null ) ), $values ),
            $type === self::TYPE_USER_GROUPS => $this->choose( $this->lookups->groups(), $values ),
            // like Joomla, the names are joined first, so unknown users are shown as empty names
            $type === self::TYPE_USER => [implode( ', ', $this->lookups->names( $values ) )],
            default => $values,
        };
    }


    /**
     * Returns the options of a list, radio or checkboxes field.
     *
     * @param array<array-key, mixed> $params Field parameters
     * @return array<string, string> Option names by value
     */
    protected function choices( array $params ): array
    {
        $options = [];

        foreach( is_array( $params[self::OPTION_OPTIONS] ?? null ) ? $params[self::OPTION_OPTIONS] : [] as $option )
        {
            if( is_array( $option ) && isset( $option['value'] ) ) {
                $options[(string) $option['value']] = (string) ( $option['name'] ?? $option['value'] );
            }
        }

        return $options;
    }


    /**
     * Returns the texts of the options whose values are selected in the order of the options.
     *
     * @param array<int|string, string> $options Option texts by value
     * @param list<string> $values Selected values
     * @return list<string> Texts of the selected options
     */
    protected function choose( array $options, array $values ): array
    {
        return array_values( array_intersect_key( $options, array_flip( $values ) ) );
    }
}
