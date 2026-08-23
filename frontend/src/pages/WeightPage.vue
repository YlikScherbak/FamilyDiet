<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../api'
import { useAppStore } from '../stores/app'

const app = useAppStore()
const entries = ref([])
const weekly = ref([])
const error = ref('')
const hover = ref(null) // { memberId, index }

const todayStr = new Date().toISOString().slice(0, 10)
const form = reactive({ familyMemberId: null, date: todayStr, weightKg: null })

async function load() {
  entries.value = await api.get('/weight-entries')
  weekly.value = await api.get('/weight-entries/weekly')
}

async function save() {
  error.value = ''
  try {
    await api.post('/weight-entries', { ...form })
    form.weightKg = null
    await load()
  } catch (e) {
    error.value = e.message
  }
}

async function remove(entry) {
  await api.del(`/weight-entries/${entry.id}`)
  await load()
}

const entriesOf = (memberId) =>
  entries.value.filter((e) => e.familyMemberId === memberId).slice().reverse()

// --- Графік тижневих середніх: одна панель на людину, одна серія на панель ---
const W = 560
const H = 200
const PAD = { top: 24, right: 16, bottom: 26, left: 44 }

function chartFor(memberId) {
  const points = weekly.value
    .filter((w) => w.familyMemberId === memberId)
    .map((w) => ({ week: w.weekStart, value: w.avgWeight, n: w.measurements }))
  if (points.length === 0) return null

  const values = points.map((p) => p.value)
  const min = Math.min(...values)
  const max = Math.max(...values)
  const span = Math.max(max - min, 1)
  const lo = min - span * 0.15
  const hi = max + span * 0.15

  const x = (i) => PAD.left + (points.length === 1
    ? (W - PAD.left - PAD.right) / 2
    : (i * (W - PAD.left - PAD.right)) / (points.length - 1))
  const y = (v) => PAD.top + ((hi - v) / (hi - lo)) * (H - PAD.top - PAD.bottom)

  const ticks = [lo + (hi - lo) * 0.1, (lo + hi) / 2, hi - (hi - lo) * 0.1]
    .map((v) => ({ value: Math.round(v * 10) / 10, y: y(v) }))

  return {
    points: points.map((p, i) => ({ ...p, x: x(i), y: y(p.value) })),
    path: points.map((p, i) => `${i === 0 ? 'M' : 'L'}${x(i)},${y(p.value)}`).join(' '),
    ticks,
  }
}

const charts = computed(() =>
  app.members
    .map((m) => ({ member: m, chart: chartFor(m.id) }))
    .filter((c) => c.chart !== null)
)

const labelIndexes = (points) => new Set([0, points.length - 1])

onMounted(async () => {
  await app.loadMembers()
  form.familyMemberId = app.members[0]?.id ?? null
  await load()
})
</script>

<template>
  <div>
    <h1>Вага</h1>

    <div class="card" style="margin-bottom: 16px">
      <div class="toolbar" style="margin-bottom: 0">
        <select v-model.number="form.familyMemberId">
          <option v-for="m in app.members" :key="m.id" :value="m.id">{{ m.name }}</option>
        </select>
        <input v-model="form.date" type="date" />
        <input v-model.number="form.weightKg" type="number" step="0.1" min="20" max="400" placeholder="Вага, кг" style="width: 120px" />
        <button class="primary" :disabled="!form.weightKg" @click="save">Зберегти</button>
        <span class="muted">Той самий день — запис оновиться. План радить зважуватись 3 рази на тиждень зранку.</span>
      </div>
      <p v-if="error" class="error" style="margin-bottom: 0">{{ error }}</p>
    </div>

    <div v-if="charts.length" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(560px, 1fr)); gap: 16px; margin-bottom: 16px">
      <div v-for="{ member, chart } in charts" :key="member.id" class="card">
        <strong>{{ member.name }} — середня вага за тиждень, кг</strong>
        <svg :viewBox="`0 0 ${W} ${H}`" style="width: 100%; margin-top: 8px" @mouseleave="hover = null">
          <line
            v-for="t in chart.ticks"
            :key="t.y"
            :x1="PAD.left" :x2="W - PAD.right" :y1="t.y" :y2="t.y"
            stroke="#e3e6ea" stroke-width="1"
          />
          <text
            v-for="t in chart.ticks"
            :key="'l' + t.y"
            :x="PAD.left - 6" :y="t.y + 4"
            text-anchor="end" font-size="11" fill="#6b7280"
          >{{ t.value }}</text>

          <path :d="chart.path" fill="none" stroke="#2f6b4f" stroke-width="2" stroke-linejoin="round" />

          <g v-for="(p, i) in chart.points" :key="p.week">
            <circle
              :cx="p.x" :cy="p.y" r="10" fill="transparent"
              @mouseenter="hover = { memberId: member.id, index: i }"
            />
            <circle :cx="p.x" :cy="p.y" r="3.5" fill="#2f6b4f" stroke="#fff" stroke-width="2" />
            <text
              v-if="labelIndexes(chart.points).has(i)"
              :x="p.x" :y="p.y - 10" text-anchor="middle" font-size="11.5" font-weight="600" fill="#1f2430"
            >{{ p.value }}</text>
            <text :x="p.x" :y="H - 8" text-anchor="middle" font-size="10.5" fill="#6b7280">
              {{ p.week.slice(5) }}
            </text>
            <g v-if="hover && hover.memberId === member.id && hover.index === i">
              <rect :x="p.x - 52" :y="p.y - 44" width="104" height="26" rx="6" fill="#1f2430" />
              <text :x="p.x" :y="p.y - 27" text-anchor="middle" font-size="11" fill="#fff">
                {{ p.value }} кг · {{ p.n }} вим.
              </text>
            </g>
          </g>
        </svg>
      </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 16px">
      <div v-for="member in app.members" :key="member.id" class="card">
        <strong>{{ member.name }} — усі вимірювання</strong>
        <table class="data" style="margin-top: 8px">
          <thead><tr><th>Дата</th><th>Вага, кг</th><th style="width: 40px"></th></tr></thead>
          <tbody>
            <tr v-for="entry in entriesOf(member.id)" :key="entry.id">
              <td>{{ entry.date }}</td>
              <td>{{ entry.weightKg }}</td>
              <td><button class="small danger" @click="remove(entry)">✕</button></td>
            </tr>
            <tr v-if="entriesOf(member.id).length === 0">
              <td colspan="3" class="muted">Поки немає записів</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
