/**
 * @license MIT, https://opensource.org/license/mit
 */

import gql from 'graphql-tag'
import { safeParse, sanitize } from './utils'

export const FILE_FIELDS = gql`
  fragment CmsFileFields on File {
    disk
    id
    lang
    mime
    name
    path
    previews
    description
    transcription
    editor
    created_at
    updated_at
    deleted_at
    latest {
      data
      aux
    }
  }
`

export const ADD_FILE = gql`
  ${FILE_FIELDS}
  mutation ($input: FileInput, $file: Upload, $disk: FileDisk) {
    addFile(input: $input, file: $file, disk: $disk) {
      ...CmsFileFields
    }
  }
`

export const RELOCATE_FILE = gql`
  mutation ($id: [ID!]!, $disk: FileDisk!) {
    relocateFile(id: $id, disk: $disk) {
      disk
      id
      editor
      updated_at
    }
  }
`

export const FETCH_FILE_DISKS = gql`
  query ($id: [ID!]!) {
    files(filter: { id: $id }, first: 100) {
      data {
        disk
        id
        editor
        updated_at
      }
    }
  }
`

export function normalizeFile(data = {}) {
  const parse = (value) => typeof value === 'string' ? safeParse(value) : sanitize(value || {})
  const item = {
    ...data,
    ...parse(data.latest?.data),
    ...parse(data.latest?.aux),
    disk: data.disk,
    id: data.id
  }

  for (const field of ['previews', 'description', 'transcription']) {
    item[field] = Object.freeze(parse(item[field]))
  }

  delete item.__typename
  delete item.latest

  return item
}
