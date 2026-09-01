/** @license MIT, https://opensource.org/license/mit */

<script>
const number = (value) => {
  if (value === null || value === undefined || value === '') return null

  const result = Number(value)
  return Number.isFinite(result) ? result : null
}

/**
 * Configuration:
 * - `default`: object, default location with latitude, longitude and zoom
 * - `required`: boolean, if true, latitude and longitude are required
 * - `zoom`: int, default OpenStreetMap zoom level (1-19)
 */
export default {
  props: {
    modelValue: { type: Object },
    config: { type: Object, default: () => ({}) },
    assets: { type: Object, default: () => ({}) },
    readonly: { type: Boolean, default: false },
    context: { type: Object }
  },

  emits: ['update:modelValue', 'error'],

  data: () => ({ lastError: null }),

  computed: {
    current() {
      const value = this.modelValue ?? this.config.default
      return value && typeof value === 'object' && !Array.isArray(value) ? value : {}
    },

    latitude() {
      return number(this.current.latitude)
    },

    longitude() {
      return number(this.current.longitude)
    },

    zoom() {
      return number(this.current.zoom ?? this.config.zoom ?? 15)
    },

    hasPoint() {
      return ![null, undefined, ''].includes(this.current.latitude)
        || ![null, undefined, ''].includes(this.current.longitude)
    },

    hasError() {
      if (!this.hasPoint) return !!this.config.required

      return !this.valid
    },

    valid() {
      return this.latitude !== null && this.latitude >= -90 && this.latitude <= 90
        && this.longitude !== null && this.longitude >= -180 && this.longitude <= 180
        && this.zoom !== null && Number.isInteger(this.zoom) && this.zoom >= 1 && this.zoom <= 19
    },

    mapUrl() {
      if (!this.valid) return ''

      const span = 360 / (2 ** this.zoom) * 1.5
      const vertical = span * 0.6
      const bbox = [
        this.longitude - span,
        this.latitude - vertical,
        this.longitude + span,
        this.latitude + vertical
      ].map((value) => value.toFixed(6)).join(',')
      const params = new URLSearchParams({
        bbox,
        layer: 'mapnik',
        marker: `${this.latitude.toFixed(6)},${this.longitude.toFixed(6)}`
      })

      return `https://www.openstreetmap.org/export/embed.html?${params}`
    },

    openUrl() {
      if (!this.valid) return ''

      const latitude = this.latitude.toFixed(6)
      const longitude = this.longitude.toFixed(6)
      return `https://www.openstreetmap.org/?mlat=${latitude}&mlon=${longitude}#map=${this.zoom}/${latitude}/${longitude}`
    }
  },

  methods: {
    rules(min, max, integer = false) {
      return [
        (value) => {
          const val = number(value)

          if (val === null) {
            return (!this.config.required && !this.hasPoint) || this.$gettext('Value is required')
          }

          return (val >= min && val <= max && (!integer || Number.isInteger(val)))
            || this.$gettext('Value has invalid format')
        }
      ]
    },

    update(name, value) {
      const location = { ...this.current }
      const val = number(value)

      if (val === null) {
        delete location[name]
      } else {
        location[name] = name === 'zoom' ? Math.round(val) : val
      }

      const latitude = number(location.latitude)
      const longitude = number(location.longitude)

      if (latitude === null && longitude === null) {
        this.$emit('update:modelValue', null)
        return
      }

      if (number(location.zoom) === null) {
        location.zoom = number(this.config.zoom) ?? 15
      }

      this.$emit('update:modelValue', location)
    }
  },

  watch: {
    hasError: {
      immediate: true,
      handler(value) {
        if (value !== this.lastError) {
          this.lastError = value
          this.$emit('error', value)
        }
      }
    }
  }
}
</script>

<template>
  <div class="map-field">
    <div class="coordinates">
      <v-number-input
        class="latitude"
        :label="$gettext('Latitude')"
        :modelValue="latitude"
        :readonly="readonly"
        :rules="rules(-90, 90)"
        :clearable="!readonly"
        :min="-90"
        :max="90"
        :precision="6"
        :step="0.000001"
        @update:modelValue="update('latitude', $event)"
        density="comfortable"
        hide-details="auto"
        variant="outlined"
      />
      <v-number-input
        class="longitude"
        :label="$gettext('Longitude')"
        :modelValue="longitude"
        :readonly="readonly"
        :rules="rules(-180, 180)"
        :clearable="!readonly"
        :min="-180"
        :max="180"
        :precision="6"
        :step="0.000001"
        @update:modelValue="update('longitude', $event)"
        density="comfortable"
        hide-details="auto"
        variant="outlined"
      />
      <v-number-input
        class="zoom"
        :label="$gettext('Zoom')"
        :modelValue="zoom"
        :readonly="readonly"
        :rules="rules(1, 19, true)"
        :min="1"
        :max="19"
        :precision="0"
        :step="1"
        @update:modelValue="update('zoom', $event)"
        density="comfortable"
        hide-details="auto"
        variant="outlined"
      />
    </div>

    <div v-if="mapUrl" class="preview">
      <iframe
        :src="mapUrl"
        :title="$gettext('OpenStreetMap preview')"
        loading="lazy"
        referrerpolicy="strict-origin-when-cross-origin"
        sandbox="allow-scripts allow-same-origin"
      ></iframe>
      <a :href="openUrl" target="_blank" rel="noopener noreferrer">
        {{ $gettext('View larger map') }}
      </a>
    </div>
  </div>
</template>

<style scoped>
.coordinates {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr)) minmax(8rem, 0.5fr);
  gap: 12px;
}

.preview {
  margin-top: 16px;
}

.preview iframe {
  display: block;
  width: 100%;
  min-height: 280px;
  border: 1px solid rgb(var(--v-theme-surface-variant));
  border-radius: 4px;
}

.preview a {
  display: inline-block;
  margin-top: 8px;
}

@media (max-width: 700px) {
  .coordinates {
    grid-template-columns: 1fr;
  }
}
</style>
