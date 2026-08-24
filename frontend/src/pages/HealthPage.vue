<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import ukLocale from '@fullcalendar/core/locales/uk'
import { Chart } from 'chart.js/auto'
import annotationPlugin from 'chartjs-plugin-annotation'
import 'chartjs-adapter-date-fns'
import { uk } from 'date-fns/locale'
import { api, HEALTH_EVENT_TYPES, healthType } from '../api'
import { useAppStore } from '../stores/app'

Chart.register(annotationPlugin)

const app = useAppStore()
const memberId = ref(null)
const events = ref([])
const error = ref('')

const todayStr = () => new Date().toISOString().slice(0, 10)
const nowTime = () => new Date().toTimeString().slice(0, 5)

async function load() {
  if (!memberId.value) return
  events.value = await api.get(`/health-events?memberId=${memberId.value}`)
}

// --- Модалка події -----------------------------------------------------------

const modal = reactive({ open: false, id: null })
const form = reactive({
  date: todayStr(),
  time: nowTime(),
  type: 'pressure',
  systolic: null,
  diastolic: null,
  pulse: null,
  kg: null,
  severity: null,
  title: '',
  note: '',
})

const formType = computed(() => healthType(form.type))

function openCreate(date) {
  Object.assign(form, {
    date,
    time: nowTime(),
    type: 'pressure',
    systolic: null,
    diastolic: null,
    pulse: null,
    kg: null,
    severity: null,
    title: '',
    note: '',
  })
  modal.id = null
  modal.open = true
}

function openEdit(event) {
  Object.assign(form, {
    date: event.date,
    time: event.time ?? '',
    type: event.type,
    systolic: event.payload.systolic ?? null,
    diastolic: event.payload.diastolic ?? null,
    pulse: event.payload.pulse ?? null,
    kg: event.payload.kg ?? null,
    severity: event.payload.severity ?? null,
    title: event.payload.title ?? '',
    note: event.note ?? '',
  })
  modal.id = event.id
  modal.open = true
}

async function saveEvent() {
  error.value = ''
  const payload = {}
  if (formType.value?.structured) {
    payload.systolic = form.systolic
    payload.diastolic = form.diastolic
    payload.pulse = form.pulse
  }
  if (formType.value?.kg) payload.kg = form.kg
  if (formType.value?.severity && form.severity) payload.severity = form.severity
  if (formType.value?.custom) payload.title = form.title

  const body = {
    familyMemberId: memberId.value,
    date: form.date,
    time: form.time || undefined,
    type: form.type,
    payload,
    note: form.note || undefined,
  }

  try {
    if (modal.id) await api.put(`/health-events/${modal.id}`, body)
    else await api.post('/health-events', body)
    modal.open = false
    await load()
  } catch (e) {
    error.value = e.message
  }
}

async function removeEvent() {
  if (!modal.id) return
  await api.del(`/health-events/${modal.id}`)
  modal.open = false
  await load()
}

// --- Календар -----------------------------------------------------------------

function eventTitle(e) {
  const t = healthType(e.type)
  if (e.type === 'pressure')
    return `${t.icon} ${e.payload.systolic}/${e.payload.diastolic} · ${e.payload.pulse}`
  if (e.type === 'weight') return `${t.icon} ${e.payload.kg} кг`
  if (e.type === 'custom') return `${t.icon} ${e.payload.title}`
  const severity = e.payload.severity ? ` (${e.payload.severity}/5)` : ''
  return `${t.icon} ${t.label}${severity}`
}

const calendarOptions = computed(() => ({
  plugins: [dayGridPlugin, interactionPlugin],
  initialView: 'dayGridMonth',
  locale: ukLocale,
  firstDay: 1,
  height: 'auto',
  fixedWeekCount: false,
  dayMaxEventRows: 4,
  events: events.value.map((e) => ({
    id: String(e.id),
    title: eventTitle(e),
    start: e.time ? `${e.date}T${e.time}` : e.date,
    allDay: !e.time,
    backgroundColor: healthType(e.type)?.color,
    borderColor: healthType(e.type)?.color,
  })),
  dateClick: (info) => openCreate(info.dateStr),
  eventClick: (info) => {
    const found = events.value.find((e) => String(e.id) === info.event.id)
    if (found) openEdit(found)
  },
}))

// --- Графіки тиску і пульсу ----------------------------------------------------

const range = reactive({ from: '', to: todayStr(), preset: 30 })

function applyPreset(days) {
  range.preset = days
  range.to = todayStr()
  if (days === 0) {
    range.from = ''
  } else {
    const d = new Date()
    d.setDate(d.getDate() - days + 1)
    range.from = d.toISOString().slice(0, 10)
  }
}
applyPreset(30)

const inRange = (e) => (!range.from || e.date >= range.from) && (!range.to || e.date <= range.to)

const pressureEvents = computed(() =>
  events.value.filter((e) => e.type === 'pressure' && inRange(e)),
)

// --- Накладені типи подій і їх налаштування (зберігаються в БД) -----------------
// Тиск — такий самий перемикач, як і решта, лише з фіксованим виглядом
// (САТ/ДАТ/пульс + межі норми), тому в панелі стилів його немає.

const OVERLAY_TYPES = HEALTH_EVENT_TYPES
const PANEL_TYPES = HEALTH_EVENT_TYPES.filter((t) => t.value !== 'pressure')
const defaultStyle = (t) => (t.severity ? 'point' : t.kg ? 'line' : 'marker')

const showCfg = ref(false)
const chartCfg = reactive({ enabled: [], types: {} })
for (const t of OVERLAY_TYPES) chartCfg.types[t.value] = { color: t.color, style: defaultStyle(t) }

let cfgLoaded = false
async function loadChartCfg() {
  try {
    const saved = await api.get('/settings/health_chart')
    for (const t of OVERLAY_TYPES) {
      chartCfg.types[t.value] = {
        color: saved.types?.[t.value]?.color ?? t.color,
        style: saved.types?.[t.value]?.style ?? defaultStyle(t),
      }
    }
    chartCfg.enabled = Array.isArray(saved.enabled) ? saved.enabled : ['pressure']
  } finally {
    cfgLoaded = true
  }
}

let saveTimer = null
function persistChartCfg() {
  if (!cfgLoaded) return
  clearTimeout(saveTimer)
  saveTimer = setTimeout(() => {
    api
      .put('/settings/health_chart', { enabled: chartCfg.enabled, types: chartCfg.types })
      .catch(() => {})
  }, 400)
}

function toggleType(type) {
  const i = chartCfg.enabled.indexOf(type)
  if (i === -1) chartCfg.enabled.push(type)
  else chartCfg.enabled.splice(i, 1)
}

// --- Пресети графіка: іменовані комбінації накладень, зберігаються в БД ---------

const DEFAULT_PRESETS = [
  {
    name: 'Тиск і мігрені',
    enabled: ['pressure', 'migraine', 'headache', 'medication'],
    types: null,
  },
  { name: 'Вага і тиск', enabled: ['pressure', 'weight'], types: null },
  { name: 'Болі та ліки', enabled: ['migraine', 'headache', 'medication'], types: null },
]

const presets = ref([])
const newPresetName = ref('')

async function loadPresets() {
  try {
    const saved = await api.get('/settings/health_chart_presets')
    presets.value =
      Array.isArray(saved.presets) && saved.presets.length ? saved.presets : DEFAULT_PRESETS
  } catch {
    presets.value = DEFAULT_PRESETS
  }
}

function persistPresets() {
  api.put('/settings/health_chart_presets', { presets: presets.value }).catch(() => {})
}

const isActivePreset = (p) =>
  JSON.stringify([...p.enabled].sort()) === JSON.stringify([...chartCfg.enabled].sort())

function applyChartPreset(p) {
  chartCfg.enabled = [...p.enabled]
  if (p.types) {
    for (const [type, cfg] of Object.entries(p.types)) {
      if (chartCfg.types[type]) chartCfg.types[type] = { ...cfg }
    }
  }
}

/** Зберігає поточну комбінацію накладень і стилів під назвою (та сама назва — перезапис). */
function saveCurrentAsPreset() {
  const name = newPresetName.value.trim()
  if (!name) return
  const preset = {
    name: name.slice(0, 40),
    enabled: [...chartCfg.enabled],
    types: JSON.parse(JSON.stringify(chartCfg.types)),
  }
  const i = presets.value.findIndex((p) => p.name === preset.name)
  if (i >= 0) presets.value[i] = preset
  else presets.value.push(preset)
  newPresetName.value = ''
  persistPresets()
}

function removePreset(preset) {
  presets.value = presets.value.filter((p) => p !== preset)
  persistPresets()
}

const overlayEvents = computed(() =>
  events.value.filter((e) => chartCfg.enabled.includes(e.type) && inRange(e)),
)

const hasChartData = computed(() => overlayEvents.value.length > 0)

const pressureCanvas = ref(null)
let pressureChart = null

// Подія без часу ставиться на полудень (для точок) або розтягується смугою на день (для маркерів)
const eventMoment = (e) => new Date(`${e.date}T${e.time ?? '12:00'}:00`)

function baseOptions(annotations, axes = {}) {
  const scales = {
    x: {
      type: 'time',
      adapters: { date: { locale: uk } },
      time: {
        tooltipFormat: 'dd.MM HH:mm',
        minUnit: 'hour',
        displayFormats: { day: 'dd.MM', hour: 'dd.MM HH:mm' },
      },
      ticks: { maxRotation: 60, autoSkip: true, maxTicksLimit: 12 },
    },
    // Явно, інакше датасети без yAxisID чіпляються до першої знайденої осі (severity)
    y: { position: 'left', display: axes.y !== false },
  }
  if (axes.severity) {
    scales.severity = {
      position: 'right',
      min: 0,
      max: 5,
      grid: { drawOnChartArea: false },
      title: { display: true, text: 'Тяжкість, 1–5' },
    }
  }
  if (axes.kg) {
    scales.kg = {
      position: 'right',
      grid: { drawOnChartArea: false },
      title: { display: true, text: 'кг' },
    }
  }
  if (axes.bpm) {
    scales.bpm = {
      position: 'right',
      grid: { drawOnChartArea: false },
      title: { display: true, text: 'Пульс, уд./хв' },
    }
  }

  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'nearest', intersect: false },
    plugins: {
      legend: { labels: { usePointStyle: true, boxHeight: 6 } },
      annotation: { annotations },
      tooltip: {
        callbacks: {
          label: (ctx) => {
            const base = `${ctx.dataset.label}: ${ctx.parsed.y}`
            if (ctx.dataset.yAxisID === 'severity') {
              return `${base}/5${ctx.raw.note ? ' — ' + ctx.raw.note : ''}`
            }
            if (ctx.dataset.yAxisID === 'kg') {
              return `${base} кг${ctx.raw.note ? ' — ' + ctx.raw.note : ''}`
            }
            if (ctx.dataset.yAxisID === 'bpm') {
              return `${base} уд./хв`
            }
            return base
          },
        },
      },
    },
    scales,
  }
}

/**
 * Датасети накладень (точка/стовпчик/лінія) для увімкнених типів: тяжкість 1–5 —
 * на осі severity, вага — на власній осі кг. Події без числа стають маркерами.
 */
function overlayDatasets() {
  const datasets = []
  for (const type of chartCfg.enabled) {
    if (type === 'pressure') continue // малюється окремо: САТ/ДАТ + пульс
    const cfg = chartCfg.types[type]
    const t = healthType(type)
    if (!cfg || cfg.style === 'marker') continue
    const points = overlayEvents.value
      .filter((e) => e.type === type && (t.kg ? e.payload.kg != null : e.payload.severity != null))
      .map((e) => ({
        x: eventMoment(e),
        y: t.kg ? e.payload.kg : e.payload.severity,
        note: e.note,
      }))
    if (points.length === 0) continue
    datasets.push({
      type: cfg.style === 'bar' ? 'bar' : cfg.style === 'line' ? 'line' : 'scatter',
      label: `${t.icon} ${t.label}`,
      data: points,
      yAxisID: t.kg ? 'kg' : 'severity',
      borderColor: cfg.color,
      backgroundColor: cfg.style === 'bar' ? cfg.color + '99' : cfg.color,
      pointStyle: t.kg ? 'circle' : 'rectRot',
      pointRadius: t.kg ? 3.5 : 6,
      barThickness: 10,
      tension: 0.25,
      spanGaps: true,
    })
  }

  return datasets
}

/** Маркери: вертикальна пунктирна лінія (є час) або смуга на весь день (часу немає). */
function overlayAnnotations() {
  const annotations = {}
  let i = 0
  for (const e of overlayEvents.value) {
    if (e.type === 'pressure') continue
    const cfg = chartCfg.types[e.type]
    const t = healthType(e.type)
    const hasValue = t.kg ? e.payload.kg != null : t.severity ? e.payload.severity != null : false
    const asMarker = !cfg || cfg.style === 'marker' || !hasValue
    if (!asMarker) continue
    const valueText = e.payload.severity
      ? ` ${e.payload.severity}/5`
      : e.payload.kg
        ? ` ${e.payload.kg}`
        : ''
    const label = {
      display: true,
      content: `${t.icon}${valueText}`,
      position: 'end',
      backgroundColor: cfg.color,
      font: { size: 10 },
      padding: 3,
    }
    annotations['ev' + i++] = e.time
      ? {
          type: 'line',
          xMin: eventMoment(e),
          xMax: eventMoment(e),
          borderColor: cfg.color,
          borderWidth: 1.5,
          borderDash: [4, 4],
          label,
        }
      : {
          type: 'box',
          xMin: new Date(`${e.date}T00:00:00`),
          xMax: new Date(`${e.date}T23:59:59`),
          backgroundColor: cfg.color + '22',
          borderWidth: 0,
          label,
        }
  }

  return annotations
}

const normLine = (value, label, color) => ({
  type: 'line',
  yMin: value,
  yMax: value,
  borderColor: color,
  borderWidth: 1.5,
  borderDash: [6, 4],
  label: {
    display: true,
    content: label,
    position: 'end',
    font: { size: 10 },
    backgroundColor: color,
  },
})

function renderCharts() {
  pressureChart?.destroy()
  pressureChart = null
  if (!pressureCanvas.value) return

  const pressureOn = chartCfg.enabled.includes('pressure')
  const data = pressureOn ? pressureEvents.value : []
  const overlays = overlayDatasets()
  if (data.length === 0 && overlays.length === 0 && overlayEvents.value.length === 0) return

  const pressureDatasets =
    data.length === 0
      ? []
      : [
          {
            label: 'Систолічний (САТ)',
            data: data.map((e) => ({ x: eventMoment(e), y: e.payload.systolic })),
            yAxisID: 'y',
            borderColor: '#b91c1c',
            backgroundColor: '#b91c1c',
            tension: 0.25,
          },
          {
            label: 'Діастолічний (ДАТ)',
            data: data.map((e) => ({ x: eventMoment(e), y: e.payload.diastolic })),
            yAxisID: 'y',
            borderColor: '#1d4ed8',
            backgroundColor: '#1d4ed8',
            tension: 0.25,
          },
          {
            label: 'Пульс',
            data: data.map((e) => ({ x: eventMoment(e), y: e.payload.pulse })),
            yAxisID: 'bpm',
            borderColor: '#2f6b4f',
            backgroundColor: '#2f6b4f',
            borderDash: [3, 3],
            tension: 0.25,
          },
        ]

  const pressureAnnotations =
    data.length === 0
      ? {}
      : {
          sysNorm: normLine(135, 'норма САТ 135', '#b91c1c'),
          diaNorm: normLine(85, 'норма ДАТ 85', '#1d4ed8'),
        }

  pressureChart = new Chart(pressureCanvas.value, {
    type: 'line',
    data: { datasets: [...pressureDatasets, ...overlays] },
    options: baseOptions(
      { ...pressureAnnotations, ...overlayAnnotations() },
      {
        y: data.length > 0,
        bpm: data.length > 0,
        severity: overlays.some((d) => d.yAxisID === 'severity'),
        kg: overlays.some((d) => d.yAxisID === 'kg'),
      },
    ),
  })
}

watch([pressureEvents, overlayEvents], () => nextTick(renderCharts))
watch(
  chartCfg,
  () => {
    persistChartCfg()
    nextTick(renderCharts)
  },
  { deep: true },
)
onBeforeUnmount(() => {
  pressureChart?.destroy()
})

watch(memberId, load)

onMounted(async () => {
  await Promise.all([app.loadMembers(), loadChartCfg(), loadPresets()])
  memberId.value = app.members[0]?.id ?? null
})
</script>

<template>
  <div>
    <h1>Здоров'я</h1>

    <div class="toolbar">
      <select v-model.number="memberId">
        <option v-for="m in app.members" :key="m.id" :value="m.id">{{ m.name }}</option>
      </select>
      <span class="muted">Клік по дню — додати подію, по події — редагувати.</span>
    </div>

    <div class="card" style="margin-bottom: 16px">
      <FullCalendar :options="calendarOptions" />
    </div>

    <div class="card">
      <div class="toolbar" style="margin-bottom: 8px">
        <strong>Графіки</strong>
        <button
          v-for="p in [
            { d: 7, l: 'Тиждень' },
            { d: 30, l: 'Місяць' },
            { d: 90, l: '3 місяці' },
            { d: 0, l: 'Все' },
          ]"
          :key="p.d"
          class="small"
          :class="{ primary: range.preset === p.d }"
          @click="applyPreset(p.d)"
        >
          {{ p.l }}
        </button>
        <input v-model="range.from" type="date" @change="range.preset = -1" />
        <span class="muted">—</span>
        <input v-model="range.to" type="date" @change="range.preset = -1" />
      </div>

      <div v-if="presets.length" class="toolbar" style="margin-bottom: 8px">
        <span class="muted">Пресети:</span>
        <button
          v-for="p in presets"
          :key="p.name"
          class="small"
          :class="{ primary: isActivePreset(p) }"
          @click="applyChartPreset(p)"
        >
          {{ p.name }}
        </button>
      </div>

      <div class="toolbar" style="margin-bottom: 8px">
        <span class="muted">Накласти на графік:</span>
        <button
          v-for="t in OVERLAY_TYPES"
          :key="t.value"
          class="small"
          :class="{ primary: chartCfg.enabled.includes(t.value) }"
          @click="toggleType(t.value)"
        >
          {{ t.icon }} {{ t.label }}
        </button>
        <button class="small" style="margin-left: auto" @click="showCfg = !showCfg">
          ⚙ Вигляд
        </button>
      </div>

      <div v-if="showCfg" class="cfg-panel">
        <div v-for="t in PANEL_TYPES" :key="t.value" class="cfg-row">
          <span class="cfg-name">{{ t.icon }} {{ t.label }}</span>
          <input v-model="chartCfg.types[t.value].color" type="color" />
          <select v-model="chartCfg.types[t.value].style" :disabled="!t.severity && !t.kg">
            <option value="marker">маркер</option>
            <template v-if="t.severity || t.kg">
              <option value="point">точка</option>
              <option value="bar">стовпчик</option>
              <option value="line">лінія</option>
            </template>
          </select>
        </div>
        <p class="muted" style="margin: 6px 0 0">
          Події без числового значення завжди відображаються маркером (лінія на моменті часу або
          смуга на день). «Тиск і пульс» має фіксований вигляд: САТ/ДАТ, пунктирний пульс і межі
          норми. Зберігається автоматично.
        </p>

        <div class="toolbar" style="margin: 12px 0 4px">
          <input v-model="newPresetName" placeholder="Назва пресета" style="width: 220px" />
          <button class="small" :disabled="!newPresetName.trim()" @click="saveCurrentAsPreset">
            💾 Зберегти поточний як пресет
          </button>
        </div>
        <div v-for="p in presets" :key="'m' + p.name" class="cfg-row">
          <span class="cfg-name">{{ p.name }}</span>
          <span class="muted" style="flex: 1; font-size: 12.5px">
            {{ p.enabled.map((v) => healthType(v)?.icon).join(' ') || 'без накладень' }}
          </span>
          <button class="small danger" @click="removePreset(p)">✕</button>
        </div>
      </div>

      <template v-if="hasChartData">
        <div style="height: 340px"><canvas ref="pressureCanvas"></canvas></div>
      </template>
      <p v-else class="muted" style="margin: 8px 0 0">
        Немає даних за обраний період — увімкніть типи подій вище або додайте події в календарі.
      </p>
    </div>

    <!-- Модалка події -->
    <div v-if="modal.open" class="overlay" @click.self="modal.open = false">
      <div class="dialog">
        <h2 style="margin-top: 0">{{ modal.id ? 'Подія' : 'Нова подія' }} · {{ form.date }}</h2>

        <div class="toolbar">
          <select v-model="form.type" :disabled="!!modal.id">
            <option v-for="t in HEALTH_EVENT_TYPES" :key="t.value" :value="t.value">
              {{ t.icon }} {{ t.label }}
            </option>
          </select>
          <input v-model="form.time" type="time" />
        </div>

        <div v-if="formType?.structured" class="toolbar">
          <input
            v-model.number="form.systolic"
            type="number"
            placeholder="САТ (верхній)"
            style="width: 130px"
          />
          <input
            v-model.number="form.diastolic"
            type="number"
            placeholder="ДАТ (нижній)"
            style="width: 130px"
          />
          <input
            v-model.number="form.pulse"
            type="number"
            placeholder="Пульс"
            style="width: 100px"
          />
        </div>

        <div v-if="formType?.kg" class="toolbar">
          <input
            v-model.number="form.kg"
            type="number"
            step="0.1"
            min="20"
            max="400"
            placeholder="Вага, кг"
            style="width: 130px"
          />
        </div>

        <div v-if="formType?.severity" class="toolbar">
          <label class="muted">Тяжкість:</label>
          <select v-model.number="form.severity">
            <option :value="null">—</option>
            <option v-for="n in 5" :key="n" :value="n">{{ n }} / 5</option>
          </select>
        </div>

        <div v-if="formType?.custom" class="toolbar">
          <input v-model="form.title" placeholder="Назва події" style="flex: 1" />
        </div>

        <textarea
          v-model="form.note"
          rows="3"
          placeholder="Опис (необов'язково)"
          style="width: 100%; margin-bottom: 12px"
        ></textarea>

        <p v-if="error" class="error">{{ error }}</p>

        <div class="toolbar" style="margin-bottom: 0">
          <button class="primary" @click="saveEvent">Зберегти</button>
          <button @click="modal.open = false">Закрити</button>
          <button v-if="modal.id" class="danger" style="margin-left: auto" @click="removeEvent">
            Видалити
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 18, 25, 0.45);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 60px 16px;
  z-index: 50;
}
.dialog {
  background: var(--card, #fff);
  border-radius: 12px;
  padding: 20px;
  width: 520px;
  max-width: 100%;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
}
.cfg-panel {
  border: 1px solid var(--border, #e3e6ea);
  border-radius: 8px;
  padding: 10px 12px;
  margin-bottom: 10px;
}
.cfg-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 6px;
}
.cfg-name {
  width: 190px;
  font-size: 13.5px;
}
.cfg-row input[type='color'] {
  width: 34px;
  height: 24px;
  padding: 0;
  border: 1px solid var(--border, #e3e6ea);
  border-radius: 4px;
}
</style>
