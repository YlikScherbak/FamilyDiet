import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { expect } from '@playwright/test'

// Тексти беремо з тих самих словників, що й застосунок — селектори не ламаються при правці перекладу.
const locales = {
  uk: JSON.parse(readFileSync(fileURLToPath(new URL('../frontend/src/locales/uk.json', import.meta.url)), 'utf-8')),
  en: JSON.parse(readFileSync(fileURLToPath(new URL('../frontend/src/locales/en.json', import.meta.url)), 'utf-8')),
}

/** t('common.save') → 'Зберегти'; параметри {name} підставляються. */
export function t(key, params = {}, locale = 'uk') {
  const value = key.split('.').reduce((acc, part) => acc?.[part], locales[locale])
  if (typeof value !== 'string') throw new Error(`i18n key not found: ${key}`)
  return value.replace(/\{(\w+)\}/g, (_, name) => String(params[name] ?? `{${name}}`))
}

export function isoDate(date) {
  return date.toISOString().slice(0, 10)
}

/** Понеділок і неділя поточного тижня (демо-меню сіється саме на нього). */
export function currentWeek() {
  const today = new Date()
  const monday = new Date(today)
  monday.setDate(today.getDate() - ((today.getDay() + 6) % 7))
  const sunday = new Date(monday)
  sunday.setDate(monday.getDate() + 6)
  return { monday: isoDate(monday), sunday: isoDate(sunday), today: isoDate(today) }
}

/** Члени сім'ї з API — тести не хардкодять імена з фікстур. */
export async function members(request) {
  const res = await request.get('/api/family-members')
  expect(res.ok()).toBeTruthy()
  return res.json()
}

/** Відкриває сторінку і чекає, поки SPA підтягне довідники (шапка з навігацією видима). */
export async function open(page, path) {
  await page.goto(path)
  await expect(page.locator('nav')).toBeVisible()
}
