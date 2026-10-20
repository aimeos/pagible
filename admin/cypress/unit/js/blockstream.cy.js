import { appendChunk, finishStream, streamMessage } from '../../../js/blockstream'

const render = (text) => `<${text}>`

describe('blockstream', () => {
  it('creates a message with empty stream state', () => {
    const msg = streamMessage({ id: 1, role: 'assistant' })

    expect(msg).to.include({ id: 1, role: 'assistant', content: '', pending: '', streaming: true })
    expect(msg.blocks).to.deep.equal([])
  })

  it('keeps text without a blank line in the open block', () => {
    const msg = streamMessage()

    appendChunk(msg, 'Hello\n', render)
    appendChunk(msg, 'World', render)

    expect(msg.blocks).to.deep.equal([])
    expect(msg.pending).to.equal('Hello\nWorld')
  })

  it('renders blocks completed by a blank line, also across chunks', () => {
    const msg = streamMessage()

    appendChunk(msg, 'One\n', render)
    appendChunk(msg, '\nTwo\n\nThree', render)

    expect(msg.blocks).to.deep.equal(['<One\n\nTwo>'])
    expect(msg.pending).to.equal('Three')
    expect(msg.content).to.equal('One\n\nTwo\n\nThree')
  })

  it('does not split fenced code blocks at blank lines', () => {
    const msg = streamMessage()

    appendChunk(msg, '```js\na()\n\n', render)
    appendChunk(msg, 'b()\n```\n\nAfter', render)

    expect(msg.blocks).to.deep.equal(['<```js\na()\n\nb()\n```>'])
    expect(msg.pending).to.equal('After')
  })

  it('renders the open block when the stream finishes', () => {
    const msg = streamMessage()

    appendChunk(msg, 'One\n\nTwo', render)
    finishStream(msg, render)

    expect(msg.blocks).to.deep.equal(['<One>', '<Two>'])
    expect(msg.pending).to.equal('')
    expect(msg.streaming).to.equal(false)
  })
})
