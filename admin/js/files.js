/**
 * @license MIT, https://opensource.org/license/mit
 */

import gql from 'graphql-tag'
import { invalidateList } from './graphql'
import { fileurl } from './urls'
import { safeParse, sanitize } from './json'

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

export const IMAGE_MIME_FILTER = { mime: ['image/gif', 'image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'] }

export const AUDIO_MIME_FILTER = { mime: ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/ogg', 'audio/webm', 'audio/wav', 'audio/wave', 'audio/x-wav', 'audio/vnd.wave', 'audio/flac', 'audio/x-flac'] }

export const VIDEO_MIME_FILTER = { mime: ['video/mp4', 'video/webm', 'video/ogg'] }

export const MEDIA_MIME_FILTER = { mime: [...IMAGE_MIME_FILTER.mime, ...VIDEO_MIME_FILTER.mime] }

/**
 * Converts a base64-encoded string to a Blob
 *
 * @param {string} base64 Base64-encoded binary data
 * @param {string} mimeType MIME type for the resulting Blob
 * @returns {Blob|null} Blob object or null if input is empty
 */
export function toBlob(base64, mimeType = 'image/png') {
  if (!base64) {
    return null
  }

  const binary = atob(base64)
  const byteArray = new Uint8Array(binary.length)

  for (let i = 0; i < binary.length; i++) {
    byteArray[i] = binary.charCodeAt(i)
  }

  return new Blob([byteArray], { type: mimeType })
}
