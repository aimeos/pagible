import { transcription } from '../../../js/audio'

describe('transcription()', () => {
  describe('asText()', () => {
    it('returns empty string for empty list', () => {
      expect(transcription().asText()).to.equal('')
    })

    it('joins text segments with newline by default', () => {
      const list = [
        { start: 0, end: 1, text: 'Hello' },
        { start: 1, end: 2, text: 'World' },
      ]
      expect(transcription(list).asText()).to.equal('Hello\nWorld')
    })

    it('uses a custom separator', () => {
      const list = [
        { start: 0, end: 1, text: 'Hello' },
        { start: 1, end: 2, text: 'World' },
      ]
      expect(transcription(list).asText(' ')).to.equal('Hello World')
    })

    it('handles missing text property gracefully', () => {
      const list = [{ start: 0, end: 1 }, { start: 1, end: 2, text: 'OK' }]
      expect(transcription(list).asText()).to.equal('\nOK')
    })
  })

  describe('asVTT()', () => {
    it('returns "WEBVTT\\n\\n" for an empty list', () => {
      expect(transcription().asVTT()).to.equal('WEBVTT\n\n')
    })

    it('formats a single cue correctly', () => {
      const list = [{ start: 0, end: 1.5, text: 'Hello' }]
      const vtt = transcription(list).asVTT()
      expect(vtt).to.contain('WEBVTT')
      expect(vtt).to.contain('1\n00:00:00.000 --> 00:00:01.500\nHello')
    })

    it('formats timestamps for long durations correctly', () => {
      const list = [{ start: 3661.123, end: 7322.456, text: 'Long' }]
      const vtt = transcription(list).asVTT()
      expect(vtt).to.contain('01:01:01.123 --> 02:02:02.456')
    })

    it('handles multiple cues with sequential numbering', () => {
      const list = [
        { start: 0, end: 1, text: 'First' },
        { start: 1, end: 2, text: 'Second' },
        { start: 2, end: 3, text: 'Third' },
      ]
      const vtt = transcription(list).asVTT()
      expect(vtt).to.contain('1\n00:00:00.000 --> 00:00:01.000\nFirst')
      expect(vtt).to.contain('2\n00:00:01.000 --> 00:00:02.000\nSecond')
      expect(vtt).to.contain('3\n00:00:02.000 --> 00:00:03.000\nThird')
    })

    it('defaults start, end, and text when properties are missing', () => {
      const list = [{}]
      const vtt = transcription(list).asVTT()
      expect(vtt).to.contain('1\n00:00:00.000 --> 00:00:00.000\n')
    })

    it('formats sub-second timestamps with 3-digit milliseconds', () => {
      const list = [{ start: 0.5, end: 0.75, text: 'Quick' }]
      const vtt = transcription(list).asVTT()
      expect(vtt).to.contain('00:00:00.500 --> 00:00:00.750')
    })
  })
})
