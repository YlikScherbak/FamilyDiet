import { test, expect } from '@playwright/test'
import { currentWeek, members, open, t } from '../helpers.js'

// Страви: створити з порціями на двох, побачити ккал у списку, видалити; запланована страва не видаляється (409).
test('страва створюється з порціями на кожного і видаляється; запланована — захищена', async ({
  page,
  request,
}) => {
  const family = await members(request)
  const name = `E2E Салат ${Date.now()}`

  await open(page, '/dishes/new')
  await expect(page.getByRole('heading', { name: t('dishForm.newTitle') })).toBeVisible()

  await page.getByPlaceholder(t('dishForm.namePlaceholder')).fill(name)
  await page.locator('main select, .page select, form select').first().selectOption('lunch')

  // Порція кожному члену сім'ї: гречка з різною грамівкою
  for (const [i, member] of family.entries()) {
    const card = page.locator('.card', { hasText: t('dishForm.portionFor', { name: member.name }) })
    await card.getByPlaceholder(t('dishForm.addIngredient')).fill('греч')
    await card.locator('.dropdown .option').first().click()
    const amount = card.locator('tbody input[type=number]').first()
    await amount.fill(String(150 + i * 50))
    await expect(card.locator('.badge').first()).toContainText(/^[1-9]\d* /)
  }

  await page.getByRole('button', { name: t('common.save') }).click()
  await expect(page).toHaveURL(/\/dishes$/)

  // У списку: рядок зі стравою, ккал для кожного > 0 і різні (порції різні)
  await page.getByPlaceholder(t('dishes.searchPlaceholder')).fill(name)
  const row = page.locator('tbody tr', { hasText: name })
  await expect(row).toHaveCount(1)
  const kcals = []
  for (let i = 0; i < family.length; i++) {
    kcals.push(Number(await row.locator('td').nth(3 + i).textContent()))
  }
  expect(Math.min(...kcals)).toBeGreaterThan(0)
  expect(new Set(kcals).size).toBe(family.length)

  // Видалення власної страви — confirm приймаємо
  page.once('dialog', (d) => d.accept())
  await row.getByRole('button', { name: '✕' }).click()
  await expect(row).toHaveCount(0)

  // Страва, що точно запланована в меню (беремо з API), → 409, повідомлення показується alert-ом
  const { monday, sunday } = currentWeek()
  const plan = await (await request.get(`/api/meal-plan?from=${monday}&to=${sunday}`)).json()
  const plannedId = plan.entries.find((e) => e.dish)?.dish.id
  expect(plannedId).toBeTruthy()
  const plannedName = (await (await request.get(`/api/dishes/${plannedId}`)).json()).name
  await page.getByPlaceholder(t('dishes.searchPlaceholder')).fill(plannedName)
  const planned = page.locator('tbody tr', { hasText: plannedName }).first()
  const messages = []
  page.on('dialog', async (d) => {
    if (d.type() === 'confirm') await d.accept()
    else {
      messages.push(d.message())
      await d.dismiss()
    }
  })
  await planned.getByRole('button', { name: '✕' }).click()
  await expect.poll(() => messages.length).toBe(1)
  expect(messages[0]).toMatch(/заплановано|запланована/i)
  await expect(page.locator('tbody tr', { hasText: plannedName })).toHaveCount(1)
})
