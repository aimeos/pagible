<script>
  import gql from "graphql-tag";
  import { Line } from 'vue-chartjs'
  import { useAppStore } from '../stores'
  import { Chart as ChartJS, Title, Tooltip, Legend, LineElement, PointElement, CategoryScale, LinearScale } from 'chart.js'

  ChartJS.register(Title, Tooltip, Legend, LineElement, PointElement, CategoryScale, LinearScale);

  export default {
    components: {
      Line
    },

    props: {
      item: { type: Object, required: true },
    },

    data: () => ({
      days: 30,
      errors: [],
      loading: false,
      pagespeed: null,
      countries: [],
      durations: [],
      referrers: [],
      referrertypes: [],
      impressions: [],
      queries: [],
      ctrs: [],
      clicks: [],
      visits: [],
      views: [],
      colors: {},
      page: {
        query: 1,
        country: 1,
        referrer: 1,
        referrerType: 1,
      },
    }),

    setup() {
      const app = useAppStore()
      return { app }
    },

    created() {
      const theme = this.$vuetify.theme
      this.colors = theme.themes[theme.name]?.colors
    },

    mounted() {
      this.metrics();
    },

    methods: {
      color(value, good, bad) {
        if(value === undefined || value === null) {
          return ''
        } else if (value < good) {
          return 'good'
        } else if (value >= bad) {
          return 'bad'
        }
        return 'warn'
      },


      slice(items, page) {
        const start = (page - 1) * 10
        return items.slice(start, start + 10)
      },


      async metrics() {
        this.errors = [];
        this.loading = true;

        try {
          const { data } = await this.$apollo.mutate({
            mutation: gql`mutation ($url: String!, $days: Int) {
                metrics(url: $url, days: $days) {
                  views { key value }
                  visits { key value }
                  durations { key value }
                  countries { key value }
                  referrers { key value }
                  referrertypes { key value }
                  pagespeed { key value }
                  impressions { key value }
                  clicks { key value }
                  ctrs { key value }
                  queries { key impressions clicks ctr position }
                  errors
                }
              }
            `,
            variables: {
              url: 'https://aimeos.org/', // this.url(this.item),
              days: this.days
            },
          });

          const stats = data?.metrics || {};
          const sortByValue = (a,b) => b.value - a.value;
          const sortByDate = (a,b) => a.key > b.key ? 1 : (a.key < b.key ? -1 : 0);
          const formatDate = (item) => {
            item.key = (new Date(item.key)).toLocaleDateString(
              this.$vuetify.locale.current,
              { day: "numeric", month: "numeric" }
            );
            return item;
          }

          this.views = (stats.views || []).sort(sortByDate).map(formatDate);
          this.visits = (stats.visits || []).sort(sortByDate).map(formatDate);
          this.durations = (stats.durations || []).sort(sortByDate).map(formatDate);

          this.impressions = (stats.impressions || []).sort(sortByDate).map(formatDate);
          this.clicks = (stats.clicks || []).sort(sortByDate).map(formatDate);
          this.ctrs = (stats.ctrs || []).sort(sortByDate).map(formatDate);

          this.queries = (stats.queries || []).sort((a,b) => b.impressions - a.impressions);

          this.countries = (stats.countries || []).sort(sortByValue);
          this.referrers = (stats.referrers || []).sort(sortByValue);
          this.referrertypes = (stats.referrertypes || []).sort(sortByValue);

          this.pagespeed = (stats?.pagespeed || []).reduce((acc, { key, value }) => {
            acc[key] = value;
            return acc;
          }, {});

          this.errors = stats.errors || [];
        } catch (e) {
          this.errors.push(e.message || String(e));
        } finally {
          this.loading = false;
        }
      },


      url(node) {
        return this.app.urlpage
          .replace(/_domain_/, node.domain || '')
          .replace(/_path_/, node.path || '/')
          .replace(/\/{2,}$/, '/')
      },


      value(v) {
        switch (true) {
          case v >= 1e9: return (v / 1e9).toFixed(2).toLocaleString() + 'B';
          case v >= 1e6: return (v / 1e6).toFixed(2).toLocaleString() + 'M';
          case v >= 1e3: return (v / 1e3).toFixed(2).toLocaleString() + 'K';
        }
        return v;
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
            :items="[30, 60, 90]"
            :label="$gettext('Days')"
            variant="underlined"
            hide-details
            @update:modelValue="metrics"
          />
        </v-col>
      </v-row>

      <v-alert v-if="errors.length"
        :title="$gettext('Errors')"
        variant="tonal"
        border="start"
        class="panel"
        type="error">
        {{ errors.join("\n") }}
      </v-alert>

      <div v-if="loading" class="loading-overlay d-flex align-center justify-center">
        <v-progress-circular indeterminate size="32" />
      </div>

      <!-- PageSpeed -->
      <v-row v-if="pagespeed">
        <v-col cols="12">
          <v-card class="panel">
            <v-card-title class="text-subtitle-1">{{ $gettext('Page Speed') }}</v-card-title>
            <v-card-text>
              <v-row>
                <v-col cols="12" lg="2" md="4" sm="6">
                    <div class="text-caption text-medium-emphasis">{{ $gettext('Round trip time') }}</div>
                    <div class="d-flex align-center justify-space-between text-h6"
                      :class="color(pagespeed?.['round_trip_time'], 200, 500)">
                      <span v-if="pagespeed?.['round_trip_time']">
                        {{ pagespeed?.['round_trip_time'] }}ms
                      </span>
                      <span v-else>—</span>
                    </div>
                </v-col>
                <v-col cols="12" lg="2" md="4" sm="6">
                    <div class="text-caption text-medium-emphasis">{{ $gettext('Time to first byte') }}</div>
                    <div class="d-flex align-center justify-space-between text-h6"
                      :class="color(pagespeed?.['time_to_first_byte'], 800, 1800)">
                      <span v-if="pagespeed?.['time_to_first_byte']">
                        {{ pagespeed?.['time_to_first_byte'] }}ms
                      </span>
                      <span v-else>—</span>
                    </div>
                </v-col>
                <v-col cols="12" lg="2" md="4" sm="6">
                    <div class="text-caption text-medium-emphasis">{{ $gettext('First contentful paint') }}</div>
                    <div class="d-flex align-center justify-space-between text-h6"
                      :class="color(pagespeed?.['first_contentful_paint'], 1800, 3000)">
                      <span v-if="pagespeed?.['first_contentful_paint']">
                        {{ pagespeed?.['first_contentful_paint'] }}ms
                      </span>
                      <span v-else>—</span>
                    </div>
                </v-col>
                <v-col cols="12" lg="2" md="4" sm="6">
                    <div class="text-caption text-medium-emphasis">{{ $gettext('Largest contentful paint') }}</div>
                    <div class="d-flex align-center justify-space-between text-h6"
                      :class="color(pagespeed?.['largest_contentful_paint'], 2500, 4000)">
                      <span v-if="pagespeed?.['largest_contentful_paint']">
                        {{ pagespeed?.['largest_contentful_paint'] }}ms
                      </span>
                      <span v-else>—</span>
                    </div>
                </v-col>
                <v-col cols="12" lg="2" md="4" sm="6">
                    <div class="text-caption text-medium-emphasis">{{ $gettext('Interaction to next paint') }}</div>
                    <div class="d-flex align-center justify-space-between text-h6"
                      :class="color(pagespeed?.['interaction_to_next_paint'], 200, 500)">
                      <span v-if="pagespeed?.['interaction_to_next_paint']">
                        {{ pagespeed?.['interaction_to_next_paint'] }}ms
                      </span>
                      <span v-else>—</span>
                    </div>
                </v-col>
                <v-col cols="12" lg="2" md="4" sm="6">
                    <div class="text-caption text-medium-emphasis">{{ $gettext('Cumulative layout shift') }}</div>
                    <div class="d-flex align-center justify-space-between text-h6"
                      :class="color(pagespeed?.['cumulative_layout_shift'], 0.1, 0.25)">
                      <span v-if="pagespeed?.['cumulative_layout_shift']">
                        {{ pagespeed?.['cumulative_layout_shift'] }}
                      </span>
                      <span v-else>—</span>
                    </div>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Analytics Charts -->
      <v-row v-if="views.length || visits.length || durations.length">
        <v-col cols="12" md="6">
          <v-card class="panel chart">
            <v-card-title class="text-subtitle-1">{{ $gettext('Number of Views & Visits') }}</v-card-title>
            <v-card-text>
              <Line
                :options="{
                  locale: $vuetify.locale.current,
                  maintainAspectRatio: false,
                  responsive: true,
                  interaction: {
                      mode: 'index',
                      intersect: false
                  },
                  plugins: {
                    legend: {
                      labels: {
                        color: colors?.['surface-variant']
                      },
                      rtl: $vuetify.locale.isRtl
                    },
                    tooltip: {
                      intersect: false,
                      rtl: $vuetify.locale.isRtl,
                    }
                  },
                  scales: {
                    x: {
                      reverse: $vuetify.locale.isRtl,
                      ticks: { color: colors?.['surface-variant'] },
                      grid: { color: colors?.['on-surface-variant'] },
                    },
                    y: {
                      beginAtZero: true,
                      position: $vuetify.locale.isRtl ? 'right' : 'left',
                      ticks: { color: colors?.['surface-variant'] },
                      grid: { color: colors?.['on-surface-variant'] },
                    },
                  }
                }"
                :data="{
                  labels: views.map(d => d.key),
                  grouped: true,
                  datasets: [{
                    borderWidth: 2,
                    borderColor: '#0000C0',
                    backgroundColor: '#0000C0',
                    label: $gettext('Views'),
                    data: views.map(d => d.value),
                    pointRadius: 0,
                    tension: 0.2
                  }, {
                    borderWidth: 2,
                    borderColor: '#C00000',
                    backgroundColor: '#C00000',
                    label: $gettext('Visits'),
                    data: visits.map(d => d.value),
                    pointRadius: 0,
                    tension: 0.2
                  }]
                }"
              />
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" md="6">
          <v-card class="panel chart">
            <v-card-title class="text-subtitle-1">{{ $gettext('Visit Durations in minutes') }}</v-card-title>
            <v-card-text>
              <Line
                :options="{
                  locale: $vuetify.locale.current,
                  maintainAspectRatio: false,
                  responsive: true,
                  interaction: {
                      mode: 'index',
                      intersect: false
                  },
                  plugins: {
                    legend: {
                      labels: {
                        color: colors?.['surface-variant']
                      },
                      rtl: $vuetify.locale.isRtl
                    },
                    tooltip: {
                      intersect: false,
                      rtl: $vuetify.locale.isRtl,
                    }
                  },
                  scales: {
                    x: {
                      reverse: $vuetify.locale.isRtl,
                      ticks: { color: colors?.['surface-variant'] },
                      grid: { color: colors?.['on-surface-variant'] },
                    },
                    y: {
                      beginAtZero: true,
                      position: $vuetify.locale.isRtl ? 'right' : 'left',
                      ticks: { color: colors?.['surface-variant'] },
                      grid: { color: colors?.['on-surface-variant'] },
                    }
                  }
                }"
                :data="{
                  labels: durations.map(d => d.key),
                  datasets: [{
                    borderWidth: 2,
                    borderColor: '#008000',
                    backgroundColor: '#008000',
                    label: $gettext('Duration'),
                    data: durations.map(d => ((d.value || 0) / 60 ).toFixed(1)),
                    pointRadius: 0,
                    tension: 0.2
                  }]
                }"
              />
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Top lists -->
      <v-row v-if="countries.length || referrers.length">
        <v-col cols="12" md="6">
          <v-card class="panel top">
            <v-card-title class="text-subtitle-1">{{ $gettext('Top Countries') }}</v-card-title>
            <v-card-text>
              <v-list density="compact">
                <v-list-item v-for="(c, i) in slice(countries, page.country)" :key="i">
                  <template #prepend>
                    <v-avatar size="25" class="mr-2">{{ (page.country - 1) * 10 + i + 1 }}</v-avatar>
                  </template>
                  <v-list-item-title class="key">{{ c.key }}</v-list-item-title>
                  <template #append>
                    <span class="value">{{ value(c.value) }}</span>
                  </template>
                </v-list-item>
              </v-list>
            </v-card-text>
            <v-card-actions class="justify-center">
              <v-pagination
                v-model="page.country"
                :length="countries.length"
              />
            </v-card-actions>
          </v-card>
        </v-col>

        <v-col cols="12" md="6">
          <v-card class="panel top">
            <v-card-title class="text-subtitle-1">{{ $gettext('Top Referrers') }}</v-card-title>
            <v-card-text>
              <v-list density="compact">
                <v-list-item v-for="(r, i) in slice(referrers, page.referrer)" :key="i">
                  <template #prepend>
                    <v-avatar size="25" class="mr-2">{{ (page.referrer - 1) * 10 + i + 1 }}</v-avatar>
                  </template>
                  <v-list-item-title>
                    <a class="key" :href="r.key" target="_blank" dir="ltr">{{ r.key }}</a>
                  </v-list-item-title>
                  <template #append>
                    <span class="value">{{ value(r.value) }}</span>
                  </template>
                </v-list-item>
              </v-list>
            </v-card-text>
            <v-card-actions class="justify-center">
              <v-pagination
                v-model="page.referrer"
                :length="referrers.length"
              />
            </v-card-actions>
          </v-card>
        </v-col>
      </v-row>

      <!-- Top lists -->
      <v-row v-if="referrertypes.length">
        <v-col cols="12" md="6">
          <v-card class="panel top">
            <v-card-title>{{ $gettext('Referrer Types') }}</v-card-title>
            <v-card-text>
              <v-list density="compact">
                <v-list-item v-for="(c, i) in slice(referrertypes, page.referrerType)" :key="i">
                  <template #prepend>
                    <v-avatar size="25" class="mr-2">{{ (page.referrerType - 1) * 10 + i + 1 }}</v-avatar>
                  </template>
                  <v-list-item-title class="key">{{ c.key }}</v-list-item-title>
                  <template #append>
                    <span class="value">{{ value(c.value) }}</span>
                  </template>
                </v-list-item>
              </v-list>
            </v-card-text>
            <v-card-actions v-if="referrertypes.length > 10" class="justify-center">
              <v-pagination
                v-model="page.referrerType"
                :length="referrertypes.length"
              />
            </v-card-actions>
          </v-card>
        </v-col>

        <v-col cols="12" md="6">
        </v-col>
      </v-row>

      <!-- GSC Charts -->
      <v-row v-if="countries.length || referrers.length">
        <v-col cols="12" md="6">
          <v-card class="panel chart">
            <v-card-title class="text-subtitle-1">{{ $gettext('Google Search: Number of Impressions & Clicks') }}</v-card-title>
            <v-card-text>
              <Line
                :options="{
                  locale: $vuetify.locale.current,
                  maintainAspectRatio: false,
                  responsive: true,
                  interaction: {
                      mode: 'index',
                      intersect: false
                  },
                  plugins: {
                    legend: {
                      labels: {
                        color: colors?.['surface-variant']
                      },
                      rtl: $vuetify.locale.isRtl
                    },
                    tooltip: {
                      intersect: false,
                      rtl: $vuetify.locale.isRtl,
                    }
                  },
                  scales: {
                    x: {
                      reverse: $vuetify.locale.isRtl,
                      ticks: { color: colors?.['surface-variant'] },
                      grid: { color: colors?.['on-surface-variant'] },
                    },
                    y: {
                      beginAtZero: true,
                      position: $vuetify.locale.isRtl ? 'right' : 'left',
                      ticks: { color: colors?.['surface-variant'] },
                      grid: { color: colors?.['on-surface-variant'] },
                    },
                  }
                }"
                :data="{
                  labels: impressions.map(d => d.key),
                  grouped: true,
                  datasets: [{
                    borderWidth: 2,
                    borderColor: '#0000C0',
                    backgroundColor: '#0000C0',
                    label: $gettext('Impressions'),
                    data: impressions.map(d => d.value),
                    pointRadius: 0,
                    tension: 0.2
                  }, {
                    borderWidth: 2,
                    borderColor: '#C00000',
                    backgroundColor: '#C00000',
                    label: $gettext('Clicks'),
                    data: clicks.map(d => d.value),
                    pointRadius: 0,
                    tension: 0.2
                  }]
                }"
              />
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" md="6">
          <v-card class="panel chart">
            <v-card-title class="text-subtitle-1">{{ $gettext('Google Search: Percentage clicked') }}</v-card-title>
            <v-card-text>
              <Line
                :options="{
                  locale: $vuetify.locale.current,
                  maintainAspectRatio: false,
                  responsive: true,
                  interaction: {
                      mode: 'index',
                      intersect: false
                  },
                  plugins: {
                    legend: {
                      labels: {
                        color: colors?.['surface-variant']
                      },
                      rtl: $vuetify.locale.isRtl
                    },
                    tooltip: {
                      intersect: false,
                      rtl: $vuetify.locale.isRtl,
                    }
                  },
                  scales: {
                    x: {
                      reverse: $vuetify.locale.isRtl,
                      ticks: { color: colors?.['surface-variant'] },
                      grid: { color: colors?.['on-surface-variant'] },
                    },
                    y: {
                      beginAtZero: true,
                      position: $vuetify.locale.isRtl ? 'right' : 'left',
                      ticks: { color: colors?.['surface-variant'] },
                      grid: { color: colors?.['on-surface-variant'] },
                    }
                  }
                }"
                :data="{
                  labels: ctrs.map(d => d.key),
                  datasets: [{
                    borderWidth: 2,
                    borderColor: '#008000',
                    backgroundColor: '#008000',
                    label: $gettext('Percentage'),
                    data: ctrs.map(d => d.value * 100),
                    pointRadius: 0,
                    tension: 0.2
                  }]
                }"
              />
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- GSC queries -->
      <v-row v-if="queries.length">
        <v-col cols="12">
          <v-card class="panel top">
            <v-card-title class="text-subtitle-1">{{ $gettext('Top Queries') }}</v-card-title>
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
              <v-row v-for="(q, i) in slice(queries, page.query)" :key="i" class="line">
                <v-col cols="12" sm="6" class="key">{{ q.key }}</v-col>
                <v-col cols="12" sm="6">
                  <v-row>
                    <v-col cols="3">{{ q.impressions }}</v-col>
                    <v-col cols="3">{{ q.clicks }}</v-col>
                    <v-col cols="3">{{ Number(q.ctr * 100).toFixed(1) }}</v-col>
                    <v-col cols="3">{{ Number(q.position).toFixed(1) }}</v-col>
                  </v-row>
                </v-col>
              </v-row>
            </v-card-text>
            <v-card-actions class="justify-center">
              <v-pagination
                v-model="page.query"
                :length="queries.length"
              />
            </v-card-actions>
          </v-card>
        </v-col>
      </v-row>

    </v-sheet>
  </v-container>
</template>

<style scoped>
  .loading-overlay {
    inset: 0;
    position: absolute;
    background: color-mix(in oklab, var(--v-theme-surface), transparent 60%);
    backdrop-filter: blur(2px);
  }

  .title,
  .select-days {
    display: flex;
    justify-content: flex-end;
    align-self: center;
  }

  .title {
    font-weight: 500;
    font-size: 1.25rem;
    justify-content: flex-start;
  }

  .select-days .v-select {
    max-width: 120px;
  }

  .panel {
    margin-top: 16px !important;
  }

  .panel.top .v-card-text {
    padding-bottom: 0;
  }

  .panel .good {
    color: #008000;
  }

  .v-theme--dark .panel .good {
    color: #00A000;
  }

  .panel .bad {
    color: #C00000;
  }

  .v-theme--dark .panel .bad {
    color: #FF4000;
  }

  .panel .warn {
    color: #B46000;
  }

  .v-theme--dark .panel .warn {
    color: #E0A000;
  }

  .panel.chart .v-card-text {
    aspect-ratio: 3 / 2;
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

  .panel .table .line > div[class*="v-col"] {
    padding-bottom: 2px !important;
    padding-top: 2px !important;
  }

  .panel .table .key {
    font-weight: bold;
  }
</style>
