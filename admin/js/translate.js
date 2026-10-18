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

/**
 * Polls the progress of queued translations until all are finished.
 *
 * Polls every two seconds and backs off up to 30 seconds while the progress doesn't change.
 * Polling pauses while the browser tab is hidden and stops after 20 polls without any progress.
 *
 * @param {Object} apollo Apollo client
 * @param {String} batch Batch ID
 * @param {Object} options Optional onProgress(progress) callback and cancelled() function stopping the polling
 * @returns {Promise<Object|null>} Final progress, the last progress with "running: true" if the translations
 *  didn't progress any more, or NULL if the batch is unknown or the polling was cancelled
 */
export async function awaitTranslation(apollo, batch, { onProgress = null, cancelled = () => false } = {}) {
  let delay = 2000
  let unchanged = 0
  let last = null

  while (!cancelled()) {
    if (document.hidden) {
      await new Promise((resolve) => setTimeout(resolve, delay))
      continue
    }

    const progress = await translateProgress(apollo, batch)

    if (!progress || cancelled()) {
      return null
    }

    onProgress?.(progress)

    if (progress.done + progress.failed >= progress.total) {
      return progress
    }

    if (last && progress.done === last.done && progress.failed === last.failed) {
      if (++unchanged >= 20) {
        return { ...progress, running: true }
      }
      delay = Math.min(delay * 2, 30000)
    } else {
      unchanged = 0
      delay = 2000
    }

    last = progress
    await new Promise((resolve) => setTimeout(resolve, delay))
  }

  return null
}
