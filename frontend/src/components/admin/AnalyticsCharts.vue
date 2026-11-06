<script setup>
// ANDARMA_FASE5B_HIGHCHARTS_USUARIOS_20261105
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import Highcharts from 'highcharts/esm/highcharts'
import 'highcharts/esm/modules/exporting'

const props = defineProps({ analytics: { type: Object, default: null } })
const root = ref(null)
let chart = null
const rows = computed(() => {
  const days = Array.isArray(props.analytics?.daily_views) ? props.analytics.daily_views : []
  const clicks = new Map((props.analytics?.daily_clicks || []).map(row => [String(row.date), Number(row.clicks || 0)]))
  return days.map(row => ({
    date: String(row.date || ''),
    label: String(row.day || row.date || '').slice(-5),
    views: Number(row.views || 0),
    clicks: clicks.get(String(row.date)) || 0
  }))
})
const totalViews = computed(() => rows.value.reduce((sum, row) => sum + row.views, 0))
const totalClicks = computed(() => rows.value.reduce((sum, row) => sum + row.clicks, 0))
const fmt = value => Number(value).toLocaleString('es-MX')
const xLabel = day => {
  const [year, month, date] = day.split('-').map(Number)
  if (!year || !month || !date) return day
  return new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: 'short', timeZone: 'UTC' }).format(new Date(Date.UTC(year, month - 1, date)))
}
function renderChart() {
  if (!root.value || !props.analytics || !rows.value.length) {
    if (chart) { chart.destroy(); chart = null }
    return
  }
  const opts = {
    chart: {
      type: 'column', backgroundColor: 'transparent', height: 315,
      spacing: [10, 8, 12, 8], style: {fontFamily: "'DM Sans', 'Manrope', system-ui, sans-serif"}
    },
    title: {text: undefined},
    subtitle: {text: undefined},
    accessibility: {enabled: true, description: 'Comparativo de visitas y clics en WhatsApp por día.'},
    xAxis: {
      categories: rows.value.map(r => xLabel(r.date)),
      crosshair: {color: 'rgba(0,102,224,.06)'},
      lineColor: '#DCE7F3', tickColor: 'transparent',
      labels: {style: {color: '#58728F', fontSize: '11px'}}
    },
    yAxis: {
      min: 0, allowDecimals: false, title: {text: undefined},
      gridLineColor: '#EAF0F7',
      labels: {style: {color: '#71869F', fontSize: '11px'}}
    },
    tooltip: {
      shared: true, useHTML: false,
      backgroundColor: '#FFFFFF', borderColor: '#DCE7F3', borderRadius: 12,
      shadow: {color: 'rgba(16,46,78,.10)', offsetX: 0, offsetY: 8, opacity: .2, width: 12},
      valueDecimals: 0
    },
    legend: {
      enabled: true, align: 'center', verticalAlign: 'bottom',
      itemStyle: {color: '#36536F', fontSize: '12px', fontWeight: '600'},
      symbolRadius: 4
    },
    plotOptions: {
      column: {
        borderWidth: 0, borderRadius: 5,
        groupPadding: .15, pointPadding: .08, maxPointWidth: 34,
        states: {hover: {brightness: .08}}
      },
      series: {animation: {duration: 400}}
    },
    exporting: {enabled: true, buttons: {contextButton: {symbolStroke: '#58728F', theme: {fill: 'transparent', stroke: 'transparent'}}}},
    credits: {enabled: true},
    series: [
      {name: 'Visitas', color: '#0891FF', data: rows.value.map(r => r.views)},
      {name: 'Clics en WhatsApp', color: '#18A66C', data: rows.value.map(r => r.clicks)}
    ]
  }
  if (!chart) chart = Highcharts.chart(root.value, opts)
  else chart.update(opts, true, true)
}
onMounted(() => nextTick(renderChart))
watch(() => props.analytics, () => nextTick(renderChart), {deep: true})
onBeforeUnmount(() => { if(chart) {chart.destroy(); chart = null} })
</script>

<template>
  <div class="ad-highcharts-analytics">
    <template v-if="rows.length">
      <div class="ad-highcharts-totals" aria-live="polite">
        <span><i class="ad-highcharts-dot ad-highcharts-dot--views"></i><strong>{{ fmt(totalViews) }}</strong> visitas</span>
        <span><i class="ad-highcharts-dot ad-highcharts-dot--clicks"></i><strong>{{ fmt(totalClicks) }}</strong> clics</span>
      </div>
      <div ref="root" class="ad-highcharts-canvas" role="img" :aria-label="'Gráfica diaria: '+rows.map(r=>xLabel(r.date)+', '+r.views+' visitas y '+r.clicks+' clics').join('; ')"></div>
      <p class="ad-highcharts-help">Las visitas son cargas registradas y los clics son aperturas de enlaces a WhatsApp; no representan usuarios únicos ni conversaciones confirmadas.</p>
    </template>
    <div v-else class="ad-highcharts-empty" role="status">Sin datos de analítica para mostrar.</div>
  </div>
</template>

<style scoped>
.ad-highcharts-analytics{width:100%;min-width:0;display:grid;gap:4px}
.ad-highcharts-totals{display:flex;gap:18px;flex-wrap:wrap;margin:2px 0 4px;color:#5B7391;font-size:12px}
.ad-highcharts-totals>span{display:inline-flex;align-items:center;gap:6px}
.ad-highcharts-totals strong{color:#17324E;font-size:17px}
.ad-highcharts-dot{display:inline-block;width:9px;height:9px;border-radius:3px}
.ad-highcharts-dot--views{background:#0891FF}
.ad-highcharts-dot--clicks{background:#18A66C}
.ad-highcharts-canvas{width:100%;min-height:315px}
.ad-highcharts-help{font-size:11px;color:#72879C;line-height:1.5;margin:0 4px 2px}
.ad-highcharts-empty{padding:48px 12px;color:#71869F;text-align:center;font-size:13px}
@media(max-width:600px){.ad-highcharts-totals{gap:12px}.ad-highcharts-canvas{min-height:315px}}
</style>
