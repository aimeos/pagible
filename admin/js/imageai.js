/**
 * @license MIT, https://opensource.org/license/mit
 */

import gql from 'graphql-tag'
import { toBlob } from './files'

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
 * Requires the item and readonly props, the cropper, width, height, user and messages properties
 * and the clear(), image() and replace() methods.
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
      menu: {}
    }
  },

  computed: {
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
      if (!this.cropper) return
      this.masked().then((files) => {
        this.mutate('image:erase', ERASE_IMAGE, files, this.$gettext('Error erasing image part'))
      })
    },

    inpaint() {
      if (!this.cropper || !this.edittext?.trim()) {
        return
      }

      this.masked().then((files) => {
        const vars = { ...files, prompt: this.edittext }
        this.mutate('image:inpaint', INPAINT_IMAGE, vars, this.$gettext('Error editing image part'))
      })
    },

    isolate() {
      if (!this.cropper) return

      this.clear()

      this.cropper.getCroppedCanvas().toBlob((blob) => {
        this.mutate('image:isolate', ISOLATE_IMAGE, {
          file: new File([blob], 'image.png', { type: 'image/png' })
        }, this.$gettext('Error removing background'))
      })
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

    // returns the image and the mask of the selected area as upload files
    masked() {
      return this.image().then((blob) =>
        new Promise((resolve) => this.mask().toBlob(resolve)).then((mask) => ({
          file: new File([blob], 'image', { type: this.item.mime }),
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

      this.loading[action] = true

      return this.$apollo
        .mutate({
          mutation,
          variables,
          context: { hasUpload: true }
        })
        .then((response) => this.replace(toBlob(response.data?.[action.split(':')[1]])))
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
      if (!this.edittext?.trim()) {
        return
      }

      this.image().then((blob) => {
        this.mutate('image:repaint', REPAINT_IMAGE, {
          file: new File([blob], 'image', { type: this.item.mime }),
          prompt: this.edittext
        }, this.$gettext('Error editing image'))
      })
    },

    uncrop() {
      if (!this.cropper || (!this.extend.top && !this.extend.right && !this.extend.bottom && !this.extend.left)) {
        return
      }

      this.clear()

      this.cropper.getCroppedCanvas().toBlob((blob) => {
        this.mutate('image:uncrop', UNCROP_IMAGE, {
          file: new File([blob], 'image.png', { type: 'image/png' }),
          top: this.extend.top ?? 0,
          right: this.extend.right ?? 0,
          bottom: this.extend.bottom ?? 0,
          left: this.extend.left ?? 0
        }, this.$gettext('Error uncropping image'))
      })
    },

    uncropped() {
      this.uncrop()
      this.menu['uncrop'] = false
    },

    upscale(factor) {
      if (!this.cropper) return

      this.clear()

      this.cropper.getCroppedCanvas().toBlob((blob) => {
        this.mutate('image:upscale', UPSCALE_IMAGE, {
          file: new File([blob], 'image.png', { type: 'image/png' }),
          factor: factor
        }, this.$gettext('Error upscaling image'))
      })
    }
  }
}
