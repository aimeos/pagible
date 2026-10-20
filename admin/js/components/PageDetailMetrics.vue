/** @license MIT, https://opensource.org/license/mit */

<script>
import gql from 'graphql-tag'
import { markRaw } from 'vue'
import { pageUrl } from '../urls'
import { mdiChevronUp, mdiChevronDown } from '@mdi/js'
import LoadingSpinner from './LoadingSpinner.vue'
import MetricsCharts from './MetricsCharts.vue'
import { QUERY_PAGE_SIZE, apiError, compact, insightValue, paginate, ranked, rating, signed, sortedByValue, timeline, toMinutes, toPercent, weeklySum } from '../metrics'

const DAYS_OPTIONS = [30, 60]

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
    LoadingSpinner,
    MetricsCharts
  },

  props: {
    item: { type: Object, required: true }
  },

  data: () => ({
    request: 0,
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
    querypage: 1
  }),

  setup() {
    return {
      mdiChevronUp, mdiChevronDown, DAYS_OPTIONS, REFERRER_HEADERS, COUNTRY_HEADERS,
      QUERY_PAGE_SIZE, apiError, compact, insightValue, paginate, rating, signed, sortedByValue, weeklySum
    }
  },

  created() {
    this.dateFormatter = new Intl.DateTimeFormat(this.$vuetify.locale.current, {
      day: 'numeric',
      month: 'numeric'
    })
  },

  mounted() {
    this.metrics()
  },

  beforeUnmount() {
    // discards the responses of pending requests
    this.request++
  },

  computed: {
    charts() {
      const p = this.palette

      return [{
        show: this.views.length || this.visits.length || this.conversions.length,
        title: this.$gettext('Views & Visits'),
        data: this.chart(this.views,
          [p.primary, this.$gettext('Views'), this.views],
          [p.warning, this.$gettext('Visits'), this.visits],
          [p.success, this.$gettext('Conversions'), this.conversions]
        )
      }, {
        show: this.durations.length,
        title: this.$gettext('Visit Durations (minutes)'),
        data: this.chart(this.durations, [p.primary, this.$gettext('Duration'), this.durations])
      }, {
        show: this.impressions.length || this.clicks.length,
        title: this.$gettext('Google Search: Impressions & Clicks'),
        data: this.chart(this.impressions,
          [p.primary, this.$gettext('Impressions'), this.impressions],
          [p.warning, this.$gettext('Clicks'), this.clicks]
        )
      }, {
        show: this.ctrs.length,
        title: this.$gettext('Google Search: Click-through rate'),
        data: this.chart(this.ctrs, [p.success, this.$gettext('Percentage'), this.ctrs])
      }]
    },

    colors() {
      return this.$vuetify.theme.current.colors
    },

    palette() {
      return {
        primary: this.colors?.primary,
        warning: this.colors?.warning,
        success: this.colors?.success
      }
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
        { label: this.$gettext('Google click-through rate'), data: this.ctrs, average: true, suffix: '%' }
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
    // chart data with the keys of the rows as labels and one dataset per [color, label, items] series
    chart(rows, ...series) {
      return markRaw({
        labels: rows.map((d) => d.key),
        ...(series.length > 1 ? { grouped: true } : {}),
        datasets: series.map(([color, label, items]) => ({
          borderWidth: 2, borderColor: color, backgroundColor: color, label, data: items.map((d) => d.value), pointRadius: 0, tension: 0.2
        }))
      })
    },

    // removes the fetched metrics
    clear() {
      for (const key of ['conversions', 'countries', 'durations', 'referrers', 'impressions', 'queries', 'ctrs', 'clicks', 'visits', 'views']) {
        this[key] = []
      }

      this.pagespeed = null
      this.querypage = 1
    },

    async metrics() {
      const request = ++this.request

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

        // skip responses of outdated requests if the days have been changed meanwhile
        if (request !== this.request) return

        const stats = data?.metrics || {}

        this.views = Object.freeze(timeline(stats.views, this.dateFormatter))
        this.visits = Object.freeze(timeline(stats.visits, this.dateFormatter))
        this.conversions = Object.freeze(timeline(stats.conversions, this.dateFormatter))
        this.durations = Object.freeze(timeline(stats.durations, this.dateFormatter, toMinutes))

        this.impressions = Object.freeze(timeline(stats.impressions, this.dateFormatter))
        this.clicks = Object.freeze(timeline(stats.clicks, this.dateFormatter))
        this.ctrs = Object.freeze(timeline(stats.ctrs, this.dateFormatter, toPercent))

        this.countries = Object.freeze(ranked(stats.countries, this.$vuetify.locale.current).map(item => Object.freeze(item)))

        this.referrers = Object.freeze(
          ranked(stats.referrers, this.$vuetify.locale.current)
            .map(item => Object.freeze({
              ...item,
              rows: Object.freeze((item.rows || []).map(r => Object.freeze(r)))
            }))
        )

        // the previous query page may not exist for the new time range
        this.querypage = 1
        this.queries = Object.freeze([...(stats.queries || [])]
          .sort((a, b) => b.impressions - a.impressions)
          .map(q => Object.freeze(q)))
        this.pagespeed = Object.freeze(stats?.pagespeed?.reduce((acc, { key, value }) => {
          acc[key] = value
          return acc
        }, {}))

        this.errors = stats.errors || []
      } catch (e) {
        if (request === this.request) {
          // don't show the charts of the previous time range next to the error
          this.clear()
          this.errors.push(this.$gettext('Error fetching metrics'))
          this.$log('PageDetailMetrics::metrics(): Error fetching metrics', e)
        }
      } finally {
        if (request === this.request) {
          this.loading = false
        }
      }
    },

    url(node) {
      return pageUrl(node, true)
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
        {{ apiError(err) }}
      </v-alert>

      <div v-if="loading" class="loading-overlay d-flex align-center justify-center">
        <LoadingSpinner width="32" height="32" />
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
                      <span class="number">{{ insightValue(col, $vuetify.locale.current) }}</span>
                      <span
                        v-if="weeklySum(col.data, 2)"
                        class="percent"
                        :class="weeklySum(col.data) >= weeklySum(col.data, 2) ? 'good' : 'bad'"
                      >
                        {{ signed((weeklySum(col.data) * 100) / (weeklySum(col.data, 2) || 1) - 100) }}%
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
      <MetricsCharts :charts="charts.slice(0, 2)" />

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
                        :items="sortedByValue(item.rows)"
                        density="compact"
                        hide-default-header
                        hover
                      >
                        <template v-slot:item="{ item }">
                          <tr>
                            <td class="v-data-table-column--align-start">{{ item.key }}</td>
                            <td class="v-data-table-column--align-end">{{ compact(item.value, $vuetify.locale.current) }}</td>
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
                    :class="rating(pagespeed?.[m.key], m.good, m.bad)"
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
                    <v-col cols="3">{{ $gettext('Impressions') }}</v-col>
                    <v-col cols="3">{{ $gettext('Clicks') }}</v-col>
                    <v-col cols="3">{{ $gettext('Percent') }}</v-col>
                    <v-col cols="3">{{ $pgettext('search ranking', 'Position') }}</v-col>
                  </v-row>
                </v-col>
              </v-row>
              <v-row v-for="(q, i) in paginate(queries, querypage)" :key="i" class="line">
                <v-col cols="12" sm="6" class="key">{{ q.key }}</v-col>
                <v-col cols="12" sm="6">
                  <v-row>
                    <v-col cols="3">{{ compact(q.impressions, $vuetify.locale.current) }}</v-col>
                    <v-col cols="3">{{ compact(q.clicks, $vuetify.locale.current) }}</v-col>
                    <v-col cols="3">{{ Number(q.ctr * 100).toFixed(1) }}</v-col>
                    <v-col cols="3">{{ Number(q.position).toFixed(1) }}</v-col>
                  </v-row>
                </v-col>
              </v-row>
            </v-card-text>
            <v-card-actions v-if="queries.length > QUERY_PAGE_SIZE" class="justify-center">
              <v-pagination v-model="querypage" :length="Math.ceil(queries.length / QUERY_PAGE_SIZE)" />
            </v-card-actions>
          </v-card>
        </v-col>
      </v-row>

      <!-- GSC Charts -->
      <MetricsCharts :charts="charts.slice(2)" />
    </v-sheet>
  </v-container>
</template>

<style scoped>
.v-sheet.scroll {
  height: var(--cms-detail-height);
}

.loading-overlay {
  inset: 0;
  position: absolute;
  background: rgba(var(--v-theme-surface), 0.4);
  backdrop-filter: blur(2px);
  z-index: 10;
}

.emphasis-bg {
  background-color: rgb(var(--v-theme-emphasis, var(--v-theme-surface-light)));
  color: rgb(var(--v-theme-on-emphasis, var(--v-theme-on-surface-light)));
}

.emphasis-bg .text-medium-emphasis {
  color: rgba(var(--v-theme-on-emphasis, var(--v-theme-on-surface-light)), var(--v-medium-emphasis-opacity));
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
  color: rgb(var(--v-theme-success));
}

.panel .bad {
  color: rgb(var(--v-theme-error));
}

.panel .warn {
  color: rgb(var(--v-theme-warning));
}

/* Blend towards the text color so the trend colors keep AA contrast on the tinted panels */
.emphasis-bg .good {
  color: color-mix(in srgb, rgb(var(--v-theme-success)) 80%, rgb(var(--v-theme-on-emphasis, var(--v-theme-on-surface))));
}

.emphasis-bg .bad {
  color: color-mix(in srgb, rgb(var(--v-theme-error)) 80%, rgb(var(--v-theme-on-emphasis, var(--v-theme-on-surface))));
}

.emphasis-bg .warn {
  color: color-mix(in srgb, rgb(var(--v-theme-warning)) 80%, rgb(var(--v-theme-on-emphasis, var(--v-theme-on-surface))));
}

.panel .percent {
  font-size: 80%;
  margin-inline-start: 12px;
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
