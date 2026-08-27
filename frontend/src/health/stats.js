// Підсумкова статистика для звіту: рахується з тих самих подій, що й графік.
import { HOME_NORM } from './chart'

const avg = (xs) =>
  xs.length ? Math.round((xs.reduce((s, x) => s + x, 0) / xs.length) * 10) / 10 : null
const isMorning = (e) => e.time != null && e.time < '12:00'
const isEvening = (e) => e.time != null && e.time >= '12:00'

/** Тиск: середні загалом / зранку / ввечері, максимум, частка замірів вище домашньої норми. */
export function pressureStats(events) {
  const list = events.filter((e) => e.type === 'pressure')
  if (list.length === 0) return null
  const triple = (xs) => ({
    sbp: avg(xs.map((e) => e.payload.systolic)),
    dbp: avg(xs.map((e) => e.payload.diastolic)),
    pulse: avg(xs.map((e) => e.payload.pulse)),
  })
  const maxSbp = list.reduce((m, e) => (e.payload.systolic > m.payload.systolic ? e : m), list[0])
  const above = list.filter(
    (e) => e.payload.systolic >= HOME_NORM.sbp || e.payload.diastolic >= HOME_NORM.dbp,
  )
  const morning = list.filter(isMorning)
  const evening = list.filter(isEvening)
  return {
    count: list.length,
    all: triple(list),
    morning: triple(morning),
    evening: triple(evening),
    morningCount: morning.length,
    eveningCount: evening.length,
    max: {
      sbp: maxSbp.payload.systolic,
      dbp: maxSbp.payload.diastolic,
      date: maxSbp.date,
      time: maxSbp.time,
    },
    abovePct: Math.round((above.length / list.length) * 100),
  }
}

/** Вага: перший/останній замір і зміна за період. */
export function weightStats(events) {
  const list = events.filter((e) => e.type === 'weight' && e.payload.kg != null)
  if (list.length === 0) return null
  const kgs = list.map((e) => e.payload.kg)
  const first = list[0].payload.kg
  const last = list[list.length - 1].payload.kg
  return {
    count: list.length,
    first,
    last,
    delta: Math.round((last - first) * 10) / 10,
    min: Math.min(...kgs),
    max: Math.max(...kgs),
    avg: avg(kgs),
  }
}

/** Типи з тяжкістю (біль, мігрень, симптом): кількість, середня і максимальна тяжкість. */
export function severityStats(events, type) {
  const list = events.filter((e) => e.type === type)
  if (list.length === 0) return null
  const sev = list.map((e) => e.payload.severity).filter((s) => s != null)
  return {
    count: list.length,
    avgSeverity: avg(sev),
    maxSeverity: sev.length ? Math.max(...sev) : null,
  }
}

/** Прості типи (ліки, нотатки, власні): лише кількість. */
export const countOf = (events, type) => events.filter((e) => e.type === type).length
