import { pluginComponents, pluginUi } from '../../../js/plugin'

describe('pluginUi', () => {
  const common = [
    'VAlert',
    'VAutocomplete',
    'VAvatar',
    'VBtn',
    'VCard',
    'VCardActions',
    'VCardText',
    'VCardTitle',
    'VCheckbox',
    'VCheckboxBtn',
    'VChip',
    'VCol',
    'VCombobox',
    'VContainer',
    'VDialog',
    'VDivider',
    'VFileInput',
    'VForm',
    'VIcon',
    'VImg',
    'VList',
    'VListGroup',
    'VListItem',
    'VListItemTitle',
    'VListSubheader',
    'VMenu',
    'VPagination',
    'VProgressCircular',
    'VProgressLinear',
    'VRadio',
    'VRadioGroup',
    'VRow',
    'VSelect',
    'VSheet',
    'VSpacer',
    'VSwitch',
    'VTab',
    'VTable',
    'VTabs',
    'VTabsWindow',
    'VTabsWindowItem',
    'VTextField',
    'VTextarea',
    'VToolbar',
    'VToolbarTitle',
    'VWindow',
    'VWindowItem',
    'CmsActionMenu',
    'CmsDialog',
    'CmsFilePicker',
    'CmsLoadingSpinner'
  ]

  it('provides the common component contract without mutating plugins', () => {
    const LocalComponent = {}
    const panel = { components: { LocalComponent } }
    const wrapped = pluginUi(panel)

    expect(Object.keys(pluginComponents)).to.deep.equal(common)
    expect(wrapped.components).to.include.keys(...common)
    expect(wrapped.components.CmsActionMenu).to.equal(pluginComponents.CmsActionMenu)
    expect(wrapped.components.CmsDialog).to.equal(pluginComponents.CmsDialog)
    expect(wrapped.components.CmsFilePicker).to.equal(pluginComponents.CmsFilePicker)
    expect(wrapped.components.CmsLoadingSpinner).to.equal(pluginComponents.CmsLoadingSpinner)
    expect(wrapped.components.LocalComponent.components).to.include.keys(...common)
    expect(panel.components).to.deep.equal({ LocalComponent })
    expect(LocalComponent).to.deep.equal({})
  })

  it('provides the components to nested and recursive plugin components', () => {
    const Leaf = { name: 'Leaf' }
    const Tree = { name: 'Tree', components: { Leaf } }
    Tree.components.Tree = Tree
    const Async = { __asyncLoader: () => Promise.resolve({}) }
    const wrapped = pluginUi({ components: { Tree, Leaf, Async } })

    expect(wrapped.components.Tree.components.Leaf.components).to.include.keys('CmsDialog')
    expect(wrapped.components.Tree.components.Tree).to.equal(wrapped.components.Tree)
    expect(wrapped.components.Leaf).to.equal(wrapped.components.Tree.components.Leaf)
    expect(wrapped.components.Async).to.equal(Async)
  })

  it('keeps shell, Labs and heavy specialized components private', () => {
    expect(Object.keys(pluginComponents)).not.to.include.members([
      'VApp',
      'VAppBar',
      'VColorInput',
      'VDataTable',
      'VDateInput',
      'VDatePicker',
      'VLayout',
      'VMain',
      'VNavigationDrawer',
      'VNumberInput',
      'VSnackbarQueue',
      'VTimePicker',
      'VTimeline'
    ])
  })
})
