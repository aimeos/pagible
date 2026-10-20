import { createPinia, setActivePinia } from 'pinia'
import { translate, write } from '../../../js/ai'
import { useMessageStore } from '../../../js/stores'

describe('ai', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('resolves translate() to false without permission', () => {
    const messages = useMessageStore()
    const result = translate(['Hello'], 'de')

    expect(result).to.be.instanceOf(Promise)

    return result.then((value) => {
      expect(value).to.be.false
      expect(messages.queue.some((msg) => msg.text === 'Permission denied')).to.be.true
    })
  })

  it('resolves write() to false without permission', () => {
    const result = write('Some text')

    expect(result).to.be.instanceOf(Promise)

    return result.then((value) => {
      expect(value).to.be.false
    })
  })
})
