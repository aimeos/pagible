/** @license MIT, https://opensource.org/license/mit */

<script>
import { defineAsyncComponent, markRaw } from 'vue'

const LineChart = defineAsyncComponent(() =>
  Promise.all([
    import('vue-chartjs').then(m => m.Line),
    import('chart.js').then(({ Chart, Title, Tooltip, Legend, LineElement, PointElement, CategoryScale, LinearScale }) => {
      Chart.register(Title, Tooltip, Legend, LineElement, PointElement, CategoryScale, LinearScale)
    })
  ]).then(([Line]) => Line)
)

const BASE_CHART_OPTIONS = {
  maintainAspectRatio: false,
  responsive: true,
  interaction: { mode: 'index', intersect: false }
}

/**
 * Row of line charts of the page metrics, each chart as { show, title, data }
 */
export default {
  components: {
    LineChart
  },

  props: {
    charts: { type: Array, required: true }
  },

  computed: {
    options() {
      const colors = this.$vuetify.theme.current.colors
      const isRtl = this.$vuetify.locale.isRtl
      const tickColor = colors?.['text-secondary'] || colors?.['on-surface']
      const gridColor = colors?.['border-light']

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
    }
  }
}
</script>

<template>
  <v-row>
    <template v-for="c in charts" :key="c.title">
      <v-col v-if="c.show" cols="12" md="6">
        <v-card class="panel chart">
          <v-card-title>{{ c.title }}</v-card-title>
          <v-card-text>
            <LineChart :options="options" :data="c.data" />
          </v-card-text>
        </v-card>
      </v-col>
    </template>
  </v-row>
</template>

<style scoped>
.panel {
  margin-top: 16px !important;
}

.v-card-title {
  font-size: 112.5%;
}

.chart .v-card-text {
  aspect-ratio: 3 / 2;
}
</style>
