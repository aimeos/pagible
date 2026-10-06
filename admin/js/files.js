/**
 * @license MIT, https://opensource.org/license/mit
 */

import gql from 'graphql-tag'
import { invalidateList } from './graphql'
import { fileurl, safeParse, sanitize } from './utils'

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

const ADD_FILE = gql`
  ${FILE_FIELDS}
  mutation ($input: FileInput, $file: Upload, $disk: FileDisk) {
    addFile(input: $input, file: $file, disk: $disk) {
      ...CmsFileFields
    }
  }
`

export async function createFile(apollo, variables) {
  const options = { mutation: ADD_FILE, variables }

  if (variables.file) {
    options.context = { hasUpload: true }
  }

  const response = await apollo.mutate(options)

  return normalizeFile(response.data?.addFile)
}

const RELOCATE_FILE = gql`
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

export function fileMap(entries) {
  const map = {}
  for (const entry of entries) map[entry.id] = normalizeFile(entry)
  return map
}

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

export function previewUrl(file) {
  return fileurl(file, Object.values(file.previews || {})[0] ?? file.path)
}

export async function relocateFiles(apollo, ids, disk) {
  const response = await apollo.mutate({ mutation: RELOCATE_FILE, variables: { id: ids, disk } })
  invalidateList('files')
  return response.data?.relocateFile || []
}

export function revokeBlob(item) {
  if (item?.path?.startsWith('blob:')) {
    URL.revokeObjectURL(item.path)
  }
}
