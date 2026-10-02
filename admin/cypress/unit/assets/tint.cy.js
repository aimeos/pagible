import { h } from 'vue'
import { VBtn, VCard, VThemeProvider, VToolbar } from 'vuetify/components'
import '../../../js/assets/base.css'

// parses the first "rgb(...)", "rgba(...)" or "color(srgb ...)" into [r, g, b, a] (0-255, alpha 0-1)
function parse(value) {
  const srgb = value.match(/color\(srgb ([\d.]+) ([\d.]+) ([\d.]+)(?: \/ ([\d.]+))?\)/)
  if (srgb) {
    return [srgb[1] * 255, srgb[2] * 255, srgb[3] * 255, srgb[4] === undefined ? 1 : +srgb[4]]
  }
  const [r, g, b, a = 1] = value.match(/[\d.]+/g).map(Number)
  return [r, g, b, a]
}

function luminance([r, g, b]) {
  const lin = (c) => ((c /= 255) <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4)
  return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b)
}

function contrast(fg, bg) {
  const [l1, l2] = [luminance(fg), luminance(bg)].sort((a, b) => b - a)
  return (l1 + 0.05) / (l2 + 0.05)
}

// composites a translucent color over an opaque one
function over([r, g, b, a], base) {
  return [r * a + base[0] * (1 - a), g * a + base[1] * (1 - a), b * a + base[2] * (1 - a)]
}

// reads the theme colors of the admin config as CSS variables like Vuetify generates them
function palette(php, theme) {
  const block = php.split(`'${theme}' => [`)[1].split("'variables'")[0]
  const style = {}

  for (const [, name, hex] of block.matchAll(/'([\w-]+)' => '#([0-9A-Fa-f]{6})'/g)) {
    style[`--v-theme-${name}`] = hex.match(/../g).map((v) => parseInt(v, 16))
  }
  for (const name of ['background', 'surface']) {
    style[`--v-theme-on-${name}`] =
      luminance(style[`--v-theme-${name}`]) < 0.5 ? [255, 255, 255] : [0, 0, 0]
  }
  return Object.fromEntries(Object.entries(style).map(([k, v]) => [k, v.join(',')]))
}

// composites the tonal underlay of a button over the background and returns its text color and fill
function tonal(btn, base) {
  const underlay = getComputedStyle(btn.querySelector('.v-btn__underlay'))
  const [r, g, b, a] = parse(underlay.backgroundColor)
  const bg = over(
    [r, g, b, a * Number(underlay.opacity)],
    over(parse(getComputedStyle(btn).backgroundColor), base)
  )

  return { fg: parse(getComputedStyle(btn.querySelector('.v-btn__content')).color), bg }
}

function mountTheme(theme) {
  return cy.readFile('config/cms/admin.php').then((php) => {
    // set on every themed component because Vuetify adds the theme class to each one
    const style = palette(php, theme)
    const buttons = (prefix) =>
      colors.map((color) =>
        h(VBtn, { color, variant: 'tonal', class: `${prefix}-${color}`, style }, () => color)
      )

    cy.mount({
      render: () =>
        h(VThemeProvider, { theme, withBackground: true, class: 'surface', style }, () => [
          h('div', { class: 'dialog' }, buttons('dialog')),
          h('div', { class: 'v-app-bar' }, [
            ...buttons('bar'),
            h(VBtn, { variant: 'tonal', class: 'active bar-publish', style }, () => 'publish'),
            h(VBtn, { variant: 'text', class: 'error bar-changed', style }, () => 'changed'),
            h(VBtn, { variant: 'plain', class: 'saved bar-saved', style }, () => 'saved')
          ]),
          h('span', { class: 'item-lang' }, 'en'),
          h('div', { class: 'v-overlay' }, [
            h(VCard, { style }, () =>
              h(VToolbar, { color: 'warning', class: 'header-warning', style }, () => 'Warning')
            ),
            h(VCard, { style }, () =>
              h(VToolbar, { color: 'error', class: 'header-error', style }, () => 'Error')
            )
          ])
        ])
    })
  })
}

const colors = ['primary', 'secondary', 'error', 'warning', 'info', 'success']

describe('tinted colors', () => {
  ;['light', 'dark'].forEach((theme) => {
    describe(`in ${theme} mode`, () => {
      beforeEach(() => mountTheme(theme))

      it('uses tonal dialog buttons with AAA text', () => {
        cy.get('.surface').then(($root) => {
          const surface = parse(getComputedStyle($root[0]).getPropertyValue('--v-theme-surface'))

          colors.forEach((color) => {
            const btn = $root[0].querySelector(`.dialog-${color}`)
            const { fg, bg } = tonal(btn, surface)

            expect(getComputedStyle(btn).boxShadow, `${color} has no ring`).to.equal('none')
            expect(bg, `${color} is tinted`).not.to.deep.equal(surface.slice(0, 3))
            expect(contrast(fg, bg), `${color} text`).to.be.at.least(7)
          })
        })
      })

      it('uses a stronger tint for warning and error buttons', () => {
        cy.get('.surface').then(($root) => {
          const surface = parse(getComputedStyle($root[0]).getPropertyValue('--v-theme-surface'))

          ;['error', 'warning'].forEach((color) => {
            const { bg } = tonal($root[0].querySelector(`.dialog-${color}`), surface)
            expect(contrast(bg, surface), `${color} stands out`).to.be.at.least(1.5)
          })
        })
      })

      it('uses recognizable app bar buttons with AA text', () => {
        cy.get('.surface').then(($root) => {
          const background = parse(
            getComputedStyle($root[0]).getPropertyValue('--v-theme-background')
          )
          const sels = [
            ...colors.map((c) => `.bar-${c}`),
            '.bar-publish',
            '.bar-changed',
            '.bar-saved'
          ]

          sels.forEach((sel) => {
            const btn = $root[0].querySelector(sel)
            const { fg, bg } = tonal(btn, background)

            expect(getComputedStyle(btn).boxShadow, `${sel} has no ring`).to.equal('none')
            expect(contrast(bg, background), `${sel} is visible`).to.be.at.least(2)
            expect(contrast(fg, bg), `${sel} text`).to.be.at.least(4.5)
          })
        })
      })

      it('uses tinted warning and error dialog headers with AAA text', () => {
        cy.get('.surface').then(($root) => {
          const surface = parse(getComputedStyle($root[0]).getPropertyValue('--v-theme-surface'))

          ;['warning', 'error'].forEach((type) => {
            const style = getComputedStyle($root[0].querySelector(`.header-${type}`))
            const bg = over(parse(style.backgroundColor), surface)

            expect(bg, `${type} header is tinted`).not.to.deep.equal(surface.slice(0, 3))
            expect(contrast(parse(style.color), bg), `${type} header text`).to.be.at.least(7)
          })
        })
      })

      it('uses readable language tags', () => {
        cy.get('.surface').then(($root) => {
          const surface = parse(getComputedStyle($root[0]).getPropertyValue('--v-theme-surface'))
          const style = getComputedStyle($root[0].querySelector('.item-lang'))

          expect(
            contrast(parse(style.color), over(parse(style.backgroundColor), surface))
          ).to.be.at.least(4.5)
        })
      })
    })
  })
})
