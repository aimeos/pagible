/**
 * @license MIT, https://opensource.org/license/mit
 */

// only for translation extraction

/*
 * Translation contexts (msgctxt) for $pgettext(), used for dynamic names below
 * and for UI labels whose meaning is ambiguous without context:
 *
 * - cs: content sections of a page
 * - sg: schema groups and page config groups
 * - as: aside drawer filters
 * - st: element states, schema elements and page config elements
 * - fn: field names in the element editor
 * - fh: field hints (descriptions) in the element editor
 * - sd: schema element descriptions
 * - op: option labels of select fields
 * - ai: AI response messages
 * - clipboard: "Cut" and "Copy" actions
 * - image edge: "Top", "Left", "Right" and "Bottom" of an image
 * - page status: "Enable" and "Disable" of pages
 * - search ranking: "Position" in the search results
 * - editing interface: the visual page editor
 * - editor person: the user who edited an item
 * - text element: "Split" a text element
 *
 * Names, descriptions, fields, hints and options of the schema elements are
 * translated by the theme packages defining them. Their catalogs are created
 * from the schema.json files by "php bin/schema-i18n" and use the same contexts.
 *
 * New contexts should use descriptive words like the last ones, not abbreviations.
 */

if ($pgettext) {
  // content sections
  $pgettext('cs', 'main')
  $pgettext('cs', 'header')
  $pgettext('cs', 'footer')
  $pgettext('cs', 'sidebar')
  $pgettext('cs', 'aside')

  // schema groups
  $pgettext('sg', 'basic')
  $pgettext('sg', 'content')
  $pgettext('sg', 'media')
  $pgettext('sg', 'forms')

  // aside drawer
  $pgettext('as', 'type')
  $pgettext('as', 'state')

  // element states
  $pgettext('st', 'valid')
  $pgettext('st', 'changed')
  $pgettext('st', 'error')

  // core elements
  $pgettext('st', 'reference')

  // field names in change and history paths
  $pgettext('fn', 'files')
  $pgettext('fn', 'language')
  $pgettext('fn', 'main')

  // AI response
  $pgettext('ai', 'Done')
  $pgettext('ai', 'Already exists')
}
