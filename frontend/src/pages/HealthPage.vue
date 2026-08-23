<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import ukLocale from '@fullcalendar/core/locales/uk'
import { Chart } from 'chart.js/auto'
import annotationPlugin from 'chartjs-plugin-annotation'
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

const pressureEvents = computed(() =>
  events.value.filter(
    (e) =>
      e.type === 'pressure' &&
      (!range.from || e.date >= range.from) &&
      (!range.to || e.date <= range.to),
  ),
)

const pressureCanvas = ref(null)
const pulseCanvas = ref(null)
let pressureChart = null
let pulseChart = null

const chartLabel = (e) =>
  `${e.date.slice(8, 10)}.${e.date.slice(5, 7)}${e.time ? ' ' + e.time : ''}`

function baseOptions(annotations) {
  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { labels: { usePointStyle: true, boxHeight: 6 } },
      annotation: { annotations },
    },
    scales: { x: { ticks: { maxRotation: 60, autoSkip: true } } },
  }
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
  const data = pressureEvents.value
  const labels = data.map(chartLabel)

  pressureChart?.destroy()
  pulseChart?.destroy()
  pressureChart = null
  pulseChart = null
  if (data.length === 0 || !pressureCanvas.value || !pulseCanvas.value) return

  pressureChart = new Chart(pressureCanvas.value, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Систолічний (САТ)',
          data: data.map((e) => e.payload.systolic),
          borderColor: '#b91c1c',
          backgroundColor: '#b91c1c',
          tension: 0.25,
        },
        {
          label: 'Діастолічний (ДАТ)',
          data: data.map((e) => e.payload.diastolic),
          borderColor: '#1d4ed8',
          backgroundColor: '#1d4ed8',
          tension: 0.25,
        },
      ],
    },
    options: baseOptions({
      sysNorm: normLine(135, 'норма САТ 135', '#b91c1c'),
      diaNorm: normLine(85, 'норма ДАТ 85', '#1d4ed8'),
    }),
  })

  pulseChart = new Chart(pulseCanvas.value, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Пульс, уд./хв',
          data: data.map((e) => e.payload.pulse),
          borderColor: '#2f6b4f',
          backgroundColor: '#2f6b4f',
          tension: 0.25,
        },
      ],
    },
    options: baseOptions({}),
  })
}

watch(pressureEvents, () => nextTick(renderCharts))
onBeforeUnmount(() => {
  pressureChart?.destroy()
  pulseChart?.destroy()
})

watch(memberId, load)

onMounted(async () => {
  await app.loadMembers()
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
        <strong>Тиск і пульс</strong>
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

      <template v-if="pressureEvents.length">
        <div style="height: 300px"><canvas ref="pressureCanvas"></canvas></div>
        <div style="height: 200px; margin-top: 12px"><canvas ref="pulseCanvas"></canvas></div>
      </template>
      <p v-else class="muted" style="margin: 8px 0 0">
        Немає замірів тиску за обраний період. Додайте подію «Тиск і пульс» у календарі.
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
</style>
