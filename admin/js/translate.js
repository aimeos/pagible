/**
 * @license MIT, https://opensource.org/license/mit
 */

import gql from 'graphql-tag'

const TRANSLATE_PAGE = gql`
  mutation ($id: [ID!]!, $lang: [String!]!) {
    translatePage(id: $id, lang: $lang) {
      id
      total
    }
  }
`

const TRANSLATE_PROGRESS = gql`
  query ($batch: ID!) {
    translateProgress(batch: $batch) {
      total
      done
      failed
    }
  }
`

/**
 * Queues the translations of the pages into the languages.
 *
 * Only missing and outdated translations are queued, pages in their source language and
 * up to date translations are skipped by the server.
 *
 * @param {Object} apollo Apollo client
 * @param {Array<String>} ids Page IDs
 * @param {Array<String>} langs Language codes
 * @returns {Promise<Object>} Batch with ID and number of queued translations
 */
export function translatePages(apollo, ids, langs) {
  return apollo.mutate({ mutation: TRANSLATE_PAGE, variables: { id: ids, lang: langs } }).then((result) => {
    if (!result.data?.translatePage) {
      throw new Error('No data in translatePage mutation result')
    }
    return result.data.translatePage
  })
}

/**
 * Returns the progress of queued translations.
 *
 * @param {Object} apollo Apollo client
 * @param {String} batch Batch ID
 * @returns {Promise<Object|null>} Total, done and failed translations or NULL if the batch is unknown
 */
function translateProgress(apollo, batch) {
  return apollo
    .query({ query: TRANSLATE_PROGRESS, variables: { batch }, fetchPolicy: 'no-cache' })
    .then((result) => result.data?.translateProgress || null)
}

// Delay in milliseconds between the polls while the translations progress
const POLL_DELAY = 2000
// Longest delay in milliseconds between the polls while nothing changes or the requests fail
const POLL_MAX_DELAY = 30000
// Number of polls without progress (or failed requests) until the polling is given up
const POLL_MAX_UNCHANGED = 20

/**
 * Polls the progress of queued translations until all are finished.
 *
 * Polls every two seconds and backs off up to 30 seconds while the progress doesn't change or
 * the requests fail. Polling pauses while the browser tab is hidden and stops after 20 polls
 * without any progress.
 *
 * @param {Object} apollo Apollo client
 * @param {String} batch Batch ID
 * @param {Object} options Optional onProgress(progress) callback and cancelled() function stopping the polling
 * @returns {Promise<Object|null>} Final progress, the last progress with "running: true" if the translations
 *  didn't progress any more, or NULL if the batch is unknown or the polling was cancelled
 */
export async function awaitTranslation(apollo, batch, { onProgress = null, cancelled = () => false } = {}) {
  let delay = POLL_DELAY
  let unchanged = 0
  let last = null

  while (!cancelled()) {
    if (document.hidden) {
      await new Promise((resolve) => setTimeout(resolve, delay))
      continue
    }

    let progress

    try {
      progress = await translateProgress(apollo, batch)
    } catch (error) {
      // the translations continue on the server, so temporary errors only delay the next poll
      console.warn('Failed to fetch translation progress, retrying', error)
      progress = undefined
    }

    if (progress === null || cancelled()) {
      return null
    }

    if (progress) {
      onProgress?.(progress)

      if (progress.done + progress.failed >= progress.total) {
        return progress
      }
    }

    if (!progress || (last && progress.done === last.done && progress.failed === last.failed)) {
      if (++unchanged >= POLL_MAX_UNCHANGED) {
        return { total: 0, done: 0, failed: 0, ...last, ...progress, running: true }
      }
      delay = Math.min(delay * 2, POLL_MAX_DELAY)
    } else {
      unchanged = 0
      delay = POLL_DELAY
    }

    last = progress || last
    await new Promise((resolve) => setTimeout(resolve, delay))
  }

  return null
}
