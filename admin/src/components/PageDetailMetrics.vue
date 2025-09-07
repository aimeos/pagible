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
      referrers: [],
      durations: [],
      visits: [],
      views: [],
      colors: {},
      page: {
        country: 1,
        referrer: 1,
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
                  pagespeed { key value }
                  errors
                }
              }
            `,
            variables: {
              url: this.url(this.item),
              days: this.days
            },
          });

          const stats = data?.metrics || {};
          const sortByValue = (a,b) => b.value - a.value;
          const sortByDate = (a,b) => a.key > b.key ? 1 : (a.key < b.key ? -1 : 0);

          this.views = (stats.views || []).sort(sortByDate);
          this.visits = (stats.visits || []).sort(sortByDate);
          this.durations = (stats.durations || []).sort(sortByDate);

          this.countries = (stats.countries || []).sort(sortByValue);
          this.referrers = (stats.referrers || []).sort(sortByValue);

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
          {{ $gettext('Page analytics') }}
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

      <!-- Performance -->
      <v-row>
        <v-col cols="12">
          <v-card class="panel">
            <v-card-title class="text-subtitle-1">{{ $gettext('Page Speed') }}</v-card-title>
            <v-card-text>
              <v-row v-if="pagespeed">
                <v-col cols="12" lg="2" md="4" sm="6">
                    <div class="text-caption text-medium-emphasis">{{ $gettext('Round trip time') }}</div>
                    <div class="d-flex align-center justify-space-between text-h6"
                      :class="color(pagespeed?.['round_trip_time'], 200, 500)">
                      <span v-if="pagespeed?.['round_trip_time']">
                        {{ pagespeed?.['round_trip_time'] }} ms
                      </span>
                      <span v-else>—</span>
                    </div>
                </v-col>
                <v-col cols="12" lg="2" md="4" sm="6">
                    <div class="text-caption text-medium-emphasis">{{ $gettext('Time to first byte') }}</div>
                    <div class="d-flex align-center justify-space-between text-h6"
                      :class="color(pagespeed?.['time_to_first_byte'], 800, 1800)">
                      <span v-if="pagespeed?.['time_to_first_byte']">
                        {{ pagespeed?.['time_to_first_byte'] }} ms
                      </span>
                      <span v-else>—</span>
                    </div>
                </v-col>
                <v-col cols="12" lg="2" md="4" sm="6">
                    <div class="text-caption text-medium-emphasis">{{ $gettext('First contentful paint') }}</div>
                    <div class="d-flex align-center justify-space-between text-h6"
                      :class="color(pagespeed?.['first_contentful_paint'], 1800, 3000)">
                      <span v-if="pagespeed?.['first_contentful_paint']">
                        {{ pagespeed?.['first_contentful_paint'] }} ms
                      </span>
                      <span v-else>—</span>
                    </div>
                </v-col>
                <v-col cols="12" lg="2" md="4" sm="6">
                    <div class="text-caption text-medium-emphasis">{{ $gettext('Largest contentful paint') }}</div>
                    <div class="d-flex align-center justify-space-between text-h6"
                      :class="color(pagespeed?.['largest_contentful_paint'], 2500, 4000)">
                      <span v-if="pagespeed?.['largest_contentful_paint']">
                        {{ pagespeed?.['largest_contentful_paint'] }} ms
                      </span>
                      <span v-else>—</span>
                    </div>
                </v-col>
                <v-col cols="12" lg="2" md="4" sm="6">
                    <div class="text-caption text-medium-emphasis">{{ $gettext('Interaction to next paint') }}</div>
                    <div class="d-flex align-center justify-space-between text-h6"
                      :class="color(pagespeed?.['interaction_to_next_paint'], 200, 500)">
                      <span v-if="pagespeed?.['interaction_to_next_paint']">
                        {{ pagespeed?.['interaction_to_next_paint'] }} ms
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
              <div v-else class="text-caption text-medium-emphasis mt-2">
                {{ $gettext('No real-user page speed data available.') }}
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Charts -->
      <v-row>
        <v-col cols="12" md="6">
          <v-card class="panel chart">
            <v-card-title class="text-subtitle-1">{{ $gettext('Number of Views & Visits') }}</v-card-title>
            <v-card-text>
              <Line
                :options="{
                  responsive: true,
                  plugins: {
                    legend: {
                      labels: {
                        color: colors?.['surface-variant']
                      },
                      rtl: $vuetify.locale.isRtl
                    }
                  },
                  scales: {
                    x: {
                      ticks: { color: colors?.['surface-variant'] },
                      grid: { color: colors?.['on-surface-variant'] },
                    },
                    y: {
                      beginAtZero: true,
                      ticks: { color: colors?.['surface-variant'] },
                      grid: { color: colors?.['on-surface-variant'] },
                    },
                  }
                }"
                :data="{
                  labels: views.map(d => d.key),
                  grouped: true,
                  datasets: [{
                    borderWidth: 1.5,
                    borderColor: '#0000C0',
                    backgroundColor: '#0000C0',
                    label: $gettext('Views'),
                    data: views.map(d => d.value)
                  }, {
                    borderWidth: 1.5,
                    borderColor: '#C00000',
                    backgroundColor: '#C00000',
                    label: $gettext('Visits'),
                    data: visits.map(d => d.value)
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
                  responsive: true,
                  plugins: {
                    legend: {
                      labels: {
                        color: colors?.['surface-variant']
                      },
                      rtl: $vuetify.locale.isRtl
                    }
                  },
                  scales: {
                    x: {
                      ticks: { color: colors?.['surface-variant'] },
                      grid: { color: colors?.['on-surface-variant'] },
                    },
                    y: {
                      beginAtZero: true,
                      ticks: { color: colors?.['surface-variant'] },
                      grid: { color: colors?.['on-surface-variant'] },
                    }
                  }
                }"
                :data="{
                  labels: durations.map(d => d.key),
                  datasets: [{
                    borderWidth: 1.5  ,
                    borderColor: '#008000',
                    backgroundColor: '#008000',
                    label: $gettext('Duration'),
                    data: durations.map(d => ((d.value || 0) / 60 ).toFixed(1))
                  }]
                }"
              />
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Top lists -->
      <v-row>
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
                    <a class="key" :href="r.key" target="_blank">{{ r.key }}</a>
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

      <div v-if="loading" class="loading-overlay d-flex align-center justify-center">
        <v-progress-circular indeterminate size="32" />
      </div>

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
    aspect-ratio: 425 / 200;
  }

  .panel .value {
    margin-inline-start: 8px;
    text-align: end;
    min-width: 3.5rem;
  }
</style>
