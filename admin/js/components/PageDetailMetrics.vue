/** @license LGPL, https://opensource.org/license/lgpl-3-0 */

<script>
import gql from 'graphql-tag'
import { defineAsyncComponent, markRaw } from 'vue'
import { useAppStore } from '../stores'
import { mdiChevronUp, mdiChevronDown } from '@mdi/js'

const LineChart = defineAsyncComponent(() =>
  Promise.all([
    import('vue-chartjs').then(m => m.Line),
    import('chart.js').then(({ Chart, Title, Tooltip, Legend, LineElement, PointElement, CategoryScale, LinearScale }) => {
      Chart.register(Title, Tooltip, Legend, LineElement, PointElement, CategoryScale, LinearScale)
    })
  ]).then(([Line]) => Line)
)

const DAYS_OPTIONS = [30, 60]

const BASE_CHART_OPTIONS = {
  maintainAspectRatio: false,
  responsive: true,
  interaction: { mode: 'index', intersect: false }
}

const REFERRER_HEADERS = [
  { key: 'data-table-expand', width: 1 },
  { key: 'key' },
  { key: 'value', align: 'end' }
]

const COUNTRY_HEADERS = [{ key: 'key' }, { key: 'value', align: 'end' }]

const FETCH_METRICS = gql`
  mutation ($url: String!, $days: Int) {
    metrics(url: $url, days: $days) {
      views { key value }
      visits { key value }
      conversions { key value }
      durations { key value }
      countries { key value }
      referrers { key value rows { key value } }
      pagespeed { key value }
      impressions { key value }
      clicks { key value }
      ctrs { key value }
      queries { key impressions clicks ctr position }
      errors
    }
  }
`

export default {
  components: {
    LineChart
  },

  props: {
    item: { type: Object, required: true }
  },

  data: () => ({
    destroyed: false,
    days: 30,
    errors: [],
    loading: false,
    pagespeed: null,
    conversions: [],
    countries: [],
    durations: [],
    referrers: [],
    impressions: [],
    queries: [],
    ctrs: [],
    clicks: [],
    visits: [],
    views: [],
    colors: {},
    querypage: 1
  }),

  setup() {
    const app = useAppStore()
    return { app, mdiChevronUp, mdiChevronDown, DAYS_OPTIONS, REFERRER_HEADERS, COUNTRY_HEADERS }
  },

  created() {
    const theme = this.$vuetify.theme
    this.colors = theme.themes[theme.name]?.colors
    this.dateFormatter = new Intl.DateTimeFormat(this.$vuetify.locale.current, {
      day: 'numeric',
      month: 'numeric'
    })
  },

  mounted() {
    this.metrics()
  },

  beforeUnmount() {
    this.destroyed = true
    this.views = null
    this.visits = null
    this.conversions = null
    this.durations = null
    this.impressions = null
    this.clicks = null
    this.ctrs = null
    this.countries = null
    this.referrers = null
    this.queries = null
    this.pagespeed = null
    this.colors = null
  },

  computed: {
    chartOptions() {
      const isRtl = this.$vuetify.locale.isRtl
      const tickColor = this.colors?.['surface-variant']
      const gridColor = this.colors?.['on-surface-variant']

      return markRaw({
        ...BASE_CHART_OPTIONS,
        locale: this.$vuetify.locale.current,
        plugins: {
          legend: { labels: { color: tickColor }, rtl: isRtl },
          tooltip: { intersect: false, rtl: isRtl }
        },
        scales: {
          x: { reverse: isRtl, ticks: { color: tickColor }, grid: { color: gridColor } },
          y: { beginAtZero: true, position: isRtl ? 'right' : 'left', ticks: { color: tickColor }, grid: { color: gridColor } }
        }
      })
    },

    viewsData() {
      return markRaw({
        labels: this.views.map((d) => d.key),
        grouped: true,
        datasets: [
          this.dataset('#C00000', this.$gettext('Views'), this.views.map((d) => d.value)),
          this.dataset('#0000C0', this.$gettext('Visits'), this.visits.map((d) => d.value)),
          this.dataset('#008000', this.$gettext('Conversions'), this.conversions.map((d) => d.value))
        ]
      })
    },

    durationsData() {
      return markRaw({
        labels: this.durations.map((d) => d.key),
        datasets: [
          this.dataset('#0000C0', this.$gettext('Duration'), this.durations.map((d) => d.value))
        ]
      })
    },

    impressionsData() {
      return markRaw({
        labels: this.impressions.map((d) => d.key),
        grouped: true,
        datasets: [
          this.dataset('#C00000', this.$gettext('Impressions'), this.impressions.map((d) => d.value)),
          this.dataset('#0000C0', this.$gettext('Clicks'), this.clicks.map((d) => d.value))
        ]
      })
    },

    ctrsData() {
      return markRaw({
        labels: this.ctrs.map((d) => d.key),
        datasets: [
          this.dataset('#008000', this.$gettext('Percentage'), this.ctrs.map((d) => d.value))
        ]
      })
    },

    insightColumns() {
      const hasConversions = this.conversions.reduce((acc, item) => acc + Number(item.value), 0)

      return markRaw([
        { label: this.$gettext('Page Views'), data: this.views },
        { label: this.$gettext('Page Visits'), data: this.visits },
        hasConversions
          ? { label: this.$gettext('Conversions'), data: this.conversions, average: true }
          : { label: this.$gettext('Visit Duration (minutes)'), data: this.durations, average: true },
        { label: this.$gettext('Google Impressions'), data: this.impressions },
        { label: this.$gettext('Google Clicks'), data: this.clicks },
        { label: this.$gettext('Google Conversion Rate'), data: this.ctrs, average: true, suffix: '%' }
      ])
    },

    speedMetrics() {
      return markRaw([
        { key: 'round_trip_time', label: this.$gettext('Round trip time'), good: 200, bad: 500, unit: 'ms' },
        { key: 'time_to_first_byte', label: this.$gettext('Time to first byte'), good: 800, bad: 1800, unit: 'ms' },
        { key: 'first_contentful_paint', label: this.$gettext('First contentful paint'), good: 1800, bad: 3000, unit: 'ms' },
        { key: 'largest_contentful_paint', label: this.$gettext('Largest contentful paint'), good: 2500, bad: 4000, unit: 'ms' },
        { key: 'interaction_to_next_paint', label: this.$gettext('Interaction to next paint'), good: 200, bad: 500, unit: 'ms' },
        { key: 'cumulative_layout_shift', label: this.$gettext('Cumulative layout shift'), good: 0.1, bad: 0.25, unit: '' }
      ])
    }
  },

  methods: {
    dataset(color, label, data) {
      return { borderWidth: 2, borderColor: color, backgroundColor: color, label, data, pointRadius: 0, tension: 0.2 }
    },

    insightValue(col) {
      return col.average
        ? Number(this.weekly(col.data) / 7).toFixed(1) + (col.suffix || '')
        : this.value(this.weekly(col.data))
    },


    color(value, good, bad) {
      if (value === undefined || value === null) {
        return ''
      } else if (value < good) {
        return 'good'
      } else if (value >= bad) {
        return 'bad'
      }
      return 'warn'
    },

    formatDate(item) {
      item.key = this.dateFormatter.format(new Date(item.key))
      return item
    },

    formatValue(item) {
      item.value = this.value(item.value)
      return item
    },

    error(msg) {
      try {
        return JSON.parse(msg)?.error?.message || msg
      } catch {
        return msg
      }
    },

    async metrics() {
      this.errors = []
      this.loading = true

      try {
        const { data } = await this.$apollo.mutate({
          mutation: FETCH_METRICS,
          variables: {
            url: this.url(this.item),
            days: this.days
          }
        })

        if (this.destroyed) return

        const stats = data?.metrics || {}

        this.views = Object.freeze(this.sortAndFormat(stats.views))
        this.visits = Object.freeze(this.sortAndFormat(stats.visits))
        this.conversions = Object.freeze(this.sortAndFormat(stats.conversions))
        this.durations = Object.freeze(this.sortAndFormat(stats.durations, this.toMinutes))

        this.impressions = Object.freeze(this.sortAndFormat(stats.impressions))
        this.clicks = Object.freeze(this.sortAndFormat(stats.clicks))
        this.ctrs = Object.freeze(this.sortAndFormat(stats.ctrs, this.toPercent))

        this.countries = Object.freeze(
          this.sortAndTransform(stats.countries, this.sortByValue, this.formatValue)
            .map(item => Object.freeze(item))
        )

        this.referrers = Object.freeze(
          this.sortAndTransform(stats.referrers, this.sortByValue, this.formatValue)
            .map(item => Object.freeze({
              ...item,
              rows: Object.freeze((item.rows || []).map(r => Object.freeze(r)))
            }))
        )

        this.queries = Object.freeze((stats.queries || [])
          .sort((a, b) => b.impressions - a.impressions)
          .map(q => Object.freeze(q)))
        this.pagespeed = Object.freeze(stats?.pagespeed?.reduce((acc, { key, value }) => {
          acc[key] = value
          return acc
        }, {}))

        this.errors = stats.errors || []
      } catch (e) {
        this.errors.push(e.message || String(e))
      } finally {
        this.loading = false
      }
    },

    sortAndFormat(data, transform) {
      const arr = (data || []).sort(this.sortByDate)

      for (let i = 0; i < arr.length; i++) {
        this.formatDate(arr[i])
        if (transform) transform(arr[i])
      }

      return arr
    },

    sortAndTransform(data, sortFn, transformFn) {
      const arr = (data || []).sort(sortFn)

      for (let i = 0; i < arr.length; i++) {
        transformFn(arr[i])
      }

      return arr
    },

    sortedRows(rows) {
      return [...rows].sort((a, b) => b.value - a.value)
    },

    sortByDate(a, b) {
      return a.key > b.key ? 1 : a.key < b.key ? -1 : 0
    },

    sortByValue(a, b) {
      return b.value - a.value
    },

    toMinutes(item) {
      item.value = item.value / 60
      return item
    },

    toPercent(item) {
      item.value = item.value * 100
      return item
    },

    percent(v) {
      return (v > 0 ? '+' : '') + Number(v).toFixed(0)
    },

    slice(items, page) {
      const start = (page - 1) * 10
      return items.slice(start, start + 10)
    },

    url(node) {
      return this.app.urlpage
        .replace(/_domain_/, node.domain || '')
        .replace(/_path_/, node.path || '/')
        .replace(/\/{2,}$/, '/')
    },

    value(v) {
      switch (true) {
        case v >= 1e9:
          return (v / 1e9).toFixed(2).toLocaleString() + 'B'
        case v >= 1e6:
          return (v / 1e6).toFixed(2).toLocaleString() + 'M'
        case v >= 1e3:
          return (v / 1e3).toFixed(2).toLocaleString() + 'K'
      }
      return v
    },

    weekly(data, slice = 1) {
      return data
        .slice(-7 * slice, data.length - 7 * (slice - 1))
        .reduce((acc, item) => acc + Number(item.value), 0)
    }
  }
}
</script>

<template>
  <v-container>
    <v-sheet class="box scroll">
      <v-row>
        <v-col cols="6" class="title">
          {{ $gettext('Page metrics') }}
        </v-col>
        <v-col cols="6" class="select-days">
          <v-select
            v-model="days"
            :items="DAYS_OPTIONS"
            :label="$gettext('Days')"
            variant="underlined"
            hide-details
            @update:modelValue="metrics"
          />
        </v-col>
      </v-row>

      <v-alert
        v-for="(err, idx) in errors"
        :key="idx"
        variant="tonal"
        border="start"
        class="panel"
        type="error"
      >
        {{ error(err) }}
      </v-alert>

      <div v-if="loading" class="loading-overlay d-flex align-center justify-center">
        <v-progress-circular indeterminate size="32" />
      </div>

      <!-- Overview -->
      <v-row>
        <v-col cols="12">
          <v-card class="panel emphasis-bg">
            <v-card-title>{{ $gettext('Weekly Insights') }}</v-card-title>
            <v-card-text>
              <v-row>
                <v-col v-for="(col, idx) in insightColumns" :key="idx" cols="6" md="4" lg="2">
                  <div class="text-caption text-medium-emphasis">{{ col.label }}</div>
                  <div class="d-flex align-center justify-space-between text-h6">
                    <div v-if="col.data.length">
                      <span class="number">{{ insightValue(col) }}</span>
                      <span
                        v-if="weekly(col.data, 2)"
                        class="percent"
                        :class="weekly(col.data) >= weekly(col.data, 2) ? 'good' : 'bad'"
                      >
                        {{ percent((weekly(col.data) * 100) / (weekly(col.data, 2) || 1) - 100) }}%
                      </span>
                    </div>
                    <div v-else>—</div>
                  </div>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Analytics Charts -->
      <v-row>
        <v-col v-if="views.length || visits.length || conversions.length" cols="12" md="6">
          <v-card class="panel chart">
            <v-card-title>{{ $gettext('Views & Visits') }}</v-card-title>
            <v-card-text>
              <LineChart :options="chartOptions" :data="viewsData" />
            </v-card-text>
          </v-card>
        </v-col>

        <v-col v-if="durations.length" cols="12" md="6">
          <v-card class="panel chart">
            <v-card-title>{{ $gettext('Visit Durations (minutes)') }}</v-card-title>
            <v-card-text>
              <LineChart :options="chartOptions" :data="durationsData" />
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Top lists -->
      <v-row>
        <v-col v-if="referrers.length" cols="12" md="6">
          <v-card class="panel">
            <v-card-title>{{ $gettext('Referrers') }}</v-card-title>
            <v-card-text>
              <v-data-table
                :items="referrers"
                :headers="REFERRER_HEADERS"
                item-value="key"
                items-per-page="25"
                hide-default-header
                hide-default-footer
                expand-on-click
                show-expand
              >
                <template #[`item.data-table-expand`]="{ internalItem, isExpanded }">
                  <v-icon v-if="internalItem.raw?.rows?.length">
                    {{ isExpanded(internalItem) ? mdiChevronUp : mdiChevronDown }}
                  </v-icon>
                </template>
                <template v-slot:expanded-row="{ columns, item }">
                  <tr v-if="item.rows.length">
                    <td :colspan="columns.length" class="py-2">
                      <v-data-table
                        :items="sortedRows(item.rows)"
                        density="compact"
                        hide-default-header
                        hover
                      >
                        <template v-slot:item="{ item }">
                          <tr>
                            <td class="v-data-table-column--align-start">{{ item.key }}</td>
                            <td class="v-data-table-column--align-end">{{ value(item.value) }}</td>
                          </tr>
                        </template>
                      </v-data-table>
                    </td>
                  </tr>
                </template>
              </v-data-table>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col v-if="countries.length" cols="12" md="6">
          <v-card class="panel">
            <v-card-title>{{ $gettext('Countries') }}</v-card-title>
            <v-card-text>
              <v-data-table
                :items="countries"
                :headers="COUNTRY_HEADERS"
                hide-default-header
              />
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- PageSpeed -->
      <v-row>
        <v-col v-if="pagespeed" cols="12">
          <v-card class="panel emphasis-bg">
            <v-card-title>{{ $gettext('Page Speed') }}</v-card-title>
            <v-card-text>
              <v-row>
                <v-col v-for="m in speedMetrics" :key="m.key" cols="6" md="4" lg="2">
                  <div class="text-caption text-medium-emphasis">{{ m.label }}</div>
                  <div
                    class="d-flex align-center justify-space-between text-h6"
                    :class="color(pagespeed?.[m.key], m.good, m.bad)"
                  >
                    <span v-if="typeof pagespeed?.[m.key] === 'number'">
                      {{ pagespeed?.[m.key] }}{{ m.unit }}
                    </span>
                    <span v-else>—</span>
                  </div>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- GSC queries -->
      <v-row>
        <v-col v-if="queries.length" cols="12">
          <v-card class="panel">
            <v-card-title>{{ $gettext('Google Search: Queries') }}</v-card-title>
            <v-card-text class="table">
              <v-row class="header">
                <v-col cols="12" sm="6" class="key"></v-col>
                <v-col cols="12" sm="6">
                  <v-row>
                    <v-col cols="3">{{ $gettext('Views') }}</v-col>
                    <v-col cols="3">{{ $gettext('Clicks') }}</v-col>
                    <v-col cols="3">{{ $gettext('Percent') }}</v-col>
                    <v-col cols="3">{{ $gettext('Position') }}</v-col>
                  </v-row>
                </v-col>
              </v-row>
              <v-row v-for="(q, i) in slice(queries, querypage)" :key="i" class="line">
                <v-col cols="12" sm="6" class="key">{{ q.key }}</v-col>
                <v-col cols="12" sm="6">
                  <v-row>
                    <v-col cols="3">{{ value(q.impressions) }}</v-col>
                    <v-col cols="3">{{ value(q.clicks) }}</v-col>
                    <v-col cols="3">{{ Number(q.ctr * 100).toFixed(1) }}</v-col>
                    <v-col cols="3">{{ Number(q.position).toFixed(1) }}</v-col>
                  </v-row>
                </v-col>
              </v-row>
            </v-card-text>
            <v-card-actions v-if="queries.length > 10" class="justify-center">
              <v-pagination v-model="querypage" :length="Math.ceil(queries.length / 10)" />
            </v-card-actions>
          </v-card>
        </v-col>
      </v-row>

      <!-- GSC Charts -->
      <v-row>
        <v-col v-if="impressions.length || clicks.length" cols="12" md="6">
          <v-card class="panel chart">
            <v-card-title>{{ $gettext('Google Search: Impressions & Clicks') }}</v-card-title>
            <v-card-text>
              <LineChart :options="chartOptions" :data="impressionsData" />
            </v-card-text>
          </v-card>
        </v-col>

        <v-col v-if="ctrs.length" cols="12" md="6">
          <v-card class="panel chart">
            <v-card-title>{{ $gettext('Google Search: Conversions') }}</v-card-title>
            <v-card-text>
              <LineChart :options="chartOptions" :data="ctrsData" />
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </v-sheet>
  </v-container>
</template>

<style scoped>
.v-sheet.scroll {
  max-height: calc(100vh - 96px);
}

.loading-overlay {
  inset: 0;
  position: absolute;
  background: color-mix(in oklab, var(--v-theme-surface), transparent 60%);
  backdrop-filter: blur(2px);
  z-index: 10;
}

.emphasis-bg {
  background-color: rgb(var(--v-theme-background));
}

.title,
.select-days {
  display: flex;
  justify-content: flex-end;
  align-self: center;
}

.title {
  font-weight: 500;
  font-size: 125%;
  justify-content: flex-start;
}

.select-days .v-select {
  max-width: 120px;
}

.v-card-title {
  font-size: 112.5%;
}

.panel {
  margin-top: 16px !important;
}

.panel .good {
  color: #008000;
}

.v-theme--dark .panel .good {
  color: #00a000;
}

.panel .bad {
  color: #c00000;
}

.v-theme--dark .panel .bad {
  color: #ff4000;
}

.panel .warn {
  color: #b46000;
}

.v-theme--dark .panel .warn {
  color: #e0a000;
}

.panel.chart .v-card-text {
  aspect-ratio: 3 / 2;
}

.panel .percent {
  font-size: 80%;
  margin-inline-start: 12px;
}

.panel .value {
  margin-inline-start: 8px;
  text-align: end;
  min-width: 3.5rem;
}

.panel .table .header {
  font-weight: bold;
}

.panel .table .line {
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  padding-top: 8px;
  padding-bottom: 8px;
}

.panel .table .line > div[class*='v-col'] {
  padding-bottom: 2px !important;
  padding-top: 2px !important;
}

.panel .table .key {
  font-weight: bold;
}
</style>
