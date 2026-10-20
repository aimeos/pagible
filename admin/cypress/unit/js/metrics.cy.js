import { QUERY_PAGE_SIZE, apiError, compact, insightValue, paginate, ranked, rating, signed, sortedByValue, timeline, toMinutes, toPercent, weeklySum } from '../../../js/metrics'

describe('metrics', () => {
  it('extracts the message of JSON encoded API errors', () => {
    expect(apiError('{"error":{"message":"Quota exceeded"}}')).to.equal('Quota exceeded')
    expect(apiError('Plain error')).to.equal('Plain error')
  })

  it('shortens large numbers', () => {
    expect(compact(999)).to.equal('999')
    expect(compact(12.5, 'de')).to.equal('12,5')
    expect(compact(1500)).to.equal('1.50K')
    expect(compact(2500000)).to.equal('2.50M')
    expect(compact(3e9)).to.equal('3.00B')
  })

  it('formats the shortened numbers for the locale', () => {
    expect(compact(1500, 'de')).to.equal('1,50K')
    expect(compact(1234567, 'en')).to.equal('1.23M')
    expect(insightValue({ data: [{ value: 1500 }] }, 'de')).to.equal('1,50K')
  })

  it('sums the values of the last and the previous week', () => {
    const data = Array.from({ length: 14 }, (_, i) => ({ value: i < 7 ? 1 : 2 }))
    expect(weeklySum(data)).to.equal(14)
    expect(weeklySum(data, 2)).to.equal(7)
  })

  it('returns the weekly sum or the daily average of insight columns', () => {
    const data = Array.from({ length: 7 }, () => ({ value: 2000 }))
    expect(insightValue({ data })).to.equal('14.00K')
    expect(insightValue({ data, average: true, suffix: '%' }, 'en')).to.equal('2,000.0%')
    expect(insightValue({ data: [{ value: 21 }], average: true }, 'de')).to.equal('3,0')
  })

  it('returns QUERY_PAGE_SIZE items per page', () => {
    expect(QUERY_PAGE_SIZE).to.equal(10)

    const items = Array.from({ length: 25 }, (_, i) => i)
    expect(paginate(items, 1)).to.deep.equal(items.slice(0, 10))
    expect(paginate(items, 3)).to.deep.equal([20, 21, 22, 23, 24])
  })

  it('sorts rows by value and shortens the values', () => {
    expect(ranked([{ key: 'a', value: 5 }, { key: 'b', value: 1200 }])).to.deep.equal([
      { key: 'b', value: '1.20K' },
      { key: 'a', value: '5' }
    ])
    expect(ranked(null)).to.deep.equal([])
  })

  it('does not change the passed rows when ranking them', () => {
    const rows = [{ key: 'a', value: 5 }, { key: 'b', value: 1200 }]
    ranked(rows)
    expect(rows).to.deep.equal([{ key: 'a', value: 5 }, { key: 'b', value: 1200 }])
  })

  it('does not change the passed rows when creating a timeline', () => {
    const formatter = { format: (date) => date.toISOString().slice(5, 10) }
    const rows = [{ key: '2026-01-02', value: 120 }, { key: '2026-01-01', value: 60 }]
    timeline(rows, formatter, toMinutes)
    expect(rows).to.deep.equal([{ key: '2026-01-02', value: 120 }, { key: '2026-01-01', value: 60 }])
  })

  it('rates values by their thresholds', () => {
    expect(rating(null, 1, 2)).to.equal('')
    expect(rating(0.5, 1, 2)).to.equal('good')
    expect(rating(1.5, 1, 2)).to.equal('warn')
    expect(rating(2, 1, 2)).to.equal('bad')
  })

  it('adds a plus sign to positive changes', () => {
    expect(signed(12.4)).to.equal('+12')
    expect(signed(-3)).to.equal('-3')
    expect(signed(0)).to.equal('0')
  })

  it('sorts a copy of the rows by value', () => {
    const rows = [{ value: 1 }, { value: 3 }]
    expect(sortedByValue(rows)).to.deep.equal([{ value: 3 }, { value: 1 }])
    expect(rows[0].value).to.equal(1)
  })

  it('sorts by date, formats the keys and transforms the values', () => {
    const formatter = { format: (date) => date.toISOString().slice(5, 10) }
    const rows = timeline([{ key: '2026-01-02', value: 120 }, { key: '2026-01-01', value: 60 }], formatter, toMinutes)

    expect(rows).to.deep.equal([{ key: '01-01', value: 1 }, { key: '01-02', value: 2 }])
  })

  it('converts ratios to percent', () => {
    expect(toPercent({ value: 0.1234 }).value).to.equal(12.3)
  })
})
