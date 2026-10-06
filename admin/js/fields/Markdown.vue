/** @license MIT, https://opensource.org/license/mit */

<script>
import {
  Markdown,
  Essentials,
  PasteFromOffice,
  Fullscreen,
  Clipboard,
  FindAndReplace,
  RemoveFormat,
  Heading,
  Paragraph,
  Bold,
  Italic,
  Strikethrough,
  BlockQuote,
  Code,
  CodeBlock,
  AutoLink,
  Link,
  List,
  SourceEditing
} from 'ckeditor5'
import Text from './Text.vue'

const plugins = [
  Markdown,
  Essentials,
  PasteFromOffice,
  Fullscreen,
  Clipboard,
  FindAndReplace,
  RemoveFormat,
  Heading,
  Paragraph,
  Bold,
  Italic,
  Strikethrough,
  BlockQuote,
  Code,
  CodeBlock,
  AutoLink,
  Link,
  List,
  SourceEditing
]

const toolbar = [
  'undo',
  'redo',
  'removeFormat',
  '|',
  'heading',
  '|',
  'bold',
  'italic',
  'strikethrough',
  'link',
  'code',
  '|',
  'codeBlock',
  'blockQuote',
  '|',
  'bulletedList',
  'numberedList',
  '|',
  'sourceEditing',
  'fullscreen'
]

/**
 * Configuration:
 * - `hint`: string, description shown below the field while it has focus
 * - `max`: int, maximum number of characters allowed
 * - `min`: int, minimum number of characters required if the field isn't empty
 * - `required`: boolean, if true, the field must not be empty
 */
export default {
  extends: Text,

  setup() {
    return { plugins, toolbar }
  }
}
</script>

