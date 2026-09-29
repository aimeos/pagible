const files = import.meta.glob('../../../i18n/*.po', {
  query: '?raw',
  import: 'default',
  eager: true
})
const compiled = import.meta.glob('../../../i18n/*.json', { import: 'default', eager: true })

// placeholders like %{num} and HTML tags which must be kept in translations
const tokens = (str) => (str.match(/%\{\w+\}|<\/?[a-z][^>]*>/g) || []).sort()

function unquote(lines) {
  return lines.map((line) => JSON.parse(line.slice(line.indexOf('"')))).join('')
}

function parse(po) {
  return po
    .split(/\n\s*\n/)
    .map((block) => {
      const entry = {}
      let key = null

      for (const line of block.split('\n')) {
        if (line.startsWith('#')) continue
        const match = line.match(/^(msgctxt|msgid|msgid_plural|msgstr(?:\[\d+\])?) /)
        key = match ? match[1] : key
        key && (entry[key] ??= []).push(line)
      }

      return Object.fromEntries(Object.entries(entry).map(([k, lines]) => [k, unquote(lines)]))
    })
    .filter((entry) => entry.msgid !== undefined)
}

function forms(entry) {
  return Object.keys(entry)
    .filter((key) => key.startsWith('msgstr'))
    .map((key) => entry[key])
}

function label(entry) {
  return (entry.msgctxt ? `[${entry.msgctxt}] ` : '') + entry.msgid
}

// same structure as created by "npm run gettext:compile"
function catalog(entries) {
  const map = {}

  for (const entry of entries.filter((entry) => entry.msgid)) {
    const msgstrs = forms(entry)

    if (msgstrs.every(Boolean)) {
      map[entry.msgid] ??= {}
      // a single plural form is stored as string
      map[entry.msgid][entry.msgctxt ?? ''] = msgstrs.length > 1 ? msgstrs : msgstrs[0]
    }
  }

  return Object.fromEntries(
    Object.entries(map).map(([msgid, ctx]) => [
      msgid,
      Object.keys(ctx).join() === '' ? ctx[''] : ctx
    ])
  )
}

describe('translations', () => {
  const langs = Object.entries(files)
    .map(([path, po]) => [path.match(/(\w+)\.po$/)[1], parse(po)])
    .filter(([lang]) => lang !== 'en') // English uses the msgids

  it('finds the translation files', () => {
    expect(langs.length).to.be.greaterThan(20)
  })

  langs.forEach(([lang, entries]) => {
    const translations = entries.filter((entry) => entry.msgid)

    // run with "npm run test:i18n", missing translations shouldn't block unrelated changes
    it(`are complete in ${lang}`, function () {
      if (!Cypress.expose('i18nComplete')) this.skip()

      const missing = translations.filter((entry) => forms(entry).some((form) => !form)).map(label)

      expect(missing, `untranslated:\n${missing.join('\n')}`).to.be.empty
    })

    it(`keep placeholders and plural forms in ${lang}`, () => {
      const header = entries.find((entry) => entry.msgid === '')
      const nplurals = +header?.msgstr.match(/nplurals=(\d+)/)?.[1]
      const errors = []

      for (const entry of translations) {
        const name = label(entry)
        const msgstrs = forms(entry)

        if (entry.msgid_plural !== undefined && msgstrs.length !== nplurals) {
          errors.push(`${msgstrs.length} of ${nplurals} plural forms: ${name}`)
        }

        if (msgstrs.some((form) => !form)) {
          continue
        }

        if (entry.msgid_plural === undefined) {
          if (tokens(msgstrs[0]).join() !== tokens(entry.msgid).join()) {
            errors.push(`placeholders: ${name} -> ${msgstrs[0]}`)
          }
          continue
        }

        // plural forms may spell out the number instead of %{num}, e.g. "one" in Arabic or Hebrew,
        // but the last form is used for most numbers and must contain it
        const allowed = new Set([...tokens(entry.msgid), ...tokens(entry.msgid_plural)])
        const required = [...allowed].filter((token) => token !== '%{num}')

        msgstrs.forEach((form, idx) => {
          const found = tokens(form)
          if (
            found.some((token) => !allowed.has(token)) ||
            required.some((token) => !found.includes(token)) ||
            (idx === msgstrs.length - 1 && allowed.has('%{num}') && !found.includes('%{num}'))
          ) {
            errors.push(`placeholders: ${name} -> ${form}`)
          }
        })
      }

      expect(errors, errors.join('\n')).to.be.empty
    })

    it(`are compiled from the current ${lang}.po file`, () => {
      const json = compiled[`../../../i18n/${lang}.json`]?.[lang]
      expect(json, `${lang}.json is outdated, run "npm run gettext:compile"`).to.deep.equal(
        catalog(entries)
      )
    })
  })
})
