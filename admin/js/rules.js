/**
 * @license MIT, https://opensource.org/license/mit
 */

/*
 * Validation rules for required values and the minimum and maximum length of field values
 *
 * Each factory gets the $gettext/$ngettext function of the component and the configuration and returns a
 * Vuetify rule, which returns true for valid values or the translated error message. An empty or zero
 * limit disables the check. The messages must stay literal $ngettext() calls for the translation extractor.
 * Character limits aren't checked for empty values, use required() for fields which must have a value.
 */

// 0 and false are values, empty lists are not
const empty = (v) => v === null || v === undefined || v === '' || (Array.isArray(v) && !v.length)

// labels incl. punycode ones (xn--) and alphabetic or punycode TLDs, case insensitive
const DOMAIN_RE = /^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+(?:[a-z]{2,}|xn--[a-z0-9-]+)$/i

export const domain = ($gettext) => (v) =>
  !v || DOMAIN_RE.test(v) || $gettext('Domain name is invalid')

export const required = ($gettext, flag) => (v) =>
  !flag || !empty(v) || $gettext('Value is required')

export const minEntries = ($ngettext, min) => (v) =>
  !min ||
  +v?.length >= +min ||
  $ngettext('At least %{num} entry is required', 'At least %{num} entries are required', +min, {
    num: min
  })

export const maxEntries = ($ngettext, max) => (v) =>
  !max ||
  +v?.length <= +max ||
  $ngettext('At most %{num} entry is allowed', 'At most %{num} entries are allowed', +max, {
    num: max
  })

export const minChars = ($ngettext, min) => (v) =>
  !min ||
  !v?.length ||
  +v?.length >= +min ||
  $ngettext(
    'At least %{num} character is required',
    'At least %{num} characters are required',
    +min,
    { num: min }
  )

export const maxChars = ($ngettext, max) => (v) =>
  !max ||
  +v?.length <= +max ||
  $ngettext('At most %{num} character is allowed', 'At most %{num} characters are allowed', +max, {
    num: max
  })

// the table rules count the rows of the table like the server side minItems/maxItems
export const minRows = ($ngettext, min) => (v) =>
  !min ||
  +v?.length >= +min ||
  $ngettext('At least %{num} row is required', 'At least %{num} rows are required', +min, {
    num: min
  })

export const maxRows = ($ngettext, max) => (v) =>
  !max ||
  +v?.length <= +max ||
  $ngettext('At most %{num} row is allowed', 'At most %{num} rows are allowed', +max, {
    num: max
  })
