import {
  minEntries,
  maxEntries,
  minChars,
  maxChars,
  minRows,
  maxRows,
  domain,
  required
} from '../../../js/rules'

// English translation function: singular for 1, plural otherwise
const ngettext = (singular, plural, n, params) =>
  (n === 1 ? singular : plural).replace('%{num}', params.num)

const gettext = (msgid) => msgid

describe('rules', () => {
  describe('domain', () => {
    it('accepts ASCII, punycode and uppercase domains', () => {
      for (const value of ['', 'example.com', 'Example.COM', 'a.b.co.uk', 'xn--mnchen-3ya.de', 'example.xn--p1ai']) {
        expect(domain(gettext)(value), value).to.equal(true)
      }
    })

    it('rejects invalid domains', () => {
      for (const value of ['nodot', '-bad.com', 'bad-.com', 'a..com', 'x.c', '1.2.3.4']) {
        expect(domain(gettext)(value), value).to.equal('Domain name is invalid')
      }
    })
  })

  describe('required', () => {
    it('is disabled if not configured', () => {
      expect(required(gettext, undefined)('')).to.equal(true)
      expect(required(gettext, false)(null)).to.equal(true)
    })

    it('accepts values', () => {
      expect(required(gettext, true)('a')).to.equal(true)
    })

    it('accepts 0 and false as values', () => {
      expect(required(gettext, true)(0)).to.equal(true)
      expect(required(gettext, true)(false)).to.equal(true)
    })

    it('fails for empty values', () => {
      expect(required(gettext, true)('')).to.equal('Value is required')
      expect(required(gettext, true)(null)).to.equal('Value is required')
      expect(required(gettext, true)(undefined)).to.equal('Value is required')
      expect(required(gettext, true)([])).to.equal('Value is required')
    })
  })

  describe('minimum', () => {
    it('is disabled for an empty or zero limit', () => {
      expect(minChars(ngettext, undefined)('')).to.equal(true)
      expect(minChars(ngettext, null)('')).to.equal(true)
      expect(minEntries(ngettext, 0)([])).to.equal(true)
    })

    it('accepts values with at least the minimum length', () => {
      expect(minChars(ngettext, 3)('abc')).to.equal(true)
      expect(minEntries(ngettext, 2)([1, 2, 3])).to.equal(true)
      expect(minRows(ngettext, '2')([1, 2])).to.equal(true)
    })

    it('returns the singular message for a limit of one', () => {
      expect(minEntries(ngettext, 1)([])).to.equal('At least 1 entry is required')
      expect(minRows(ngettext, 1)([])).to.equal('At least 1 row is required')
    })

    it('returns the plural message for other limits', () => {
      expect(minEntries(ngettext, 2)([1])).to.equal('At least 2 entries are required')
      expect(minChars(ngettext, '4')('abc')).to.equal('At least 4 characters are required')
      expect(minRows(ngettext, 3)([1])).to.equal('At least 3 rows are required')
    })

    it('accepts empty values for character limits', () => {
      expect(minChars(ngettext, 2)(undefined)).to.equal(true)
      expect(minChars(ngettext, 2)('')).to.equal(true)
    })

    it('fails for missing entries and columns', () => {
      expect(minEntries(ngettext, 1)(undefined)).to.equal('At least 1 entry is required')
      expect(minRows(ngettext, 2)([])).to.equal('At least 2 rows are required')
    })
  })

  describe('maximum', () => {
    it('is disabled for an empty or zero limit', () => {
      expect(maxChars(ngettext, undefined)('abc')).to.equal(true)
      expect(maxEntries(ngettext, 0)([1, 2])).to.equal(true)
    })

    it('accepts values up to the maximum length', () => {
      expect(maxChars(ngettext, 3)('abc')).to.equal(true)
      expect(maxEntries(ngettext, 2)([1])).to.equal(true)
      expect(maxRows(ngettext, '2')([1, 2])).to.equal(true)
    })

    it('returns the singular message for a limit of one', () => {
      expect(maxEntries(ngettext, 1)([1, 2])).to.equal('At most 1 entry is allowed')
      expect(maxChars(ngettext, 1)('ab')).to.equal('At most 1 character is allowed')
      expect(maxRows(ngettext, 1)([1, 2])).to.equal('At most 1 row is allowed')
    })

    it('returns the plural message for other limits', () => {
      expect(maxEntries(ngettext, 2)([1, 2, 3])).to.equal('At most 2 entries are allowed')
      expect(maxChars(ngettext, '3')('abcd')).to.equal('At most 3 characters are allowed')
      expect(maxRows(ngettext, 2)([1, 2, 3])).to.equal('At most 2 rows are allowed')
    })
  })
})
