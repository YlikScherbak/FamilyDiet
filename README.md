# FamilyDiet

Локальний застосунок ведення сімейної дієти на основі 21-денного плану харчування
від нутриціолога (сам PDF-план не публікується; страви з нього розібрані в seed-дані
та фікстури).

**Стек:** Symfony 7.4 (REST API) · Vue 3 + Vite + Pinia · PostgreSQL 16 · Docker Compose.

## Запуск з нуля

```bash
docker compose up -d --build
docker compose exec php composer install
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
docker compose exec php php bin/console app:import-usda-products
docker compose exec php php bin/console app:import-ingredient-translations
```

- Застосунок: http://localhost:5173
- API: http://localhost:8080/api/health
- PostgreSQL: `localhost:5432`, база `familydiet`, користувач/пароль `app`/`app`

Фікстури засівають: 2 членів сім'ї (з цілями ккал/БЖВ), 96 інгредієнтів з КБЖВ
та 91 страву з плану (B01-B21 сніданки, L01-L21 обіди, D01-D21 вечері,
S01-S21 перекуси, N01-N07 додаткові перекуси) з окремими порціями для кожного.
Календар меню порожній — наповнюється вручну.

`app:import-usda-products` довантажує ~7 200 продуктів з
[USDA FoodData Central](https://fdc.nal.usda.gov/) (SR Legacy, CC0;
дамп у `backend/data/usda_products.json`). `app:import-ingredient-translations`
накочує українські назви (`backend/data/usda_names_uk.csv`, ключ — `fdc_id`);
оригінал зберігається у `name_en`, пошук працює обома мовами. Обидві команди
ідемпотентні, базу НЕ очищають (на відміну від `fixtures:load`); переімпорт
продуктів не перезаписує перекладені назви.

Довідник інгредієнтів вантажиться на фронтенд один раз при старті
(`GET /api/ingredients/all` → Pinia store) — автокомпліт шукає локально,
по словах у будь-якому порядку, українською та англійською.

> `doctrine:fixtures:load` очищає базу — план меню, вага та власні страви зникнуть.
> Запускати лише для початкового наповнення.

## Сторінки

- **Календар** — тижнева сітка меню для обох (сніданок/обід/вечеря/перекуси),
  підсумок дня ккал/БЖВ проти особистої цілі, копіювання тижня. Клік по клітинці
  відкриває **конструктор дня**: у кожен прийом додаються страви або окремі
  продукти з ручною грамівкою (гречка 150 г, курка гриль 100 г), справа — живий
  прогрес ккал/Б/Ж/В проти цілей.
- **Страви** — CRUD з порціями на кожного, рецептом, YouTube-відео та
  прапорцем «заготівля»; калорійність рахується з інгредієнтів.
- **Інгредієнти** — CRUD з КБЖВ на 100 г (для штучних — вага 1 шт).
- **Вага** — записи ваги (upsert по даті) + графік середньої за тиждень
  (план радить порівнювати тижневі середні, а не окремі дні).

## API (основне)

| Метод | Шлях | Опис |
|---|---|---|
| GET | `/api/family-members` | члени сім'ї з цілями |
| GET/POST/PUT/DELETE | `/api/ingredients` | CRUD інгредієнтів (`?search=&category=`) |
| GET/POST/PUT/DELETE | `/api/dishes` | CRUD страв з порціями (`?search=&category=`) |
| GET | `/api/meal-plan?from=&to=` | записи меню + денні підсумки |
| POST/DELETE | `/api/meal-plan/entries` | додати/прибрати страву зі слота |
| POST | `/api/meal-plan/copy` | копіювати діапазон дат |
| GET/POST/DELETE | `/api/weight-entries` | вага (POST = upsert по даті) |
| GET | `/api/weight-entries/weekly` | середня вага за ISO-тижнями |

## Структура

```
backend/    Symfony API (entities, controllers, fixtures)
frontend/   Vue 3 SPA (Vite dev server проксує /api на nginx)
docker/     php-fpm Dockerfile, nginx конфіг
.docs/      план дієти (PDF), seed JSON, план розробки
```

## Дорожня карта

- Список закупівель за обраний період (агрегація інгредієнтів із меню) — формат обговорюється.
- Рекомендації страв: мінімізація кількості інгредієнтів на період, «використати залишки».
- Аналіз цін інтернет-магазинів.

## Ліцензія та дані

Код — [MIT](LICENSE).

Довідник продуктів (`backend/data/usda_products.json`) — вибірка з
[USDA FoodData Central](https://fdc.nal.usda.gov/), набір SR Legacy.
Дані USDA є суспільним надбанням ([CC0 1.0](https://creativecommons.org/publicdomain/zero/1.0/)):
U.S. Department of Agriculture, Agricultural Research Service. FoodData Central. fdc.nal.usda.gov.
Українські назви продуктів (`backend/data/usda_names_uk.csv`) — власний переклад.
