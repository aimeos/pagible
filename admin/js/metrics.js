/** @license MIT, https://opensource.org/license/mit */

/**
 * Error message of a failed metrics provider, which may be a JSON encoded API response.
 */
export function apiError(msg) {
  try {
    return JSON.parse(msg)?.error?.message || msg
  } catch {
    return msg
  }
}

/**
 * Number of search queries shown per page.
 */
export const QUERY_PAGE_SIZE = 10

/**
 * Shortens large numbers formatted for the locale, e.g. 1500 to "1.50K".
 */
export function compact(v, locale = undefined) {
  const format = (num, unit) =>
    new Intl.NumberFormat(locale, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num) + unit

  switch (true) {
    case v >= 1e9:
      return format(v / 1e9, 'B')
    case v >= 1e6:
      return format(v / 1e6, 'M')
    case v >= 1e3:
      return format(v / 1e3, 'K')
  }
  return new Intl.NumberFormat(locale, { maximumFractionDigits: 2 }).format(v)
}

/**
 * Value shown for an insight column, the sum or the daily average of the last week.
 */
export function insightValue(col, locale = undefined) {
  return col.average
    ? new Intl.NumberFormat(locale, { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(weeklySum(col.data) / 7) +
      (col.suffix || '')
    : compact(weeklySum(col.data), locale)
}

/**
 * Items of the page when showing QUERY_PAGE_SIZE items per page.
 */
export function paginate(items, page) {
  const start = (page - 1) * QUERY_PAGE_SIZE
  return items.slice(start, start + QUERY_PAGE_SIZE)
}

/**
 * Rows sorted by their value, the highest first, and their values shortened.
 */
export function ranked(data, locale = undefined) {
  return [...(data || [])].sort(byValue).map((item) => ({ ...item, value: compact(item.value, locale) }))
}

/**
 * Rating of a page speed value, "good" below the good and "bad" from the bad threshold on.
 */
export function rating(value, good, bad) {
  if (value === undefined || value === null) {
    return ''
  } else if (value < good) {
    return 'good'
  } else if (value >= bad) {
    return 'bad'
  }
  return 'warn'
}

/**
 * Percentage change rounded to an integer with a leading "+" sign if positive.
 */
export function signed(v) {
  return (v > 0 ? '+' : '') + Number(v).toFixed(0)
}

/**
 * Copy of the rows sorted by their value, the highest first.
 */
export function sortedByValue(rows) {
  return [...rows].sort(byValue)
}

/**
 * Rows sorted by their date keys with the keys formatted and the values transformed.
 */
export function timeline(data, formatter, transform = null) {
  // copies the rows because transform() changes them in place
  return [...(data || [])].sort(byDate).map((row) => {
    const item = { ...row, key: formatter.format(new Date(row.key)) }
    transform?.(item)
    return item
  })
}

/**
 * Converts a duration value in seconds to minutes with one decimal.
 */
export function toMinutes(item) {
  item.value = Math.round(item.value / 6) / 10
  return item
}

/**
 * Converts a ratio value to percent with one decimal.
 */
export function toPercent(item) {
  item.value = Math.round(item.value * 1000) / 10
  return item
}

/**
 * Sum of the values of the given week, counted backwards from the last day (1 = last week).
 */
export function weeklySum(data, week = 1) {
  return data
    .slice(-7 * week, data.length - 7 * (week - 1))
    .reduce((acc, item) => acc + Number(item.value), 0)
}

function byDate(a, b) {
  return a.key > b.key ? 1 : a.key < b.key ? -1 : 0
}

function byValue(a, b) {
  return b.value - a.value
}
