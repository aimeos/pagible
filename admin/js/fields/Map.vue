/** @license MIT, https://opensource.org/license/mit */

<script>
import { markRaw } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const number = (value) => {
  if (value === null || value === undefined || value === '') return null

  const result = Number(value)
  return Number.isFinite(result) ? result : null
}

const limitedZoom = (value, fallback = 15) => {
  const zoom = number(value)
  return zoom === null ? fallback : Math.min(19, Math.max(1, Math.round(zoom)))
}

const markerIcon = () => L.divIcon({
  className: 'location-marker',
  html: '<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5Z"/></svg>',
  iconAnchor: [18, 42],
  iconSize: [36, 44]
})

/**
 * Configuration:
 * - `default`: object, default location with latitude, longitude and zoom
 * - `required`: boolean, if true, a location is required
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

  data: () => ({
    lastError: null,
    map: null,
    marker: null,
    syncing: false
  }),

  computed: {
    current() {
      const value = this.modelValue ?? this.config.default
      return value && typeof value === 'object' && !Array.isArray(value) ? value : {}
    },

    hasError() {
      if (!this.hasPoint) return !!this.config.required

      return !this.valid
    },

    hasPoint() {
      return ![null, undefined, ''].includes(this.current.latitude)
        || ![null, undefined, ''].includes(this.current.longitude)
    },

    latitude() {
      return number(this.current.latitude)
    },

    longitude() {
      return number(this.current.longitude)
    },

    pointValid() {
      return this.latitude !== null && this.latitude >= -90 && this.latitude <= 90
        && this.longitude !== null && this.longitude >= -180 && this.longitude <= 180
    },

    valid() {
      return this.pointValid
        && this.zoom !== null && Number.isInteger(this.zoom) && this.zoom >= 1 && this.zoom <= 19
    },

    zoom() {
      return number(this.current.zoom ?? this.config.zoom ?? 15)
    }
  },

  mounted() {
    const center = this.pointValid ? [this.latitude, this.longitude] : [0, 0]
    const zoom = this.pointValid ? limitedZoom(this.zoom) : 2

    this.map = markRaw(L.map(this.$refs.map, {
      center,
      zoom,
      maxZoom: 19,
      scrollWheelZoom: false,
      worldCopyJump: true
    }))

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      maxZoom: 19
    }).addTo(this.map)

    this.map.on('click', this.select)
    this.map.on('zoomend', this.updateZoom)
    this.sync()
  },

  beforeUnmount() {
    this.map?.remove()
    this.map = null
    this.marker = null
  },

  methods: {
    clear() {
      if (this.readonly) return

      this.removeMarker()
      this.$emit('update:modelValue', null)
    },

    mark(location) {
      if (this.marker) {
        this.marker.setLatLng(location)
        return
      }

      this.marker = markRaw(L.marker(location, {
        icon: markerIcon(),
        interactive: false
      }).addTo(this.map))
    },

    removeMarker() {
      if (this.marker) {
        this.marker.remove()
        this.marker = null
      }
    },

    select(event) {
      if (this.readonly) return

      const initial = !this.pointValid
      const zoom = initial ? limitedZoom(this.config.zoom) : this.map.getZoom()
      const latitude = Number(event.latlng.lat.toFixed(6))
      const longitude = Number(L.Util.wrapNum(event.latlng.lng, [-180, 180], true).toFixed(6))

      if (initial) {
        this.syncing = true
        this.map.setView([latitude, longitude], zoom, { animate: false })
        this.syncing = false
      }

      this.mark([latitude, longitude])
      this.$emit('update:modelValue', { ...this.current, latitude, longitude, zoom })
    },

    sync() {
      if (!this.map) return

      if (!this.pointValid) {
        this.removeMarker()
        return
      }

      const location = L.latLng(this.latitude, this.longitude)
      const moved = !this.marker || !this.marker.getLatLng().equals(location)
      const zoom = limitedZoom(this.zoom, this.map.getZoom())

      this.mark(location)

      if (moved || this.map.getZoom() !== zoom) {
        this.syncing = true
        this.map.setView(location, zoom, { animate: false })
        this.syncing = false
      }
    },

    updateZoom() {
      if (this.readonly || this.syncing || !this.pointValid) return

      const zoom = this.map.getZoom()
      if (zoom !== this.zoom) {
        this.$emit('update:modelValue', { ...this.current, zoom })
      }
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
    },

    modelValue: {
      deep: true,
      handler() {
        this.sync()
      }
    }
  }
}
</script>

<template>
  <div class="map-field" :class="{ invalid: hasError }">
    <div
      ref="map"
      class="map"
      :aria-label="$gettext(readonly ? 'Map location' : 'Click the map to set the location')"
      :aria-invalid="hasError"
    ></div>
    <v-btn
      v-if="!readonly && hasPoint"
      class="clear-location"
      size="small"
      variant="elevated"
      @click="clear"
    >
      {{ $gettext('Clear location') }}
    </v-btn>
    <div v-if="!readonly" class="hint">
      {{ $gettext('Click the map to set the location') }}
    </div>
  </div>
</template>

<style scoped>
.map-field {
  position: relative;
}

.map {
  z-index: 0;
  width: 100%;
  min-height: 320px;
  border: 1px solid rgb(var(--v-theme-surface-variant));
  border-radius: 4px;
}

.invalid .map {
  border-color: rgb(var(--v-theme-error));
}

:deep(.location-marker) {
  border: 0;
  background: transparent;
  filter: drop-shadow(0 2px 2px rgb(0 0 0 / 45%));
}

:deep(.location-marker svg) {
  display: block;
  width: 36px;
  height: 44px;
  fill: rgb(var(--v-theme-error));
  stroke: #ffffff;
  stroke-linejoin: round;
  stroke-width: 1.5px;
}

.clear-location {
  position: absolute;
  z-index: 500;
  top: 10px;
  right: 10px;
}

.hint {
  margin-top: 6px;
  color: rgb(var(--v-theme-on-surface-variant));
  font-size: 0.75rem;
}
</style>
