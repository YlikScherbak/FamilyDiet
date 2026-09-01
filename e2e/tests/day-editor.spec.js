import { test, expect } from '@playwright/test'
import { currentWeek, members, open, t } from '../helpers.js'

// Конструктор дня: страва + продукт з грамівкою, живий підсумок ккал, збереження одним запитом.
test('день збирається зі страви і продукту, підсумок рахується, запис з’являється в сітці', async ({
  page,
  request,
}) => {
  const [member] = await members(request)
  const { monday } = currentWeek()

  await open(page, '/')
  await expect(page.getByRole('heading', { name: t('menu.title') })).toBeVisible()

  // Клітинка: рядок «сніданок / перший член сім'ї», перший день тижня (понеділок)
  const breakfastRow = page.locator('tr', { has: page.locator('td.member-label', { hasText: member.name }) }).first()
  const cell = breakfastRow.locator('td.cell').first()
  await cell.hover() // кнопка ✎ з'являється при наведенні
  await cell.getByRole('button', { name: '✎' }).click()

  const editor = page.locator('.modal.editor')
  await expect(editor.getByRole('heading', { name: `${member.name} · ${monday}` })).toBeVisible()

  // Кожен сценарій починає з чистого сніданку — прибираємо, що насіяло демо.
  // Увесь блок у toPass(): на повільному CI items домальовуються пізніше за відкриття модалки.
  const breakfast = editor.locator('section.slot').first()
  const existing = breakfast.locator('.item')
  await expect(async () => {
    while ((await existing.count()) > 0) {
      await existing.first().hover() // ✕ видима лише при наведенні
      await existing.first().getByRole('button', { name: '✕' }).click()
    }
    await expect(breakfast.locator('.slot-head .muted')).toHaveText(`0 ${t('common.kcal')}`, {
      timeout: 2000,
    })
  }).toPass({ timeout: 20_000 })

  // 1) страва з пікера
  await breakfast.getByRole('button', { name: t('dayEditor.addDish') }).click()
  const picker = page.locator('.modal', { has: page.getByRole('heading', { name: t('dishes.pick') }) })
  const firstDish = picker.locator('tbody tr').first()
  const dishName = (await firstDish.locator('td').nth(1).textContent()).trim()
  await firstDish.click()
  await expect(breakfast.locator('.item-name', { hasText: dishName })).toBeVisible()

  // 2) продукт з автокомплітом і грамівкою
  await breakfast.getByPlaceholder(t('ingredients.autocompletePlaceholder')).fill('греч')
  await breakfast.locator('.dropdown .option').first().click()
  const amount = breakfast.locator('.pending input[type=number]')
  await amount.fill('150')
  await breakfast.locator('.pending').getByRole('button', { name: t('common.ok') }).click()
  await expect(breakfast.locator('.item')).toHaveCount(2)

  // Живий підсумок: ккал сніданку > 0 і збігається з підсумком дня (інші слоти для перевірки не чіпаємо)
  const slotKcal = Number((await breakfast.locator('.slot-head .muted').textContent()).replace(/\D/g, ''))
  expect(slotKcal).toBeGreaterThan(0)
  const totals = editor.locator('aside.totals')
  await expect(totals.getByText(t('dayEditor.dayTotal'))).toBeVisible()
  const dayKcal = Number(await totals.locator('.target').first().locator('strong').textContent())
  expect(dayKcal).toBeGreaterThanOrEqual(slotKcal)

  // Зберегти — модалка закривається, у клітинці обидва записи
  await editor.getByRole('button', { name: t('common.save') }).first().click()
  await expect(editor).toBeHidden()
  await expect(cell.locator('.entry')).toHaveCount(2)
  await expect(cell.locator('.entry-name').first()).toContainText(dishName.slice(0, 10))
  await expect(cell.locator('.entry-kcal').first()).toContainText(t('common.kcal'))
})
