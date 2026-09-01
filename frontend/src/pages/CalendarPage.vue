<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api, MEAL_SLOTS } from '../api'
import { useAppStore } from '../stores/app'
import DayEditor from '../components/DayEditor.vue'

const { t, locale } = useI18n()
const app = useAppStore()
const entries = ref([])
const summaries = ref([])
const weekStart = ref(mondayOf(new Date()))
const editor = ref(null) // { date, member }

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
    const weekday = d.toLocaleDateString(locale.value === 'uk' ? 'uk-UA' : 'en-GB', {
      weekday: 'short',
    })
    return {
      date: fmt(d),
      label: `${weekday} ${d.getDate()}.${String(d.getMonth() + 1).padStart(2, '0')}`,
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
    : `${e.ingredient.name} — ${e.amount} ${t(`units.${e.ingredient.unit}`)}`

const entryShort = (e) =>
  e.type === 'dish'
    ? e.dish.name
    : `${e.ingredient.name} ${e.amount}${t(`units.${e.ingredient.unit}`)}`

async function removeEntry(entry) {
  await api.del(`/meal-plan/entries/${entry.id}`)
  await load()
}

function openEditor(date, member) {
  editor.value = { date, member }
}

// --- Шаблони днів: знімок дати і застосування на дату ---------------------
const templates = ref([])
const selectedTemplate = ref('')
const templateDate = ref('')

async function loadTemplates() {
  templates.value = await api.get('/day-templates')
}

async function saveDayAsTemplate() {
  const name = (window.prompt(t('menu.templateNamePrompt', { date: templateDate.value })) ?? '').trim()
  if (!name) return
  try {
    const created = await api.post('/day-templates', { name, date: templateDate.value })
    await loadTemplates()
    selectedTemplate.value = String(created.id)
  } catch (e) {
    alert(e.message)
  }
}

async function applyTemplate() {
  const tpl = templates.value.find((x) => String(x.id) === selectedTemplate.value)
  if (!tpl) return
  if (!confirm(t('menu.confirmApplyTemplate', { name: tpl.name, date: templateDate.value }))) return
  try {
    await api.post(`/day-templates/${tpl.id}/apply`, { date: templateDate.value })
    await load()
  } catch (e) {
    alert(e.message)
  }
}

async function deleteTemplate() {
  const tpl = templates.value.find((x) => String(x.id) === selectedTemplate.value)
  if (!tpl) return
  if (!confirm(t('menu.confirmDeleteTemplate', { name: tpl.name }))) return
  await api.del(`/day-templates/${tpl.id}`)
  selectedTemplate.value = ''
  await loadTemplates()
}

async function copyWeek() {
  const target = new Date(weekStart.value)
  target.setDate(target.getDate() + 7)
  if (!confirm(t('menu.confirmCopy'))) return
  await api.post('/meal-plan/copy', {
    sourceFrom: days.value[0].date,
    sourceTo: days.value[6].date,
    targetFrom: fmt(target),
  })
  shiftWeek(1)
}

onMounted(async () => {
  templateDate.value = today
  loadTemplates()
  await app.loadMembers()
  await load()
})
</script>

<template>
  <div>
    <h1>{{ $t('menu.title') }}</h1>
    <div class="toolbar">
      <button @click="shiftWeek(-1)">{{ $t('menu.prev') }}</button>
      <button @click="goToday">{{ $t('menu.today') }}</button>
      <button @click="shiftWeek(1)">{{ $t('menu.next') }}</button>
      <strong style="margin-left: 8px">{{ days[0].date }} — {{ days[6].date }}</strong>
      <span class="spacer" />
      <span class="muted">{{ $t('menu.clickHint') }}</span>
      <button @click="copyWeek">{{ $t('menu.copyWeek') }}</button>
    </div>

    <div class="toolbar templates-bar">
      <span class="muted">{{ $t('menu.templates') }}</span>
      <select v-model="selectedTemplate" class="template-select">
        <option value="">{{ $t('menu.templatePlaceholder') }}</option>
        <option v-for="tpl in templates" :key="tpl.id" :value="String(tpl.id)">
          {{ tpl.name }} ({{ $t('common.count', { n: tpl.items }) }})
        </option>
      </select>
      <input v-model="templateDate" type="date" />
      <button class="small" :disabled="!selectedTemplate || !templateDate" @click="applyTemplate">
        {{ $t('menu.applyTemplate') }}
      </button>
      <button class="small danger" :disabled="!selectedTemplate" @click="deleteTemplate">✕</button>
      <span class="spacer" />
      <button class="small" :disabled="!templateDate" @click="saveDayAsTemplate">
        {{ $t('menu.saveTemplate') }}
      </button>
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
          <template v-for="slot in MEAL_SLOTS" :key="slot">
            <tr v-for="(member, mi) in app.members" :key="member.id">
              <th v-if="mi === 0" :rowspan="app.members.length" class="slot-label">
                {{ $t(`slots.${slot}`) }}
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
                  v-for="entry in cellEntries(day.date, member.id, slot)"
                  :key="entry.id"
                  class="entry"
                  :title="entryTitle(entry)"
                >
                  <span class="entry-name" @click="openEditor(day.date, member)">{{
                    entryShort(entry)
                  }}</span>
                  <span v-if="entry.nutrition" class="muted entry-kcal"
                    >{{ Math.round(entry.nutrition.kcal) }}&nbsp;{{ $t('common.kcal') }}</span
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
              {{ $t('menu.total') }}
            </th>
            <td class="muted member-label">{{ member.name }}</td>
            <td v-for="day in days" :key="day.date" :class="{ today: day.date === today }">
              <template v-if="summaryFor(day.date, member.id)">
                <strong :class="{ over: summaryFor(day.date, member.id).kcal > member.kcalTarget }">
                  {{ Math.round(summaryFor(day.date, member.id).kcal) }}
                </strong>
                <span class="muted"> / {{ member.kcalTarget }}</span>
                <div class="muted macro">
                  {{ $t('nutrients.p') }}
                  {{ Math.round(summaryFor(day.date, member.id).protein) }} ·
                  {{ $t('nutrients.f') }} {{ Math.round(summaryFor(day.date, member.id).fat) }} ·
                  {{ $t('nutrients.c') }} {{ Math.round(summaryFor(day.date, member.id).carbs) }}
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
/* Вертикальні розділювачі між днями, щоб колонки не зливались */
.calendar td.cell,
.calendar thead th:not(:first-child) {
  border-left: 1px solid var(--border);
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
