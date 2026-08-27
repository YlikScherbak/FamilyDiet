<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api, HEALTH_EVENT_TYPES, healthType } from '../api'
import { useAppStore } from '../stores/app'
import { Chart, buildHealthChart, defaultStyle } from '../health/chart'
import { countOf, pressureStats, severityStats, weightStats } from '../health/stats'

const { t, locale } = useI18n()
const route = useRoute()
const app = useAppStore()

// Параметри звіту приходять зі сторінки «Здоров'я»: людина, період, увімкнені типи.
const memberId = Number(route.query.member) || null
const from = typeof route.query.from === 'string' ? route.query.from : ''
const to = typeof route.query.to === 'string' ? route.query.to : ''
const enabled = String(route.query.types ?? '')
  .split(',')
  .filter((v) => healthType(v))

const events = ref([])
const types = ref(
  Object.fromEntries(
    HEALTH_EVENT_TYPES.map((ht) => [ht.value, { color: ht.color, style: defaultStyle(ht) }]),
  ),
)
const ready = ref(false)
const includeTable = ref(true)

const member = computed(() => app.members.find((m) => m.id === memberId))
const generatedAt = new Date()
const intlLocale = computed(() => (locale.value === 'uk' ? 'uk-UA' : 'en-GB'))

const fmtDate = (iso) => new Date(`${iso}T00:00:00`).toLocaleDateString(intlLocale.value)

const periodLabel = computed(() =>
  from || to
    ? `${from ? fmtDate(from) : '…'} — ${to ? fmtDate(to) : '…'}`
    : t('report.wholePeriod'),
)

const visible = computed(() => events.value.filter((e) => enabled.includes(e.type)))

const stats = computed(() => ({
  pressure: enabled.includes('pressure') ? pressureStats(visible.value) : null,
  weight: enabled.includes('weight') ? weightStats(visible.value) : null,
  severity: HEALTH_EVENT_TYPES.filter((ht) => ht.severity && enabled.includes(ht.value))
    .map((ht) => ({ type: ht, stat: severityStats(visible.value, ht.value) }))
    .filter((s) => s.stat),
  simple: HEALTH_EVENT_TYPES.filter(
    (ht) => !ht.severity && !ht.kg && !ht.structured && enabled.includes(ht.value),
  )
    .map((ht) => ({ type: ht, count: countOf(visible.value, ht.value) }))
    .filter((s) => s.count > 0),
}))

const bp = (x) => (x.sbp == null ? '—' : `${x.sbp}/${x.dbp} · ${x.pulse}`)

function eventValue(e) {
  if (e.type === 'pressure')
    return `${e.payload.systolic}/${e.payload.diastolic} · ${e.payload.pulse}`
  if (e.type === 'weight') return `${e.payload.kg} ${t('health.kg')}`
  if (e.type === 'custom') return e.payload.title ?? ''
  return e.payload.severity ? `${e.payload.severity}/5` : ''
}

// --- Графіки: по одному на календарний місяць, вісь X — межі місяця ---------------

const pad = (n) => String(n).padStart(2, '0')

const months = computed(() => {
  if (visible.value.length === 0) return []
  const start = from || visible.value[0].date
  const end = to || visible.value[visible.value.length - 1].date
  const startDate = new Date(`${start}T00:00:00`)
  const endDate = new Date(`${end}T23:59:59`)
  const list = []
  let cursor = new Date(startDate.getFullYear(), startDate.getMonth(), 1)
  while (cursor <= endDate) {
    const y = cursor.getFullYear()
    const m = cursor.getMonth()
    const key = `${y}-${pad(m + 1)}`
    const monthEvents = visible.value.filter((e) => e.date.startsWith(key))
    if (monthEvents.length) {
      const first = new Date(y, m, 1)
      const last = new Date(y, m + 1, 0, 23, 59, 59)
      list.push({
        key,
        label: first.toLocaleDateString(intlLocale.value, { month: 'long', year: 'numeric' }),
        events: monthEvents,
        min: first < startDate ? startDate : first,
        max: last > endDate ? endDate : last,
      })
    }
    cursor = new Date(y, m + 1, 1)
  }
  return list
})

// Спільна шкала ваги на всі місяці періоду — інакше нахил ліній непорівнюваний
const kgRange = computed(() => {
  const kgs = visible.value
    .filter((e) => e.type === 'weight' && e.payload.kg != null)
    .map((e) => e.payload.kg)
  if (kgs.length < 2) return null
  const pad = Math.max(0.5, (Math.max(...kgs) - Math.min(...kgs)) * 0.1)
  return {
    min: Math.floor((Math.min(...kgs) - pad) * 2) / 2,
    max: Math.ceil((Math.max(...kgs) + pad) * 2) / 2,
  }
})

const canvases = {}
let charts = []

function renderCharts() {
  charts.forEach((c) => c.destroy())
  charts = []
  for (const month of months.value) {
    const canvas = canvases[month.key]
    if (!canvas) continue
    const config = buildHealthChart({
      events: month.events,
      enabled,
      types: types.value,
      t,
      locale: locale.value,
      animate: false,
      xRange: { min: month.min, max: month.max },
      kgRange: kgRange.value,
      smoothPulse: true,
    })
    if (config) charts.push(new Chart(canvas, config))
  }
}

// --- Щоденник: один рядок на день, колонка на кожен увімкнений тип -----------------

const days = computed(() => {
  const byDate = new Map()
  for (const e of visible.value) {
    if (!byDate.has(e.date)) byDate.set(e.date, [])
    byDate.get(e.date).push(e)
  }
  return [...byDate.entries()].map(([date, list]) => ({
    date,
    cells: enabled.map((type) => list.filter((e) => e.type === type)),
  }))
})

const cellLine = (e) =>
  `${e.time ? e.time + ' ' : ''}${eventValue(e)}${e.note ? ' — ' + e.note : ''}`.trim()

const printReport = () => window.print()

// Chart.js не встигає перерахувати розмір при переході в print-режим —
// перед друком задаємо розмір під альбомний A4 явно, після — повертаємо адаптивний.
const PRINT_CHART = { w: 1040, h: 620 }
const onBeforePrint = () => charts.forEach((c) => c.resize(PRINT_CHART.w, PRINT_CHART.h))
const onAfterPrint = () => charts.forEach((c) => c.resize())
window.addEventListener('beforeprint', onBeforePrint)
window.addEventListener('afterprint', onAfterPrint)

watch([months, locale], () => nextTick(renderCharts))
onBeforeUnmount(() => {
  window.removeEventListener('beforeprint', onBeforePrint)
  window.removeEventListener('afterprint', onAfterPrint)
  charts.forEach((c) => c.destroy())
})

onMounted(async () => {
  const params = new URLSearchParams()
  if (memberId) params.set('memberId', String(memberId))
  if (from) params.set('from', from)
  if (to) params.set('to', to)

  const [, settings, list] = await Promise.all([
    app.loadMembers(),
    api.get('/settings/health_chart').catch(() => ({})),
    api.get(`/health-events?${params}`),
  ])
  for (const ht of HEALTH_EVENT_TYPES) {
    types.value[ht.value] = {
      color: settings.types?.[ht.value]?.color ?? ht.color,
      style: settings.types?.[ht.value]?.style ?? defaultStyle(ht),
    }
  }
  events.value = list
  ready.value = true
  await nextTick()
  renderCharts()
})
</script>

<template>
  <div class="report">
    <div class="toolbar no-print">
      <RouterLink to="/health"
        ><button>{{ $t('report.back') }}</button></RouterLink
      >
      <label class="muted" style="display: flex; align-items: center; gap: 6px">
        <input v-model="includeTable" type="checkbox" style="width: auto" />
        {{ $t('report.includeTable') }}
      </label>
      <span class="spacer" />
      <button class="primary" @click="printReport">{{ $t('report.print') }}</button>
    </div>

    <header class="report-head">
      <h1>{{ $t('report.title') }}</h1>
      <dl>
        <dt>{{ $t('report.member') }}</dt>
        <dd>{{ member?.name ?? '—' }}</dd>
        <dt>{{ $t('report.period') }}</dt>
        <dd>{{ periodLabel }}</dd>
        <dt>{{ $t('report.includes') }}</dt>
        <dd>
          {{ enabled.map((v) => `${healthType(v).icon} ${$t(`healthTypes.${v}`)}`).join(', ') }}
        </dd>
        <dt>{{ $t('report.generated') }}</dt>
        <dd>{{ generatedAt.toLocaleString(intlLocale) }}</dd>
      </dl>
    </header>

    <template v-if="ready">
      <p v-if="visible.length === 0" class="muted">{{ $t('report.noEvents') }}</p>

      <section v-if="visible.length" class="card">
        <h2>{{ $t('report.summary') }}</h2>
        <div class="summary-grid">
          <div v-if="stats.pressure" class="stat">
            <strong>🩺 {{ $t('healthTypes.pressure') }}</strong>
            <div class="muted">{{ stats.pressure.count }} {{ $t('report.measurements') }}</div>
            <table class="data compact">
              <tbody>
                <tr>
                  <td>{{ $t('report.avgAll') }}</td>
                  <td>
                    <b>{{ bp(stats.pressure.all) }}</b>
                  </td>
                </tr>
                <tr v-if="stats.pressure.morningCount">
                  <td>{{ $t('report.morning') }} ({{ stats.pressure.morningCount }})</td>
                  <td>{{ bp(stats.pressure.morning) }}</td>
                </tr>
                <tr v-if="stats.pressure.eveningCount">
                  <td>{{ $t('report.evening') }} ({{ stats.pressure.eveningCount }})</td>
                  <td>{{ bp(stats.pressure.evening) }}</td>
                </tr>
                <tr>
                  <td>{{ $t('report.maxSbp') }}</td>
                  <td>
                    {{ stats.pressure.max.sbp }}/{{ stats.pressure.max.dbp }}
                    <span class="muted">
                      ({{ fmtDate(stats.pressure.max.date)
                      }}{{ stats.pressure.max.time ? ' ' + stats.pressure.max.time : '' }})
                    </span>
                  </td>
                </tr>
                <tr>
                  <td>{{ $t('report.abovePct') }}</td>
                  <td :class="{ over: stats.pressure.abovePct >= 50 }">
                    {{ stats.pressure.abovePct }}%
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="stats.weight" class="stat">
            <strong>⚖️ {{ $t('healthTypes.weight') }}</strong>
            <div class="muted">{{ stats.weight.count }} {{ $t('report.measurements') }}</div>
            <table class="data compact">
              <tbody>
                <tr>
                  <td>{{ $t('report.weightChange') }}</td>
                  <td>
                    <b>{{ stats.weight.first }} → {{ stats.weight.last }} {{ $t('health.kg') }}</b>
                    <span class="muted">
                      ({{ stats.weight.delta > 0 ? '+' : '' }}{{ stats.weight.delta }})
                    </span>
                  </td>
                </tr>
                <tr>
                  <td>{{ $t('report.weightRange') }}</td>
                  <td>{{ stats.weight.min }} – {{ stats.weight.max }} {{ $t('health.kg') }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-for="s in stats.severity" :key="s.type.value" class="stat">
            <strong>{{ s.type.icon }} {{ $t(`healthTypes.${s.type.value}`) }}</strong>
            <div class="muted">{{ s.stat.count }} {{ $t('report.times') }}</div>
            <table v-if="s.stat.avgSeverity != null" class="data compact">
              <tbody>
                <tr>
                  <td>{{ $t('report.avgSeverity') }}</td>
                  <td>
                    <b>{{ s.stat.avgSeverity }}/5</b>
                  </td>
                </tr>
                <tr>
                  <td>{{ $t('report.maxSeverity') }}</td>
                  <td>{{ s.stat.maxSeverity }}/5</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-for="s in stats.simple" :key="s.type.value" class="stat">
            <strong>{{ s.type.icon }} {{ $t(`healthTypes.${s.type.value}`) }}</strong>
            <div class="muted">{{ s.count }} {{ $t('report.times') }}</div>
          </div>
        </div>
      </section>

      <!-- Кожен місяць — окремий альбомний аркуш при друку -->
      <section v-for="month in months" :key="month.key" class="card chart-page">
        <h2>{{ $t('report.chart') }} — {{ month.label }}</h2>
        <div class="chart-box">
          <canvas :ref="(el) => (canvases[month.key] = el)"></canvas>
        </div>
      </section>

      <section v-if="includeTable && days.length" class="card table-page">
        <h2>{{ $t('report.dailyLog') }}</h2>
        <table class="data log">
          <thead>
            <tr>
              <th>{{ $t('report.date') }}</th>
              <th v-for="type in enabled" :key="type">
                {{ healthType(type).icon }} {{ $t(`healthTypes.${type}`) }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="day in days" :key="day.date">
              <td class="nowrap">{{ fmtDate(day.date) }}</td>
              <td v-for="(cell, i) in day.cells" :key="i">
                <div v-for="e in cell" :key="e.id">{{ cellLine(e) }}</div>
              </td>
            </tr>
          </tbody>
        </table>
      </section>

      <p class="muted disclaimer">{{ $t('report.disclaimer') }}</p>
    </template>
  </div>
</template>

<style scoped>
.report {
  max-width: 900px;
  margin: 0 auto;
}
.report-head h1 {
  margin-bottom: 8px;
}
.report-head dl {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 4px 16px;
  margin: 0 0 16px;
  font-size: 14px;
}
.report-head dt {
  color: var(--muted);
}
.report-head dd {
  margin: 0;
}
.card {
  margin-bottom: 16px;
}
.card h2 {
  margin: 0 0 10px;
  font-size: 17px;
}
.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 16px;
}
.stat .muted {
  font-size: 12.5px;
  margin-bottom: 6px;
}
.compact td {
  padding: 4px 8px;
  font-size: 13px;
}
.chart-box {
  height: 340px;
}
.log td,
.log th {
  font-size: 12.5px;
  vertical-align: top;
  padding: 4px 8px;
}
.nowrap {
  white-space: nowrap;
}
.over {
  color: var(--danger);
  font-weight: 600;
}
.disclaimer {
  font-size: 12px;
  margin-top: 24px;
}

@page {
  size: A4;
  margin: 12mm;
}
@page landscape {
  size: A4 landscape;
  margin: 10mm;
}
@media print {
  .report {
    max-width: none;
  }
  .card {
    box-shadow: none;
    border: 1px solid #ddd;
    break-inside: avoid;
  }
  /* Графік — на власному альбомному аркуші, по центру і на всю ширину */
  .chart-page {
    page: landscape;
    break-before: page;
    border: none;
    padding: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
  }
  .chart-page h2 {
    align-self: flex-start;
  }
  .chart-box {
    width: 100%;
    height: 165mm;
    display: flex;
    justify-content: center;
  }
  .table-page {
    break-before: page;
  }
  .log tr {
    break-inside: avoid;
  }
}
</style>
