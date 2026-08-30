import { test, expect } from '@playwright/test'
import { currentWeek, isoDate, open, t } from '../helpers.js'

// Журнал здоров'я: створити замір тиску кліком по дню, побачити в календарі, відредагувати, видалити.
test('замір тиску додається, редагується і видаляється через календар', async ({ page }) => {
  const { today } = currentWeek()
  // День без демо-подій: завтра, якщо він у тому ж місяці, інакше сьогодні
  const tomorrow = new Date(today)
  tomorrow.setDate(tomorrow.getDate() + 1)
  const date = isoDate(tomorrow).slice(0, 7) === today.slice(0, 7) ? isoDate(tomorrow) : today

  await open(page, '/health')
  await expect(page.getByRole('heading', { name: t('health.title') })).toBeVisible()

  const day = page.locator(`.fc-daygrid-day[data-date="${date}"]`)
  await day.locator('.fc-daygrid-day-frame').click({ position: { x: 10, y: 60 } })

  const dialog = page.locator('.dialog')
  await expect(dialog.getByRole('heading', { name: `${t('health.newEvent')} · ${date}` })).toBeVisible()

  await dialog.locator('select').first().selectOption('pressure')
  await dialog.locator('input[type=time]').fill('08:15')
  await dialog.getByPlaceholder(t('health.systolic')).fill('151')
  await dialog.getByPlaceholder(t('health.diastolic')).fill('93')
  await dialog.getByPlaceholder(t('health.pulse')).fill('71')
  await dialog.getByRole('button', { name: t('common.save') }).click()
  await expect(dialog).toBeHidden()

  const event = day.locator('.fc-event', { hasText: '151/93 · 71' })
  await expect(event).toBeVisible()

  // Редагування: змінити пульс
  await event.click()
  await expect(dialog.getByRole('heading', { name: `${t('health.event')} · ${date}` })).toBeVisible()
  await dialog.getByPlaceholder(t('health.pulse')).fill('69')
  await dialog.getByRole('button', { name: t('common.save') }).click()
  await expect(day.locator('.fc-event', { hasText: '151/93 · 69' })).toBeVisible()

  // Видалення
  await day.locator('.fc-event', { hasText: '151/93 · 69' }).click()
  await dialog.getByRole('button', { name: t('common.delete') }).click()
  await expect(dialog).toBeHidden()
  await expect(day.locator('.fc-event', { hasText: '151/93' })).toHaveCount(0)
})
