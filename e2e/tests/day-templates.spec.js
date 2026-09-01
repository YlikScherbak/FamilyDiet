import { test, expect } from '@playwright/test'
import { currentWeek, open, t } from '../helpers.js'

// Шаблони днів: зберегти понеділок як шаблон, застосувати на неділю (замінює день для всієї сім'ї), видалити.
test('день зберігається як шаблон і застосовується на іншу дату', async ({ page, request }) => {
  const { monday, sunday } = currentWeek()
  const name = `E2E Шаблон ${Date.now()}`

  const entriesOf = async (date) => {
    const res = await request.get(`/api/meal-plan?from=${date}&to=${date}`)
    return (await res.json()).entries
  }
  const mondayEntries = await entriesOf(monday)
  expect(mondayEntries.length).toBeGreaterThan(5) // демо-меню

  await open(page, '/')
  const bar = page.locator('.templates-bar')

  // Зберегти понеділок як шаблон (назва — через window.prompt)
  page.once('dialog', (d) => d.accept(name))
  await bar.locator('input[type=date]').fill(monday)
  await bar.getByRole('button', { name: t('menu.saveTemplate') }).click()
  await expect(bar.locator('select option', { hasText: name })).toHaveCount(1)

  // Застосувати на неділю — записи неділі замінюються записами шаблону
  page.once('dialog', (d) => d.accept())
  await bar.locator('input[type=date]').fill(sunday)
  await bar.getByRole('button', { name: t('menu.applyTemplate') }).click()

  await expect
    .poll(async () => (await entriesOf(sunday)).length)
    .toBe(mondayEntries.length)
  const sundayEntries = await entriesOf(sunday)
  const key = (e) => `${e.familyMemberId}:${e.slot}:${e.dish?.id ?? ''}:${e.ingredient?.id ?? ''}`
  expect(sundayEntries.map(key).sort()).toEqual(mondayEntries.map(key).sort())

  // Видалити шаблон — меню не чіпається
  page.once('dialog', (d) => d.accept())
  await bar.getByRole('button', { name: '✕' }).click()
  await expect(bar.locator('select option', { hasText: name })).toHaveCount(0)
  expect((await entriesOf(sunday)).length).toBe(mondayEntries.length)
})
