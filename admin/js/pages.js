/**
 * @license MIT, https://opensource.org/license/mit
 */

import gql from 'graphql-tag'
import { safeParse } from './json'

// editor name of AI translated drafts, same as Aimeos\Cms\Sync::EDITOR
export const AI_EDITOR = 'AI draft'

// maximum number of pages changed by one bulk mutation
export const PAGE_BULK_LIMIT = 1000

// fields of the page tree rows, event patches only update these or already set ones
const PAGE_TREE_FIELDS = new Set([
  'access',
  'cache',
  'created_at',
  'deleted_at',
  'domain',
  'editor',
  'has',
  'id',
  'lang',
  'latest_id',
  'name',
  'parent_id',
  'path',
  'publish_at',
  'published',
  'restricted',
  'source',
  'stale',
  'status',
  'tag',
  'theme',
  'title',
  'to',
  'type',
  'updated_at',
  'variant_deleted_at'
])

/**
 * Copies the fields of an event patch to the data of a tree row
 */
export function patchData(data, item) {
  for (const key in item) {
    if (key in data || PAGE_TREE_FIELDS.has(key)) {
      data[key] = item[key]
    }
  }
}

export const CLEAR_CACHE = gql`
  mutation ($ids: [ID!]!) {
    clearCache(ids: $ids)
  }
`

export const INSERT_PAGE = gql`
  mutation ($input: PageInput!, $parent: ID, $ref: ID) {
    addPage(input: $input, parent: $parent, ref: $ref) {
      id
    }
  }
`

export const MOVE_PAGE = gql`
  mutation ($id: ID!, $parent: ID, $ref: ID) {
    movePage(id: $id, parent: $parent, ref: $ref) {
      id
    }
  }
`

export const SAVE_PAGES = gql`
  mutation ($id: [ID!]!, $input: PageInput!, $descendants: Boolean, $lang: String) {
    bulkPage(id: $id, input: $input, descendants: $descendants, lang: $lang) {
      ids
      latest
      data
      failed
    }
  }
`

const PAGE_FIELDS = `id
          access @include(if: $access)
          parent_id
          lang
          source
          stale
          created_at
          deleted_at
          variant_deleted_at
          editor
          has
          restricted
          latest {
            id
            published
            publish_at
            data
            editor
            created_at
          }`

export const ADD_PAGE_VARIANT = gql`
  mutation ($id: ID!, $lang: String!, $access: Boolean!) {
    addPageVariant(id: $id, lang: $lang) {
      ${PAGE_FIELDS}
    }
  }
`

export const COPY_PAGE = gql`
  mutation ($id: ID!, $parent: ID, $ref: ID) {
    copyPage(id: $id, parent: $parent, ref: $ref) {
      id
    }
  }
`

export const FETCH_PAGES = gql`
  query(
    $filter: PageFilter,
    $sort: [QueryPagesSortOrderByClause!],
    $limit: Int!,
    $page: Int!,
    $trashed: Trashed,
    $publish: Publish,
    $lang: String,
    $access: Boolean!
  ) {
    pages(
      filter: $filter,
      sort: $sort,
      first: $limit,
      page: $page,
      trashed: $trashed,
      publish: $publish,
      lang: $lang
    ) {
      data {
        ${PAGE_FIELDS}
        variants(lang: $lang) {
          state
        }
      }
      paginatorInfo {
        currentPage
        lastPage
      }
    }
  }
`

/**
 * Returns the version related fields of an item, taken from its latest version if available
 *
 * @param {Object} entry Page, element or file returned by the GraphQL API incl. its latest version
 * @returns {Object} Modification date, editor, publish state and ID of the latest version
 */
export function versionMeta(entry) {
  const latest = entry.latest

  return {
    updated_at: latest?.created_at || entry.updated_at,
    editor: latest?.editor || entry.editor,
    published: latest?.published ?? true,
    publish_at: latest?.publish_at || null,
    latest_id: latest?.id || null
  }
}

/**
 * Returns the data of a tree row from a page returned by the GraphQL API
 *
 * @param {Object} entry Page with its latest version and the variant state in the current language
 * @returns {Object} Page data of the latest version incl. the page's own fields
 */
export function hydrate(entry) {
  const item = entry.latest?.data ? safeParse(entry.latest.data) : { ...entry }

  return Object.assign(item, {
    access: entry.access,
    id: entry.id,
    has: entry.has,
    restricted: entry.restricted,
    parent_id: entry.parent_id,
    deleted_at: entry.deleted_at,
    created_at: entry.created_at,
    ...versionMeta(entry),
    lang: entry.lang ?? item.lang ?? null,
    source: entry.source ?? null,
    stale: !!entry.stale,
    variant_deleted_at: entry.variant_deleted_at || null,
    // the variant in the current language is in the trash and the source variant is shown instead
    variant_trashed: entry.variants?.[0]?.state === 'trashed'
  })
}
