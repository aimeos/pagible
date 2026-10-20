/**
 * @license MIT, https://opensource.org/license/mit
 */

import gql from 'graphql-tag'
import { canvasBlob, toBlob } from './files'
import { hasTrue } from './utils'

const ERASE_IMAGE = gql`
  mutation ($file: Upload!, $mask: Upload!) {
    erase(file: $file, mask: $mask)
  }
`

const INPAINT_IMAGE = gql`
  mutation ($file: Upload!, $mask: Upload!, $prompt: String!) {
    inpaint(file: $file, mask: $mask, prompt: $prompt)
  }
`

const ISOLATE_IMAGE = gql`
  mutation ($file: Upload!) {
    isolate(file: $file)
  }
`

const REPAINT_IMAGE = gql`
  mutation ($file: Upload!, $prompt: String!) {
    repaint(file: $file, prompt: $prompt)
  }
`

const UNCROP_IMAGE = gql`
  mutation ($file: Upload!, $top: Int!, $right: Int!, $bottom: Int, $left: Int) {
    uncrop(file: $file, top: $top, right: $right, bottom: $bottom, left: $left)
  }
`

const UPSCALE_IMAGE = gql`
  mutation ($file: Upload!, $factor: Int!) {
    upscale(file: $file, factor: $factor)
  }
`

/**
 * AI operations on the image shown in the image editor: erasing, inpainting, repainting,
 * isolating, uncropping and upscaling the image or the selected area
 *
 * Requires the item and readonly props, the cropper, selected, width, height, user and messages
 * properties and the clear(), image() and replace() methods.
 */
export const imageAi = {
  data() {
    return {
      edittext: null,
      extend: {
        top: 0,
        right: 0,
        bottom: 0,
        left: 0
      },
      loading: {},
      menu: {},
      preparing: false
    }
  },

  computed: {
    // only one AI operation at a time, the last result would replace the others otherwise
    aiBusy() {
      return this.preparing || hasTrue(this.loading)
    },

    edges() {
      const hint = ' ‒ ' + this.$gettext('Number of pixels added at this side of the image')

      return [
        [['top', this.$pgettext('image edge', 'Top') + hint]],
        [
          ['left', this.$pgettext('image edge', 'Left') + hint],
          ['right', this.$pgettext('image edge', 'Right') + hint]
        ],
        [['bottom', this.$pgettext('image edge', 'Bottom') + hint]]
      ]
    },

    factors() {
      return [16, 8, 4, 2].filter((f) => this.width * f <= 4096 && this.height * f <= 4096)
    }
  },

  methods: {
    erase() {
      if (!this.cropper || this.aiBusy) return
      return this.run('image:erase', ERASE_IMAGE, this.$gettext('Error erasing image part'), () => this.masked())
    },

    inpaint() {
      if (!this.cropper || this.aiBusy || !this.edittext?.trim()) {
        return
      }

      return this.run('image:inpaint', INPAINT_IMAGE, this.$gettext('Error editing image part'), () =>
        this.masked().then((files) => ({ ...files, prompt: this.edittext }))
      )
    },

    isolate() {
      if (!this.cropper || this.aiBusy) return

      // without a crop box, the whole image is used
      this.clear()

      return this.run('image:isolate', ISOLATE_IMAGE, this.$gettext('Error removing background'), () =>
        this.croppedFile().then((file) => ({ file }))
      )
    },

    mask() {
      if (!this.cropper) return null

      const canvas = document.createElement('canvas')
      const context = canvas.getContext('2d')

      const data = this.cropper.getImageData()
      const crop = this.cropper.getData()

      canvas.width = data.naturalWidth
      canvas.height = data.naturalHeight

      context.fillStyle = 'black'
      context.fillRect(0, 0, canvas.width, canvas.height)

      context.fillStyle = 'white'
      context.fillRect(crop.x, crop.y, crop.width, crop.height)

      const origToBlob = canvas.toBlob.bind(canvas)
      canvas.toBlob = (callback, ...args) => {
        origToBlob((blob) => {
          canvas.width = 0
          canvas.height = 0
          callback(blob)
        }, ...args)
      }

      return canvas
    },

    // returns the cropped area or the whole image as PNG upload file
    croppedFile() {
      return canvasBlob(this.cropper.getCroppedCanvas()).then((blob) =>
        new File([blob], 'image.png', { type: 'image/png' })
      )
    },

    // edited images are PNG files while the original keeps its own type
    imageFile(blob) {
      return new File([blob], 'image', { type: blob.type || this.item.mime })
    },

    // returns the image and the mask of the selected area as upload files
    masked() {
      return this.image().then((blob) =>
        canvasBlob(this.mask()).then((mask) => ({
          file: this.imageFile(blob),
          mask: new File([mask], 'mask', { type: 'image/png' })
        }))
      )
    },

    // runs the AI image mutation and replaces the image by the result
    mutate(action, mutation, variables, msg) {
      if (this.readonly || !this.user.can(action)) {
        this.messages.denied()
        return this.clear()
      }

      // only one operation at a time, run() resets the preparing state before calling this method
      if (this.aiBusy) {
        return Promise.resolve()
      }

      this.loading[action] = true

      // restoring another version meanwhile makes the result obsolete
      const path = this.item.path

      return this.$apollo
        .mutate({
          mutation,
          variables,
          context: { hasUpload: true }
        })
        .then((response) => {
          if (this.item.path === path) {
            this.replace(toBlob(response.data?.[action.split(':')[1]]))
          }
        })
        .catch((error) => this.messages.error(msg, error))
        .finally(() => {
          this.loading[action] = false
          this.clear()
        })
    },

    painted() {
      this.selected ? this.inpaint() : this.repaint()
      this.menu['paint'] = false
    },

    repaint() {
      if (this.aiBusy || !this.edittext?.trim()) {
        return
      }

      return this.run('image:repaint', REPAINT_IMAGE, this.$gettext('Error editing image'), () =>
        this.image().then((blob) => ({
          file: this.imageFile(blob),
          prompt: this.edittext
        }))
      )
    },

    /**
     * Prepares the upload files and runs the AI mutation
     *
     * Preparing the image counts as running operation too, so no other one can start meanwhile.
     *
     * @param {string} action Permission and loading key of the operation, e.g. "image:erase"
     * @param {object} mutation GraphQL mutation document
     * @param {string} msg Translated, user-facing error message
     * @param {() => Promise<object>} prepare Returns the mutation variables
     * @returns {Promise<void>} Resolves when the operation finished, errors are reported
     */
    run(action, mutation, msg, prepare) {
      this.preparing = true

      // a synchronous error in prepare() must reset the preparing state too
      return Promise.resolve()
        .then(prepare)
        .then((variables) => {
          this.preparing = false
          return this.mutate(action, mutation, variables, msg)
        })
        .catch((error) => this.messages.error(msg, error))
        .finally(() => {
          this.preparing = false
        })
    },

    uncrop() {
      if (!this.cropper || this.aiBusy || (!this.extend.top && !this.extend.right && !this.extend.bottom && !this.extend.left)) {
        return
      }

      // without a crop box, the whole image is used
      this.clear()

      return this.run('image:uncrop', UNCROP_IMAGE, this.$gettext('Error uncropping image'), () =>
        this.croppedFile().then((file) => ({
          file,
          top: this.extend.top ?? 0,
          right: this.extend.right ?? 0,
          bottom: this.extend.bottom ?? 0,
          left: this.extend.left ?? 0
        }))
      )
    },

    uncropped() {
      this.uncrop()
      this.menu['uncrop'] = false
    },

    upscale(factor) {
      if (!this.cropper || this.aiBusy) return

      // without a crop box, the whole image is used
      this.clear()

      return this.run('image:upscale', UPSCALE_IMAGE, this.$gettext('Error upscaling image'), () =>
        this.croppedFile().then((file) => ({ file, factor }))
      )
    }
  }
}
