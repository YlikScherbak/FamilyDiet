<script setup>
import { computed, onMounted, ref } from 'vue'
import { api, MEAL_SLOTS, unitLabel } from '../api'
import { useAppStore } from '../stores/app'
import DayEditor from '../components/DayEditor.vue'

const app = useAppStore()
const entries = ref([])
const summaries = ref([])
const weekStart = ref(mondayOf(new Date()))
const editor = ref(null) // { date, member }

const DAY_LABELS = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Нд']

function mondayOf(date) {
  const d = new Date(date)
  d.setHours(0, 0, 0, 0)
  d.setDate(d.getDate() - ((d.getDay() + 6) % 7))
  return d
}

function fmt(date) {
  const p = (n) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${p(date.getMonth() + 1)}-${p(date.getDate())}`
}

const days = computed(() =>
  Array.from({ length: 7 }, (_, i) => {
    const d = new Date(weekStart.value)
    d.setDate(d.getDate() + i)
    return {
      date: fmt(d),
      label: `${DAY_LABELS[i]} ${d.getDate()}.${String(d.getMonth() + 1).padStart(2, '0')}`,
    }
  }),
)

const today = fmt(new Date())

async function load() {
  const data = await api.get(`/meal-plan?from=${days.value[0].date}&to=${days.value[6].date}`)
  entries.value = data.entries
  summaries.value = data.summaries
}

function shiftWeek(offset) {
  const d = new Date(weekStart.value)
  d.setDate(d.getDate() + offset * 7)
  weekStart.value = d
  load()
}

function goToday() {
  weekStart.value = mondayOf(new Date())
  load()
}

const cellEntries = (date, memberId, slot) =>
  entries.value.filter((e) => e.date === date && e.familyMemberId === memberId && e.slot === slot)

const summaryFor = (date, memberId) =>
  summaries.value.find((s) => s.date === date && s.familyMemberId === memberId)

const entryTitle = (e) =>
  e.type === 'dish'
    ? e.dish.name
    : `${e.ingredient.name} — ${e.amount} ${unitLabel(e.ingredient.unit)}`

const entryShort = (e) =>
  e.type === 'dish'
    ? e.dish.name
    : `${e.ingredient.name} ${e.amount}${unitLabel(e.ingredient.unit)}`

async function removeEntry(entry) {
  await api.del(`/meal-plan/entries/${entry.id}`)
  await load()
}

function openEditor(date, member) {
  editor.value = { date, member }
}

async function copyWeek() {
  const target = new Date(weekStart.value)
  target.setDate(target.getDate() + 7)
  if (!confirm('Скопіювати всі страви цього тижня на наступний?')) return
  await api.post('/meal-plan/copy', {
    sourceFrom: days.value[0].date,
    sourceTo: days.value[6].date,
    targetFrom: fmt(target),
  })
  shiftWeek(1)
}

onMounted(async () => {
  await app.loadMembers()
  await load()
})
</script>

<template>
  <div>
    <h1>Календар меню</h1>
    <div class="toolbar">
      <button @click="shiftWeek(-1)">‹ Попередній</button>
      <button @click="goToday">Сьогодні</button>
      <button @click="shiftWeek(1)">Наступний ›</button>
      <strong style="margin-left: 8px">{{ days[0].date }} — {{ days[6].date }}</strong>
      <span class="spacer" />
      <span class="muted">Клік по клітинці — редактор дня</span>
      <button @click="copyWeek">⧉ Копіювати тиждень →</button>
    </div>

    <div style="overflow-x: auto">
      <table class="data calendar">
        <thead>
          <tr>
            <th style="width: 90px"></th>
            <th style="width: 60px"></th>
            <th v-for="day in days" :key="day.date" :class="{ today: day.date === today }">
              {{ day.label }}
            </th>
          </tr>
        </thead>
        <tbody>
          <template v-for="slot in MEAL_SLOTS" :key="slot.value">
            <tr v-for="(member, mi) in app.members" :key="member.id">
              <th v-if="mi === 0" :rowspan="app.members.length" class="slot-label">
                {{ slot.label }}
              </th>
              <td class="muted member-label">{{ member.name }}</td>
              <td
                v-for="day in days"
                :key="day.date"
                class="cell"
                :class="{ today: day.date === today }"
                @click.self="openEditor(day.date, member)"
              >
                <div
                  v-for="entry in cellEntries(day.date, member.id, slot.value)"
                  :key="entry.id"
                  class="entry"
                  :title="entryTitle(entry)"
                >
                  <span class="entry-name" @click="openEditor(day.date, member)">{{
                    entryShort(entry)
                  }}</span>
                  <span v-if="entry.nutrition" class="muted entry-kcal"
                    >{{ Math.round(entry.nutrition.kcal) }}&nbsp;ккал</span
                  >
                  <button class="small danger" @click.stop="removeEntry(entry)">✕</button>
                </div>
                <button class="small add" @click="openEditor(day.date, member)">✎</button>
              </td>
            </tr>
          </template>
          <tr v-for="member in app.members" :key="'sum' + member.id" class="summary-row">
            <th
              v-if="member.id === app.members[0]?.id"
              :rowspan="app.members.length"
              class="slot-label"
            >
              Разом
            </th>
            <td class="muted member-label">{{ member.name }}</td>
            <td v-for="day in days" :key="day.date" :class="{ today: day.date === today }">
              <template v-if="summaryFor(day.date, member.id)">
                <strong :class="{ over: summaryFor(day.date, member.id).kcal > member.kcalTarget }">
                  {{ Math.round(summaryFor(day.date, member.id).kcal) }}
                </strong>
                <span class="muted"> / {{ member.kcalTarget }}</span>
                <div class="muted macro">
                  Б {{ Math.round(summaryFor(day.date, member.id).protein) }} · Ж
                  {{ Math.round(summaryFor(day.date, member.id).fat) }} · В
                  {{ Math.round(summaryFor(day.date, member.id).carbs) }}
                </div>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <DayEditor
      v-if="editor"
      :date="editor.date"
      :member="editor.member"
      @changed="load"
      @close="editor = null"
    />
  </div>
</template>

<style scoped>
.calendar th.today,
.calendar td.today {
  background: #f2f8f4;
}
.calendar .slot-label {
  vertical-align: top;
  background: #fafbfc;
  font-size: 13px;
}
.calendar .member-label {
  font-size: 12.5px;
  white-space: nowrap;
}
.calendar .cell {
  min-width: 130px;
  vertical-align: top;
  cursor: pointer;
}
.entry {
  margin-bottom: 4px;
  font-size: 13px;
  line-height: 1.3;
}
.entry-name {
  color: var(--primary);
}
.entry-kcal {
  margin-left: 4px;
  white-space: nowrap;
}
.entry-name:hover {
  text-decoration: underline;
}
.entry button {
  visibility: hidden;
  margin-left: 2px;
  padding: 0 4px;
  border: none;
}
.entry:hover button {
  visibility: visible;
}
.add {
  visibility: hidden;
  border-style: dashed;
  color: var(--muted);
}
.cell:hover .add {
  visibility: visible;
}
.summary-row td {
  font-size: 13px;
}
.summary-row .over {
  color: var(--danger);
}
.macro {
  font-size: 11.5px;
}
</style>
