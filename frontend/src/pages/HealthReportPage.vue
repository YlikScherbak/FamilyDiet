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

const member = computed(() => app.members.find((m) => m.id === memberId))
const generatedAt = new Date()

const fmtDate = (iso) =>
  new Date(`${iso}T00:00:00`).toLocaleDateString(locale.value === 'uk' ? 'uk-UA' : 'en-GB')

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

const printReport = () => window.print()

const canvas = ref(null)
let chart = null

function renderChart() {
  chart?.destroy()
  chart = null
  if (!canvas.value) return
  const config = buildHealthChart({
    events: visible.value,
    enabled,
    types: types.value,
    t,
    locale: locale.value,
    animate: false,
  })
  if (config) chart = new Chart(canvas.value, config)
}

watch([visible, locale], () => nextTick(renderChart))
onBeforeUnmount(() => chart?.destroy())

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
  renderChart()
})
</script>

<template>
  <div class="report">
    <div class="toolbar no-print">
      <RouterLink to="/health"
        ><button>{{ $t('report.back') }}</button></RouterLink
      >
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
        <dd>{{ generatedAt.toLocaleString(locale === 'uk' ? 'uk-UA' : 'en-GB') }}</dd>
      </dl>
    </header>

    <template v-if="ready">
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

      <section v-if="visible.length" class="card">
        <h2>{{ $t('report.chart') }}</h2>
        <div style="height: 340px"><canvas ref="canvas"></canvas></div>
      </section>

      <section class="card">
        <h2>{{ $t('report.events') }}</h2>
        <p v-if="visible.length === 0" class="muted">{{ $t('report.noEvents') }}</p>
        <table v-else class="data">
          <thead>
            <tr>
              <th>{{ $t('report.date') }}</th>
              <th>{{ $t('report.time') }}</th>
              <th>{{ $t('report.type') }}</th>
              <th>{{ $t('report.value') }}</th>
              <th>{{ $t('report.note') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="e in visible" :key="e.id">
              <td>{{ fmtDate(e.date) }}</td>
              <td>{{ e.time ?? '—' }}</td>
              <td>{{ healthType(e.type).icon }} {{ $t(`healthTypes.${e.type}`) }}</td>
              <td>{{ eventValue(e) }}</td>
              <td class="muted">{{ e.note ?? '' }}</td>
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
.over {
  color: var(--danger);
  font-weight: 600;
}
.disclaimer {
  font-size: 12px;
  margin-top: 24px;
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
}
</style>
