import { test, expect } from '@playwright/test'
import { currentWeek, open, t } from '../helpers.js'

// Список закупівель: агрегація з демо-меню поточного тижня, позначка «куплено» переживає перезавантаження.
test('список будується з меню, позначка «куплено» зберігається в БД', async ({ page }) => {
  const { monday, sunday } = currentWeek()
  await open(page, `/shopping?from=${monday}&to=${sunday}`)
  await expect(page.getByRole('heading', { name: t('shopping.title') })).toBeVisible()

  const items = page.locator('.groups li')
  await expect(items.first()).toBeVisible()
  const total = await items.count()
  expect(total).toBeGreaterThan(10)

  const first = items.first()
  const checkbox = first.getByRole('checkbox')
  const wasBought = await checkbox.isChecked()

  await checkbox.click()
  await expect(checkbox).toBeChecked({ checked: !wasBought })
  // debounce збереження в settings
  await page.waitForTimeout(700)

  await page.reload()
  await expect(page.locator('.groups li').first().getByRole('checkbox')).toBeChecked({ checked: !wasBought })

  const bought = (await page.locator('.groups li.bought').count()) + 0
  const progress = t('shopping.progress', { bought, total })
  await expect(page.getByText(progress)).toBeVisible()

  // Повертаємо як було, щоб тест був повторюваним
  await page.locator('.groups li').first().getByRole('checkbox').click()
  await page.waitForTimeout(700)
})
