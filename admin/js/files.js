/**
 * @license MIT, https://opensource.org/license/mit
 */

import gql from 'graphql-tag'
import { frozenParse } from './utils'

const FILE_FIELDS = gql`
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

export function normalizeFile(data = {}) {
  for (const field of ['previews', 'description', 'transcription']) {
    data[field] = frozenParse(data[field])
  }

  delete data.__typename

  return data
}
