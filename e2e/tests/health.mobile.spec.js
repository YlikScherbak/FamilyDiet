import { test, expect } from '@playwright/test'
import { open, t } from '../helpers.js'

// Мобільний вигляд: список замість сітки, FAB «＋» відкриває запис на сьогодні.
test('на телефоні журнал відкривається списком, FAB додає подію на сьогодні', async ({ page }) => {
  await open(page, '/health')

  await expect(page.locator('.fc-list')).toBeVisible()
  await expect(page.locator('.fc-daygrid')).toHaveCount(0)

  await page.locator('button.fab').click()
  const dialog = page.locator('.dialog')
  await expect(dialog).toBeVisible()
  await expect(dialog.getByRole('heading')).toContainText(t('health.newEvent'))

  await dialog.locator('select').first().selectOption('note')
  await dialog.getByPlaceholder(t('health.notePlaceholder')).fill('e2e mobile note')
  await dialog.getByRole('button', { name: t('common.save') }).click()
  await expect(dialog).toBeHidden()

  const created = page.locator('.fc-list-event', { hasText: t('healthTypes.note') }).first()
  await expect(created).toBeVisible()

  // Прибираємо за собою
  await created.click()
  await dialog.getByRole('button', { name: t('common.delete') }).click()
  await expect(dialog).toBeHidden()
})
