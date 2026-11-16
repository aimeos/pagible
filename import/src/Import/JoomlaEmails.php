<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Converts the e-mail addresses like the "Content - Email Cloaking" plugin of Joomla.
 *
 * The addresses are converted in the article texts, category descriptions, custom field values and module contents.
 */
class JoomlaEmails
{
    /** Shortcode disabling the "Content - Email Cloaking" plugin for the text */
    protected const CLOAK_OFF = '{emailcloak=off}';
    /** Regular expression matching the tags whose content isn't searched for e-mail addresses, "/" for closing tags as first and the tag name as second group */
    protected const SKIPPED_TAG_PATTERN = '/^<(\/?)(a|script|style|textarea)\b/i';
    /** Regular expression matching a whole element whose content is raw text, which may contain "<" and isn't searched */
    protected const RAW_ELEMENT_PATTERN = '/^<(script|style|textarea)\b.*<\/\1\s*>$/is';
    /**
     * Regular expression fragment matching the attributes of a tag, ">" in quoted attribute values doesn't end the tag.
     *
     * Like in browsers, quotes only start a quoted value directly after "=" and optional white space. Unquoted values
     * end at white space or ">", so quotes in them like in title=it's don't start a quoted value.
     */
    protected const TAG_ATTRIBUTES = '(?:[^>=]++|=\s*+(?:"[^"]*+"|\'[^\']*+\'|[^\s>]*+))*+';
    /**
     * Regular expression splitting the HTML into tags and texts.
     *
     * Only comments and "<" followed by a letter, "/" or "!" and a letter are tags like in browsers, so other "<"
     * like in "a < b" are text. Script, style and textarea elements are matched as a whole because their content
     * is raw text which may contain "<", e.g. "if(a<b)". Quoted values which aren't closed end at the first ">"
     * like unquoted ones. The pattern contains only one capturing group, so preg_split() returns the texts and
     * tags alternating.
     */
    protected const TAG_SPLIT_PATTERN = '/(<!--.*?-->'
        . '|<script\b' . self::TAG_ATTRIBUTES . '>.*?<\/script\s*>'
        . '|<style\b' . self::TAG_ATTRIBUTES . '>.*?<\/style\s*>'
        . '|<textarea\b' . self::TAG_ATTRIBUTES . '>.*?<\/textarea\s*>'
        . '|<[\/!]?[a-z]' . self::TAG_ATTRIBUTES . '>'
        . '|<[\/!]?[a-z][^>]*>)/is';

    /*
     * The following expressions are the ones of the "Content - Email Cloaking" plugin of Joomla, so the same
     * e-mail addresses and link texts are found.
     */

    /** E-mail addresses: local part, "@", domain labels with dots and the top level domain of 2 to 24 characters */
    protected const EMAIL_PATTERN = "[\p{L}\p{N}.'\-+]+@(?:[.\-\p{L}\p{N}]+\.)+[\-\p{L}\p{N}]{2,24}";
    /**
     * First character of a link text: any character except the ASCII controls, e.g. Latin-1, Cyrillic, Greek or CJK
     * letters and the no-break space, which Joomla accepts as UTF-8 byte sequences. Without Unicode mode, the bytes
     * of the non-ASCII characters match.
     */
    protected const EMAIL_TEXT_START = '[^\x00-\x1f]';
    /** Plain link text: the first character followed by anything except tags */
    protected const EMAIL_TEXT = self::EMAIL_TEXT_START . '[^<>]+';
    /** Opening tags around the link text: <span ...>, <span>, <strong>, <strong><span ...> or <strong><span> */
    protected const EMAIL_SPAN_OPEN = '(?:<span[^>]+>|<span>|<strong>|<strong><span[^>]+>|<strong><span>)';
    /** Closing tags after the link text: </span>, </strong> or </span></strong> */
    protected const EMAIL_SPAN_CLOSE = '(?:<\/span>|<\/strong>|<\/span><\/strong>)';
    /** Link text in <span> and <strong> tags */
    protected const EMAIL_SPAN = self::EMAIL_SPAN_OPEN . self::EMAIL_TEXT . self::EMAIL_SPAN_CLOSE;
    /** Link text which is an image, optionally followed by text */
    protected const EMAIL_IMAGE = '<img[^>]+>(?:' . self::EMAIL_TEXT . ')?';


    /**
     * Returns the text without the shortcodes disabling the "Content - Email Cloaking" plugin if one is in lower case.
     *
     * Like Joomla, the plugin doesn't change the e-mail addresses of these texts and removes the shortcodes in any case.
     *
     * @return string|null Text without the shortcodes or NULL if the text doesn't disable the plugin
     */
    public static function cloakOff( string $html ): ?string
    {
        return str_contains( $html, self::CLOAK_OFF ) ? str_ireplace( self::CLOAK_OFF, '', $html ) : null;
    }


    /**
     * Turns the e-mail addresses in the text into links like the "Content - Email Cloaking" plugin does.
     *
     * The plugin hides the addresses from spam bots by JavaScript, Pagible shows plain links instead. Addresses
     * in tags, e.g. in attributes, and in links aren't changed.
     *
     * @param string $html HTML with e-mail addresses like "info@example.com"
     * @return string HTML with links like <a href="mailto:info@example.com">info@example.com</a>
     */
    public static function emails( string $html ): string
    {
        if( ( $parts = preg_split( self::TAG_SPLIT_PATTERN, $html, -1, PREG_SPLIT_DELIM_CAPTURE ) ) === false ) {
            return $html;
        }

        // number of open tags by tag name, so a closing tag only ends its own kind of tag
        $open = [];

        // the texts and the captured tags alternate, so texts starting with a "<" which isn't a tag are searched too
        foreach( $parts as $pos => $part )
        {
            if( $pos % 2 === 1 )
            {
                // whole script, style and textarea elements don't change the open tags, unclosed ones skip the rest
                if( !preg_match( self::RAW_ELEMENT_PATTERN, $part ) && preg_match( self::SKIPPED_TAG_PATTERN, $part, $match ) )
                {
                    $name = strtolower( $match[2] );
                    $open[$name] = max( 0, ( $open[$name] ?? 0 ) + ( $match[1] === '' ? 1 : -1 ) );
                }
            }
            elseif( !array_filter( $open ) && $part !== '' )
            {
                $parts[$pos] = self::replace( '/' . self::EMAIL_PATTERN . '/i', '<a href="mailto:$0">$0</a>', $part );
            }
        }

        return implode( '', $parts );
    }


    /**
     * Replaces the links to e-mail addresses by their link text like the "Content - Email Cloaking" plugin does if it shows the addresses as text.
     *
     * Like Joomla, only links whose text is plain text, an image or text in <span> or <strong> tags are replaced.
     *
     * @param string $html HTML with links like <a href="mailto:info@example.com">Mail</a>
     * @return string HTML with the link texts like "Mail" instead
     */
    public static function unlinkEmails( string $html ): string
    {
        $link = self::EMAIL_TEXT . '|' . self::EMAIL_SPAN . '|' . self::EMAIL_IMAGE;
        $pattern = '/<a [^>]*href\s*=\s*"mailto:' . self::EMAIL_PATTERN . '(?:[?&][\x20-\x7f][^"<>]+)?"[^>]*>(' . $link . ')<\/a>/i';

        return self::replace( $pattern, '$1', $html );
    }


    /**
     * Replaces the matches of the pattern in Unicode mode and falls back to bytes if the text isn't valid UTF-8.
     *
     * Unicode patterns fail on invalid UTF-8, so the text would be lost otherwise. Without Unicode mode, the
     * addresses and link texts in ASCII are still found and the text is kept unchanged if that fails too.
     *
     * @param string $pattern Regular expression without the "u" modifier
     * @param string $replace Replacement with references to the groups
     * @param string $text HTML or text
     * @return string Text with the replacements
     */
    protected static function replace( string $pattern, string $replace, string $text ): string
    {
        return preg_replace( $pattern . 'u', $replace, $text ) ?? preg_replace( $pattern, $replace, $text ) ?? $text;
    }
}
