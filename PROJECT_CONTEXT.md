# PROJECT_CONTEXT — Krayin CRM → Crypto Call Center

> **Для AI-агентов:** этот файл — единственная точка входа в контекст проекта.
> Читай его ПЕРВЫМ перед любой задачей. После завершения задачи — обнови раздел
> «Журнал выполненных задач» в конце файла.
>
> **Аудит:** 2026-10-17 · статус: первичный аудит завершён, кастомных модулей ещё нет.

---

> **Навигация:** 0 TL;DR · 1 Правила · 2 Стек · 3 Карта директорий ·
> 4 Архитектура · 5 UI (Blade/Vue) · 6 Статус · 7 Кастомизация ·
> 8 Команды · 9 Открытые вопросы · 10 Журнал задач.

## 0. TL;DR (быстрая карточка проекта)

| Параметр | Значение |
|---|---|
| Продукт | Krayin CRM (Webkul), форк под крипто-колл-центр |
| Версия ядра | `2.2.7` — `Webkul\Core\Core::KRAYIN_VERSION` |
| Фреймворк | Laravel `12.66.0` |
| PHP | runtime `8.5.11`, composer-platform `8.3.30`, требование `^8.3` |
| Frontend | Blade + Vue 3 + Tailwind 3 + Vite (сборка отдельная у Admin) |
| БД | MySQL, `krayin_crm` @ `127.0.0.1:3306` |
| Миграции | 97 применено, 0 pending |
| Админка | `http://127.0.0.1:8000/admin` (`config('app.admin_path')`) |
| APP_NAME | `Crypto Call Center` |
| Локаль | **`ru` по умолчанию** (даёт пакет `CryptoCallCenter`); в Admin 10 языков ядра: `en, ar, es, fa, ja, ko, pt_BR, tr, vi, zh_CN` |
| Кастомные модули | `Webkul\CryptoCallCenter` — первый (см. 6.5) |
| Git | ветка `2.2`, HEAD `8f3a3959`, upstream `krayin/laravel-crm` |
| Тесты | Pest 3: `tests/Feature` + `tests/Unit`, 386 тестов / 1183 assertions — зелёные (19.10.2026) |
| Собранные ассеты | `public/admin/build`, `public/build`, `public/installer`, `public/webform` (16.10.2026); `node_modules` отсутствует |
| Менеджеры пакетов | Composer 2, npm 11.16.0 / Node 24.18.0 |

---

## 1. Обязательные правила (не нарушать)

1. **НЕ редактировать ядро** `packages/Webkul/*` без крайней необходимости.
   Любая доработка — новый пакет в `packages/Webkul/{Name}/`.
2. **Схема БД — только миграциями.** Никаких ручных правок таблиц.
3. **Repository pattern:** модели доступны через репозитории, репозитории
   привязаны к контрактам в `ModuleServiceProvider` (Concord).
4. **Новый модуль регистрируется в 3 местах:**
   - `composer.json` → `autoload.psr-4` (`Webkul\\{Name}\\`);
   - `bootstrap/providers.php` → сервис-провайдер пакета;
   - `config/concord.php` → `ModuleServiceProvider` (модели/репозитории).
5. **Переводы следуют за вьюхами:** `Resources/lang/` только если у пакета есть
   `Resources/views/`. Иначе строки живут в Admin (`admin::app.*`).
6. **CHANGELOG.md** обновляется только для продуктовых фиксов/фич, в самой
   верхней невыпущенной секции. Не выдумывать `#PR`, не менять
   `KRAYIN_VERSION` вне релиза. Документация (этот файл) CHANGELOG не требует.
7. **Тесты:** новое поведение — с Pest-тестом; перед сдачей
   `./vendor/bin/pint` и `php artisan test --compact`.
8. **После задачи** — короткая запись в «Журнал выполненных задач» (раздел 10).

---

## 2. Стек технологий

### 2.1 Backend (composer.json)

| Пакет | Зачем |
|---|---|
| `laravel/framework ^12.0` | ядро |
| `konekt/concord ^1.17` | модульность: регистрация моделей/репозиториев по пакетам |
| `prettus/l5-repository ^3.0` | базовый `Repository` (`Webkul\Core\Eloquent\Repository`) |
| `diglactic/laravel-breadcrumbs ^10.0` | хлебные крошки (`routes/breadcrumbs.php`) |
| `laravel/sanctum ^4.0` | API-аутентификация (в тестах — `Sanctum::actingAs`) |
| `laravel/reverb ^1.12`, `pusher/pusher-php-server` | websockets / уведомления |
| `barryvdh/laravel-dompdf ^3.1`, `mpdf/mpdf ^8.2` | PDF (quotes, отчёты) |
| `maatwebsite/excel ^3.1`, `phpoffice/phpspreadsheet` | импорт/экспорт |
| `webklex/laravel-imap ^6.0`, `smalot/pdfparser` | почта (IMAP) и парсинг вложений |
| `google/apiclient ^2.18` | Google Contacts |
| `khaled.alshamaa/ar-php` | арабская локаль |
| `enshrined/svg-sanitize` | безопасность SVG |

Dev: `pestphp/pest ^3.0`, `phpunit/phpunit ^11`, `laravel/pint ^1.18`,
`barryvdh/laravel-debugbar`, `krayin/krayin-package-generator`,
`laravel/sail`, `mockery`.

### 2.2 Frontend

| Слой | Детали |
|---|---|
| Vue | Vue 3 (`vue/dist/vue.esm-bundler`), `createApp` в `Admin/src/Resources/assets/js/app.js` |
| Сборка | **своя** у `packages/Webkul/Admin` (`vite.config.js` → `public/admin/build`) |
| Плагины Vue | axios, mitt (emitter), vee-validate + rules/i18n, flatpickr, vuedraggable, vue-cal, chart.js |
| Директивы | `v-debounce`, `v-safe-html` (DOMPurify), `v-tooltip` |
| Стили | Tailwind 3.3 + `postcss`; брендовый цвет через CSS-переменную `--brand-color` |
| Иконки | иконочный шрифт `icon-*` (например `icon-leads`, `icon-settings`) |
| Корневой Vite | `vite.config.js` собирает `resources/css/app.css` + `resources/js/app.js` в `public/build` (базовый Laravel-каркас, Krayin UI им не пользуется) |

### 2.3 Инфраструктура / окружение (.env)

```
APP_ENV=local            APP_DEBUG=true
DB_CONNECTION=mysql      DB_DATABASE=krayin_crm
QUEUE_CONNECTION=sync    SESSION_DRIVER=file
CACHE_DRIVER=file        BROADCAST_DRIVER=log
MAIL_MAILER=smtp         APP_LOCALE=en
```

> Примечание: имена `CACHE_DRIVER` / `BROADCAST_DRIVER` — «старый» стиль ключей,
> они соответствуют `config/cache.php` и `config/broadcasting.php` этого проекта.
> Не переименовывать в `CACHE_STORE`, пока конфиги не обновлены.

---

## 3. Карта директорий

### 3.1 Корень проекта

| Путь | Что внутри / правила |
|---|---|
| `app/` | **Пустой каркас Laravel**: `Http/Controllers/Controller.php`, `Models/User.php` (стаб, Krayin его не использует), `Providers/AppServiceProvider.php`. Доменной логики здесь быть не должно. |
| `bootstrap/` | `app.php` — middleware, CSRF-исключения (`{admin}/mail/inbound-parse`, `{admin}/web-forms/forms/*`); `providers.php` — полный список провайдеров. |
| `config/` | 23 конфига. Ключевые: `concord.php`, `krayin-vite.php`, `app.php` (`admin_path`), `auth.php`, `database.php`, `repository.php`. |
| `database/` | 4 инфраструктурные миграции (`jobs`, `job_batches`, `failed_jobs`, `personal_access_tokens`); `factories/` и `seeders/` пусты. Доменные миграции живут в пакетах. |
| `lang/` | Стандартные переводы Laravel: `en/{auth,pagination,passwords,validation}.php` + **`ru/{auth,pagination,passwords,validation}.php`** (серверные сообщения валидации/авторизации для русской локали). |
| `packages/Webkul/` | 21 модуль: 20 ядровых — **вся бизнес-логика и UI**, плюс наш `CryptoCallCenter`. |
| `public/` | `index.php`, собранные ассеты: `admin/build`, `build`, `installer`, `webform`; `storage/`. |
| `resources/` | Пустой каркас (`views/` содержит только `.gitignore`); `css/app.css`, `js/app.js`, `js/bootstrap.js` — для корневого Vite. |
| `routes/` | `web.php` пуст, `api.php` — дефолтный Laravel, `channels.php` — каналы `notifications.{id}`, `console.php` — планировщик `inbound-emails:process` каждые 5 минут, `breadcrumbs.php` — крошки. |
| `storage/` | Логи, кэш, сессии, загрузки. |
| `tests/` | `Feature/` (Contact, Leads, Mail, Notifications, Quotes, Security, WebForm), `Unit/`. Конфиг — `phpunit.xml`, хелперы — `tests/Pest.php`. |
| `vendor/` | Composer-зависимости (в git не входит). |
| `bin/validate-skills.sh` | Проверка структуры AI-скиллов. |
| `.github/skills/` | Скиллы агента: `crm-package-development`, `krayin-authorization`, `pest-testing`. |
| `.clinerules/project-memory.md`, `AGENTS.md` | Правила работы агента (дублируют ключевые договорённости). |
| `CHANGELOG.md` | Продуктовый changelog (верхняя секция — `v2.2.7`). |
| `composer.json`, `package.json`, `vite.config.js`, `phpunit.xml`, `pint.json` | Манифесты и конфиги инструментов. |
| `crm_klim.phpproj`, `crm_klim.slnx`, `.vs/` | Локальные IDE-файлы (не часть продукта). |

### 3.2 `config/` — что важно знать

| Файл | Роль |
|---|---|
| `config/concord.php` | Реестр Concord-модулей (`modules => [...]`). Новый модуль добавлять сюда. |
| `config/krayin-vite.php` | Реестр Vite-сборок: `admin`, `installer`, `webform` (hot-file, build-dir). |
| `config/app.php` | `admin_path` (`APP_ADMIN_PATH`, по умолчанию `admin`), локаль, провайдеры. |
| `config/auth.php` | Guard администратора — `user` (middleware `user` в Admin). |
| `config/database.php` | MySQL + SSL-опции; версионно-зависимая константа `ATTR_SSL_CA` для PHP 8.5 (см. 6.3). |
| `config/repository.php` | Настройки `prettus/l5-repository` (кэш, лимиты). |
| `config/breadcrumbs.php` | Подключение файла крошек. |

### 3.3 `packages/Webkul/` — модули (Concord)

| Модуль | Моделей | Зона ответственности |
|---|---:|---|
| `Admin` | 2 (+ `Notification`) | **Весь HTTP-слой и UI**: 50 контроллеров, 270 Blade-вьюх, DataGrids, Vue, CSS, lang (10 локалей), меню, ACL, настройки. |
| `Activity` | 6 | Лента активностей, вложения, трейт `LogsActivity`. |
| `Attribute` | 6 | EAV-атрибуты: `Attribute`, `AttributeOption`, `AttributeValue`. |
| `Automation` | 4 | Workflow-автоматизации. |
| `Contact` | 4 | `Person`, `Organization` — контакты. |
| `Core` | 6 | Ядро: `Core`, `Menu`, `Acl`, `SystemConfig`, базовый `Repository`, helper-функции, страны. |
| `DataGrid` | 2 | Абстракция таблиц: колонки, фильтры, масс-действия, экспорт. |
| `DataTransfer` | 4 | Импорт/экспорт данных. |
| `Email` | 4 | Письма, связь с лидами/контактами. |
| `EmailTemplate` | 2 | Шаблоны писем. |
| `GoogleContact` | 6 | Интеграция с Google Contacts. |
| `Installer` | 0 (3 контроллера, 7 вьюх) | Веб-установщик. |
| `Lead` | 12 | `Lead`, `Pipeline`, `Stage`, `Source`, `Type`, `Product` — лиды и воронки. |
| `Marketing` | 4 | События и кампании. |
| `Product` | 4 | Товары. |
| `Quote` | 4 | Коммерческие предложения и позиции. |
| `Tag` | 2 | Теги. |
| `User` | 6 | `User`, `Role`, `Group` — пользователи, роли, права, источники видимости. |
| `Warehouse` | 4 | Склады. |
| `WebForm` | 4 (2 контроллера, 13 вьюх) | Формы захвата лидов. |

> **Контроллеры есть только в `Admin` (50), `WebForm` (2) и `Installer` (3).**
> Остальные модули — модели + контракты + репозитории + миграции.
> Ключевая особенность: **домен отдельно, HTTP/UI — в Admin**.

### 3.4 Типовая структура доменного модуля (пример `Lead`)

```text
packages/Webkul/Lead/
├── composer.json
└── src/
    ├── Contracts/            # интерфейсы (Lead, Pipeline, ...)
    ├── Database/Migrations/  # 18 миграций лидов
    ├── Helpers/
    ├── Models/               # Eloquent + Proxy-классы (LeadProxy, ...)
    ├── Providers/
    │   ├── LeadServiceProvider.php    # миграции
    │   └── ModuleServiceProvider.php  # $models = [...] → Concord
    ├── Repositories/         # LeadRepository extends Core\Eloquent\Repository
    └── Services/
```

### 3.5 Шпаргалка «где искать X»

| Что нужно | Путь |
|---|---|
| Маршруты админки | `packages/Webkul/Admin/src/Routes/Admin/*.php` (подключаются из `web.php`) |
| Контроллеры | `Admin/src/Http/Controllers/{Lead,Contact,Quote,Mail,Products,Settings,User,Activity,Configuration,DataGrid}` |
| Модели | `packages/Webkul/{Module}/src/Models/` |
| Контракты | `packages/Webkul/{Module}/src/Contracts/` |
| Репозитории | `packages/Webkul/{Module}/src/Repositories/` |
| Миграции | `packages/Webkul/{Module}/src/Database/Migrations/` |
| Таблицы (DataGrid) | `packages/Webkul/Admin/src/DataGrids/` |
| Blade-вьюхи | `packages/Webkul/Admin/src/Resources/views/` |
| Blade-компоненты | `Admin/src/Resources/views/components/` (161 файл) |
| Vue-компоненты | inline в Blade: `<script type="text/x-template" id="v-...-template">` + `app.component(...)` в `@pushOnce('scripts')` |
| JS-плагины/директивы | `Admin/src/Resources/assets/js/{plugins,directives}` |
| Меню | `packages/Webkul/Admin/src/Config/menu.php` |
| ACL (права) | `packages/Webkul/Admin/src/Config/acl.php` |
| Настройки (Configuration) | `packages/Webkul/Admin/src/Config/core_config.php` |
| Сущности для кастомных атрибутов | `packages/Webkul/Admin/src/Config/attribute_entity_types.php` |
| Переводы | Ядро: `packages/Webkul/Admin/src/Resources/lang/{en,ar,es,fa,ja,ko,pt_BR,tr,vi,zh_CN}/`; **`ru` — в пакете:** `CryptoCallCenter/src/Resources/lang/{ru,overrides/ru}/` |
| Helper-функции | `packages/Webkul/Core/src/Http/helpers.php` (`core()`, `menu()`, `acl()`, `system_config()`, `view_render_event()`, `vite()`) |
| Крошки | `routes/breadcrumbs.php` |

---

## 4. Архитектура: как проходит запрос

```text
GET /admin/leads
  │
  ├─ bootstrap/app.php            middleware (web, CanInstall, CSRF…)
  ├─ AdminServiceProvider::boot() prefix config('app.admin_path')
  │     middleware ['web','admin_locale','user']  ← user = Bouncer (ACL)
  │     Routes/Admin/leads-routes.php
  ├─ Admin\Http\Controllers\Lead\LeadController@index
  │     ├─ Bouncer::hasPermission('leads') / data-scope (global|group|individual)
  │     ├─ LeadRepository (Contract Webkul\Lead\Contracts\Lead)
  │     │     └─ Eloquent Model Webkul\Lead\Models\Lead (+ CustomAttribute EAV)
  │     └─ Admin\DataGrids\Lead\LeadDataGrid
  └─ return view('admin::leads.index')
        └─ <x-admin::layouts> (Blade anonymous component)
              ├─ sidebar  → menu()->getItems('admin') → config('menu.admin') + ACL
              ├─ header   → quick create, mega search, notifications
              └─ content  → Blade + Vue 3 (inline templates) + view_render_event hooks
```

### 4.1 Ключевые механизмы

| Механизм | Как работает |
|---|---|
| **Concord** | `ModuleServiceProvider` каждого пакета регистрирует модели и репозитории; `config/concord.php` — список модулей. Даёт `LeadProxy` и авто-привязку `Contract → Repository`. |
| **Repository** | Базовый класс `Webkul\Core\Eloquent\Repository` (обёртка `prettus/l5-repository`) + `fieldSearchable`, `model()`, `create/update/delete`. |
| **EAV / Custom Attributes** | Пакет `Attribute`: значения хранятся в `attribute_values`, набор сущностей — `Admin/Config/attribute_entity_types.php` (`leads`, `persons`, `organizations`, `products`, `quotes`, `warehouses`). |
| **ACL** | `Admin/Config/acl.php` → `Bouncer::hasPermission()`; роль с `permission_type = all` видит всё; иначе — по ключам прав. |
| **Меню** | `config('menu.admin')` (ключи в dot-нотации, `sort`, `icon-class`), фильтруется ACL, рендерится в `components/layouts/sidebar/{desktop,mobile}`. |
| **Настройки** | `Admin/Config/core_config.php` → таблица `core_config` → `core()->getConfigData('group.field')`; страница Configuration. |
| **События домена** | `lead.create.after`, `quote.create.after`, `activity.create.after` и т.п.; слушатели — `Admin/Providers/EventServiceProvider`. |
| **Хуки вьюх** | `view_render_event('admin.leads.index.header.before')` → Laravel event; слушатель получает `ViewRenderEventManager`, вызывает `addTemplate('crypto::partials.x')`, шаблон рендерится в этой точке. |
| **Уведомления / WS** | `Admin\Services\NotificationDriver`, канал `notifications.{id}`, Reverb/Pusher. |

### 4.2 Соглашения по коду

- Строгий PSR-12, форматирование — Laravel Pint.
- Модели — с `Proxy`-классами (`LeadProxy`), контрактами и репозиториями.
- Контроллеры — тонкие, логика в репозиториях/сервисах.
- Валидация — `Http/Requests` (в Admin — `Requests/`).
- Даты/валюты — через `core()` (`currencySymbol`, настройки), не хардкодить.
- В Blade: `@lang('admin::app...')`, `bouncer()->hasPermission(...)`, `core()->getConfigData(...)`.

---

## 5. Навигация и интерфейс (Blade + Vue)

### 5.1 Композиция layout

Главный layout — anonymous Blade component `<x-admin::layouts>`
(`Admin/src/Resources/views/components/layouts/index.blade.php`).
Компоненты Admin зарегистрированы как `x-admin::*` через
`Blade::anonymousComponentPath(..., 'admin')`.

```text
<x-admin::layouts>            ← index.blade.php
├── header/index             — логотип, мега-поиск, quick create, уведомления, профиль
│   ├── sidebar/mobile       — мобильное меню (desktop-сайдбар рендерится отдельно)
│   ├── desktop|mobile/mega-search
│   ├── quick-creation       — быстрый add: лид, quote, персона, организация, продукт
│   └── notifications        — колокольчик (WS-канал notifications.{id})
├── sidebar/desktop          — меню из menu()->getItems('admin') + подменю
└── {{ $slot }}              — контент страницы
```

Особенности:

- **Сайдбар** — 200px, сворачивается в 70px (cookie `sidebar_collapsed`), подменю
  в flyout-панели, активный пункт — по URL.
- **Тема** — light/dark через cookie `dark_mode` (класс `dark` на `<html>`),
  брендовый цвет — `general.settings.menu_color.brand_color` (по умолчанию
  `#0E90D9`), отдаётся в CSS-переменную `--brand-color`.
- **RTL** — автоматически для локалей `fa`, `ar` (`dir` на `<html>`).
- **Адаптив** — отдельные компоненты `sidebar/mobile`, `header/mobile`.
- **Кастомный CSS без кода** — Configuration → General → Custom CSS
  (`general.content.custom_scripts.custom_css`) вставляется в `<style>` layout.
- **Точки расширения** — `admin.layout.head`, `admin.layout.body.before/after`,
  `admin.layout.content.before/after`, `admin.layout.vue-app-mount.before/after`
  и страничные `admin.{section}.index.*`; плюс `@stack('styles')` / `@stack('scripts')`.

### 5.2 Как устроен Vue

- Единое приложение: `createApp({...})` в `Admin/src/Resources/assets/js/app.js`,
  монтируется на `#app` в layout по событию `load`.
- Компоненты объявляются **прямо в Blade**:
  ```blade
  @pushOnce('scripts')
      <script type="text/x-template" id="v-my-widget-template">
          <div>@{{ message }}</div>
      </script>

      <script type="module">
          app.component('v-my-widget', {
              template: '#v-my-widget-template',
              data() { return { message: 'Hello' }; },
          });
      </script>
  @endPushOnce
  ```
  Именно так сделаны `v-sidebar-collapse`, `v-dashboard-filters`, datagrid,
  kanban и прочие компоненты.
- Плагины: `$admin` (форматирование цен), axios (`$axios`), emitter (`$emitter`),
  flatpickr, vee-validate, draggable, vue-cal.
- Директивы: `v-debounce`, `v-safe-html`, `v-tooltip`.
- Сборка Admin **отдельная**: `packages/Webkul/Admin/vite.config.js` →
  `public/admin/build`, hot-file `public/admin-vite.hot`; подключается через
  `vite()->set(['src/Resources/assets/css/app.css', 'src/Resources/assets/js/app.js'])`.

### 5.3 Готовые UI-компоненты (переиспользовать, не дублировать)

`accordion`, `activities`, `attachments`, `attributes`, `avatar`, `breadcrumbs`,
`button`, `charts`, `datagrid`, `drawer`, `dropdown`, `flash-group`,
`flat-picker`, `form`, `lookup`, `media`, `modal`, `shimmer`, `spinner`,
`table`, `tabs`, `tags`, `tinymce`, `tree` — всё в
`Admin/src/Resources/views/components/`.

> Правило UI: сначала ищем подходящий `x-admin::*` компонент; новый вёрстку
> делаем в своём пакете с префиксом `x-crypto::*` (или ином имени модуля).

---

## 6. Текущий статус проекта

### 6.1 Установлено

| Компонент | Статус |
|---|---|
| Krayin CRM | 2.2.7 (git-ветка `2.2`, upstream) |
| Composer-зависимости | установлены (`vendor/` присутствует) |
| npm-зависимости | **не установлены** (нет `node_modules` ни в корне, ни в `packages/Webkul/Admin`) |
| Собранные ассеты | присутствуют (16.10.2026) — UI работает без пересборки |
| БД | `krayin_crm` (MySQL), 96 миграций применено, pending — нет |
| Админ-пользователь | есть: 1 пользователь (id=1); лидов — 0, т.е. инстанс чистый |
| Кастомные пакеты | `packages/Webkul/CryptoCallCenter` (см. 6.5) |
| Кастомные вьюхи | отсутствуют (`resources/views` пуст) |
| `.env` | настроен: MySQL, `APP_NAME="Crypto Call Center"`, `APP_ENV=local`, `APP_DEBUG=true` |

### 6.2 Git

- Ветка `2.2`, HEAD `8f3a3959` (merge PR #2694), remote — `origin` (Krayin).
- Рабочее дерево: продуктовые изменения — только регистрация пакета
  (`composer.json`, `bootstrap/providers.php`, `config/concord.php`) и новый
  каталог `packages/Webkul/CryptoCallCenter/`, тест
  `tests/Feature/CryptoCallCenter/`. Плюс локальные IDE-файлы (`crm_klim.*`,
  `.vs/`, `.clinerules/`) и `config/database.php` (фикс PHP 8.5).
- **Своей ветки/remote для кастома ещё нет** (рекомендация: `feature/*` от `2.2`).

### 6.3 Известные особенности окружения

1. **PHP 8.5 vs composer-platform 8.3.30.** Runtime — PHP 8.5.11, а
   `composer.json` фиксирует platform 8.3.30. Код рассчитан на 8.3+; при
   использовании синтаксиса 8.4/8.5 CI/другие машины могут не собраться.
2. **PHP 8.5 и `PDO::MYSQL_ATTR_SSL_CA`** — исправлено 17.10.2026 в
   `config/database.php` (mysql + mariadb): константа выбирается по версии
   (`\Pdo\Mysql::ATTR_SSL_CA` для PHP ≥ 8.4, иначе `\PDO::MYSQL_ATTR_SSL_CA`).
   До фикса каждый тест помечался `deprecated` и `php artisan test` завершался
   с кодом 1.
3. **Xdebug** включён и пытается подключиться к `127.0.0.1:9003` при каждом
   CLI-запуске (таймаут 200 мс, шум в stderr). Для чистых прогонов:
   `php -d xdebug.mode=off artisan ...`.
4. **`node_modules` отсутствуют** — для `npm run dev/build` в
   `packages/Webkul/Admin` нужен `npm install` (lock-файла нет, версии плавают).
5. `resources/views` и `routes/web.php` — пустой каркас, это нормально для Krayin.
6. Laravel-стаб `App\Models\User` **не используется** — реальная модель
   `Webkul\User\Models\User` (guard `user`).

### 6.4 Безопасность (уже в ядре — не ломать)

- ACL через `Bouncer` + роли/группы; data-scope (`global`/`group`/`individual`).
- Sanctum для API; CSRF-исключения только для inbound-парсинга почты и web-forms.
- `SanitizeUrl` middleware, `enshrined/svg-sanitize`, DOMPurify (`v-safe-html`).
- Пароли — bcrypt, токены — Sanctum; `.env` в `.gitignore`.

### 6.5 Кастомный пакет `Webkul\CryptoCallCenter` (создан 17.10.2026)

Первый изолированный пакет: `packages/Webkul/CryptoCallCenter/`,
PSR-4 `Webkul\CryptoCallCenter\` → `src/`, Composer-имя
`krayin/laravel-crypto-call-center`.

| Файл | Роль |
|---|---|
| `src/Providers/CryptoCallCenterServiceProvider.php` | `loadViewsFrom`/`loadTranslationsFrom` (namespace `crypto_call_center`), `loadMigrationsFrom`, маршруты под `config('app.admin_path')` + middleware `web`, `admin_locale`, `user`; merge `menu.admin`, `acl`, `roles` (`crypto_call_center.roles`) и каталога подписей (`crypto_call_center.localization`); биндинг гридов и регистрация команды (см. ниже); регистрирует `EventServiceProvider`. |
| `src/Providers/ModuleServiceProvider.php` | Concord: `CryptoWallet::class`, `CallLog::class` → контракт/прокси. |
| `src/Contracts/CryptoWallet.php` | Пустой маркерный контракт (как в ядре). |
| `src/Models/CryptoWallet.php` + `CryptoWalletProxy.php` | Модель: `fillable(lead_id, wallet_address, network)`, `belongsTo(LeadProxy::modelClass())`. |
| `src/Repositories/CryptoWalletRepository.php` | `extends Webkul\Core\Eloquent\Repository`; `findByLead(int $leadId)` — выборка кошельков лида. |
| `src/Database/Migrations/2026_10_17_000000_create_crypto_wallets_table.php` | `crypto_wallets`: `lead_id` (FK → `leads`, cascade on delete), `wallet_address(191)`, `network(50)`, timestamps, unique `lead_id+wallet_address+network`, индекс `network`. |
| `src/Config/menu.php` / `src/Config/acl.php` | Пункты сайдбара и права: `crypto_call_center` (кошельки, sort 7, `icon-dollar`) и `call_logs` (история звонков, sort 8, `icon-call`), без правки Admin. |
| `src/Routes/admin.php` | `GET admin/crypto-call-center/wallets` → `admin.crypto_call_center.wallets.index`; `GET admin/crypto-call-center/call-logs` → `admin.crypto_call_center.call_logs.index`. |
| `src/Http/Controllers/CryptoWalletController.php` | JSON для datagrid при AJAX, иначе blade. |
| `src/DataGrids/CryptoWalletDataGrid.php` | `Webkul\DataGrid\DataGrid`: колонки id / wallet_address / network / lead_title / created_at, фильтр по data-scope пользователя, action «View lead». |
| `src/Resources/views/wallets/index.blade.php` | `<x-admin::layouts>` + `<x-admin::datagrid :src="route(...)">`. |
| `src/Contracts/CallLog.php` + `src/Models/CallLog.php` + `CallLogProxy.php` | Модель звонка: статусы `completed/missed/failed` (`CallLog::STATUSES`), касты `duration` (int, секунды) и `call_date` (datetime), `belongsTo(LeadProxy/UserProxy)`, `formatDuration()` → `m:ss` / `h:mm:ss`. |
| `src/Repositories/CallLogRepository.php` | `extends Webkul\Core\Eloquent\Repository`; `findByLead(int $leadId)` — звонки лида, новые первыми (relation на модели ядра не добавляется — см. ловушку `CustomAttribute` ниже). |
| `src/Database/Migrations/2026_10_17_000100_create_call_logs_table.php` | `call_logs`: `lead_id` (FK → `leads`, cascade), `user_id` (FK → `users`, nullOnDelete), `phone_number(50)`, `duration` (секунды), `status(20)`, `recording_url(2048)`, `transcript`, `call_date`, timestamps; индексы `status`, `lead_id+call_date`, `user_id+call_date`. |
| `src/DataGrids/CallLogDataGrid.php` | `Webkul\DataGrid\DataGrid`: `sortColumn = call_date`, leftJoin `leads`/`users`, скоуп по `bouncer()->getAuthorizedUserIds()` (`global` → без ограничения, `group`/`individual` → `whereIn('call_logs.user_id', …)`), фильтры (диапазон дат, dropdown статусов и менеджеров), колонка `transcript` скрыта по умолчанию (`visibility => false`), action «View lead» только при `leads.view`. |
| `src/Http/Controllers/CallLogController.php` | JSON для datagrid при AJAX, иначе blade. |
| `src/Resources/views/call-logs/index.blade.php` + `call-logs/datagrid/status.blade.php` | Экран истории звонков и бейдж статуса (`label-active` для `completed`, `label-inactive` для `missed`/`failed`): берутся только классы, уже собранные в `public/admin/build`, иначе без `node_modules` они не появятся в CSS. |
| `tests/Feature/CryptoCallCenter/CallLogTest.php` | Pest: repository + `belongsTo`, контракт, `formatDuration()`, экран + JSON datagrid, пункт меню `call_logs`, ACL (401 без `call_logs`), data-scope `individual` видит только свои звонки. |
| `src/Resources/lang/en/app.php` | `app.menu.crypto`, `app.acl.crypto`, `app.wallets.*`, `app.call-logs.*` (пакет имеет views → свой `lang`). |
| `tests/Feature/CryptoCallCenter/CryptoWalletTest.php` | Pest: repository create + `belongsTo`, разрешение модели через контракт, рендер экрана и datagrid JSON, пункт меню в `Menu::getItems('admin')`, ACL (401 без права `crypto_call_center`). |
| `src/Resources/views/components/branding/mark.blade.php` | SVG-марка «C»: цвет наследуется (`currentColor` → `text-brandColor`), размер задаётся через `$class`. |
| `src/Resources/views/components/branding/logo.blade.php` | Вордмарк `CRYPTO`: марка + `crypto_call_center::app.branding.name`; размер марки — через `$markClass`. |
| `src/Resources/views/overrides/components/layouts/header/index.blade.php` | **Переопределение вьюхи ядра** — копия шапки Admin с брендингом (см. ниже). |
| `src/Resources/views/overrides/components/layouts/sidebar/mobile/index.blade.php` | **Переопределение вьюхи ядра** — копия мобильного drawer'а с брендингом (см. ниже). |
| `src/Resources/views/overrides/partials/breadcrumbs.blade.php` | **Переопределение вьюхи ядра** — копия партиала крошек: убирает корневую крошку «Дашборд», если у роли нет права `dashboard` (иначе на каждой странице ссылка в 401); при наличии права — как в ядре. |
| `src/Resources/views/overrides/errors/index.blade.php` | **Переопределение вьюхи ядра** — копия страницы ошибок (401/403/404/500/503): кнопка «Дашборд» ведёт на первый доступный роли раздел и подписана его именем; у роли с `dashboard` — как в ядре. |
| `tests/Feature/CryptoCallCenter/BrandingTest.php` | Pest: `CRYPTO` + новый футер на авторизованной странице и на логине, отсутствие `krayincrm.com`, живость остальных строк `admin::app.*`, источник override-вьюх. |
| `src/Resources/lang/ru/app.php` | Русские строки самого пакета (`app.menu.*`, `app.acl.*`, `app.wallets.*`, `app.call-logs.*`). `app.branding.powered-by` здесь **английский** — как и в `en`: брендинг не переводится. |
| `src/Resources/lang/overrides/ru/admin/app.php` | **Полный перевод ядра**: все листья `admin::app.*` из `Admin/src/Resources/lang/en/app.php` (логин/forgot/reset, лиды, контакты, товары, предложения, активности, почта, настройки, конфигурация, дашборд, меню, ошибки, экспорт, валидация, справка). Единственный непереведённый ключ — `components.layouts.powered-by.description` (брендинг остаётся английским). Инвариант закреплён тестом `LocaleTest`. |
| `lang/ru/{auth,pagination,passwords,validation}.php` (корень проекта) | Серверные сообщения Laravel для русской локали; `validation.php` содержит и блок `attributes` (русские названия полей в ошибках). |
| `tests/Feature/CryptoCallCenter/LocaleTest.php` | Pest: `ru` в списке локалей, русский логин, дашборд/сайдбар, лиды/почта/активности, экран конфигурации, английские экраны остаются английскими, фолбэк на `en` только для строки брендинга + проверка 1:1 покрытия `admin::app` (ни одного пропущенного и ни одного лишнего ключа). |
| `src/Config/roles.php` | Декларация ролей пакета: `operator` → имя `Call Center Operator`, описание, список ACL-ключей и `view_permission = individual`. Новых прав не вводит — только подмножество уже существующего дерева Admin + пакета. |
| `src/Console/Commands/ProvisionOperatorRole.php` | `php artisan crypto-call-center:operator-role {role=operator}`: идемпотентно создаёт роль (`permission_type = custom`); ключ роли — аргумент из `Config/roles.php`, `--all` обходит все объявленные роли в порядке конфига (родитель раньше ребёнка), `--name`/`--description` вместе с `--all` запрещены, неизвестный ключ печатает список объявленных ролей; без `--sync` существующую роль не меняет (печатает diff), с `--sync` перезаписывает права из конфига; падает, если в конфиге есть ключ, которого нет в `config('acl')`, или неизвестный `view_permission`. `--sync-users` проставляет объявленный data-scope всем пользователям роли (без флага команда только сообщает, сколько аккаунтов расходятся). |
| `src/Support/OperatorScope.php` | Роль → data-scope: читает `crypto_call_center.roles.operator.view_permission`, находит роль по имени и перезаписывает `view_permission` пользователя в слушателе `User::saving` (провайдер регистрирует его в `boot()`). Остальные роли и отсутствующая роль не трогаются; неизвестное значение в конфиге даёт `null` и ничего не меняет. |
| `src/DataGrids/Concerns/HidesUnauthorizedMassActions.php` | Убирает из грида массовые действия, на которые у роли нет права: URL действия сопоставляется с URI зарегистрированного маршрута (с учётом подкаталога приложения), имя маршрута ищется в `acl()->getRoles()`, результат проверяется `bouncer()->hasPermission()`. Немэппленные маршруты остаются видимыми. |
| `src/DataGrids/Overrides/{Lead,Activity,Organization}DataGrid.php` | Наследники ядровых гридов (ядро не правится): `prepareMassActions()` вызывает родителя и фильтрует список трейтом; провайдер биндит их на имена ядровых классов, которые контроллеры и так резолвят через `datagrid(...)`. |
| `tests/Feature/CryptoCallCenter/OperatorRoleTest.php` | Pest (9 кейсов): создание роли из конфига, идемпотентность + `--sync`, отказ на неизвестном ACL-ключе, состав меню, доступные (лиды, контакты, активности, кошельки, история звонков, очередь выплат, создание лида) и запрещённые (дашборд, предложения, товары, пользователи, роли, конфигурация, карта выплаты) экраны + `DELETE` лида, `sales owner` созданного лида, ссылки лого/крошек/страницы ошибок, подмена гридов, массовые действия для оператора и админа. Кейс создания провижнит роль под уникальным именем (`--name=… <uniqid>`), иначе на рабочей БД уже лежит роль из прошлого релиза, команда её не трогает, и проверка описания зависела бы от этой строки. |
| `tests/Feature/CryptoCallCenter/OperatorScopeTest.php` | Pest (8 кейсов): объявленный `individual`-скоуп и отсутствие `leads.delete`/`leads.mass_delete` в роли, перезапись скоупа при создании и обновлении оператора, неприкосновенность других ролей, ретрофит через `--sync-users`, отказ на неизвестном скоупе, выдача гридом лидов только лидов оператора, скрытая строка «Удалить», `DELETE` своих и чужих лидов → 401. |
| `src/Config/localization.php` | Каталог русских подписей, которые живут **в БД**: `attributes[entity][code] = ['en' => …, 'ru' => …]` для `leads`, `persons`, `organizations`, `products`, `quotes`, `warehouses` и `lookups[table][english] = russian` для `lead_sources`, `lead_types`, `lead_pipelines`, `lead_pipeline_stages`. Английское значение — «сторож»: строка переименовывается, только пока она его несёт. Свои опции (`attribute_options`) не перечислены — это словарь заказчика. |
| `src/Console/Commands/LocalizeCatalog.php` | `php artisan crypto-call-center:localize-catalog`: переименовывает `attributes.name` (по `entity_type` + `code`), названия опций lookup-таблиц (по английскому `name`) и уже сохранённую историю (`activities.title`, `additional.attribute`, `additional.new/old.label`). Идемпотентна: повторный прогон печатает `0` на каждой строке; `--force` перезаписывает поле, переименованное вручную, `--dry-run` только печатает план, `--skip-history` не трогает историю. Сущность записи истории берётся из пивотов `lead_/person_/product_/warehouse_activities`, поэтому общий «Name» товара не получает подпись персоны; если русское имя уже занято (`lead_pipelines.name` уникален), опция пропускается с предупреждением вместо слияния строк. Счётчики сбрасываются в `handle()`, иначе повторный вызов в одном процессе печатал бы суммы. Отдельно пересобираются подписи дат: ядро форматирует их (`D M d, Y`) до записи, поэтому дата восстанавливается из сырого `additional.*.value` как `20.10.2026` (`20.10.2026 14:30` для `datetime`), неразбираемое значение остаётся с прежней подписью. |
| `tests/Feature/CryptoCallCenter/CatalogLocalizationTest.php` | Pest (10 кейсов): переименование полей и опций, перезапись истории (заголовок + payload + подпись опции), пересборка подписи даты из сырого значения и сохранение подписи, которую из значения не собрать, идемпотентность, сохранение ручного переименования и `--force`, `--dry-run` без записи, `--skip-history`, разрешение общего имени через сущность активности, пропуск опции с занятым русским именем. |
| `src/Database/Seeders/DemoDataSeeder.php` | Демо-контент для чистой установки: 10 персон, 15 лидов по всем шести стадиям (4 `new`, 3 `follow-up`, 2 `prospect`, 2 `negotiation`, 2 `won`, 2 `lost`), 5 кошельков, 8 звонков. Пишет через репозитории ядра и пакета (`PersonRepository`, `LeadRepository`, `CryptoWalletRepository`, `CallLogRepository`), поэтому `unique_id` персоны, контактные данные и записи истории активности — те же, что при ручном сохранении. Признак своих строк — домен `demo.klimiata.test` в `unique_id` (отдельной таблицы учёта нет): второй запуск печатает «Demo data is already present…» и ничего не пишет, `--fresh` (`$fresh = true`) сначала удаляет лиды прошлого прогона, затем их персон (кошельки и звонки уходят по каскаду FK), `$withCallLogs = false` сеет всё, кроме истории звонков. Владелец — первый аккаунт с ролью `permission_type = all`, пайплайн — дефолтный; отсутствие админа, пайплайна, стадии, источника или типа валит запуск с объяснением, а не пишет половину набора (все вставки в одной транзакции). Счётчики сбрасываются в `run()`, иначе повторный вызов в одном процессе печатал бы суммы прошлого прогона. |
| `src/Console/Commands/SeedDemoData.php` | `php artisan crypto-call-center:demo-data` — обёртка над сидером: флаги `--fresh` (заменить прежние демо-строки) и `--no-calls` (без истории звонков), привязывает консоль, чтобы строки `info`/`warn` печатались, возвращает `SUCCESS`. |
| `tests/Feature/CryptoCallCenter/DemoDataSeederTest.php` | Pest (4 кейса): 10 персон / 15 лидов / 5 кошельков / 8 звонков, диапазон `lead_value` 100–5000, 4 закрытых лида (2 `won` + 2 `lost`), история активности у лида и у персоны, пропуск второго запуска и замена на `--fresh`, 200 для канбана лидов и дашборда, команда с `--no-calls` и её вывод. Хелпер `completeDemoPipeline()` дорисовывает шесть стадий в `securityTestPipeline()` — параллельные тестовые БД мигрированы, но не засеяны; счётчики проверяются только по демо-строкам (домен `demo.klimiata.test`), потому что в dev-БД демо-данные уже могут лежать. |
| `CHANGELOG.md` (в корне пакета) | История изменений пакета — чтобы не трогать релизный `CHANGELOG.md` ядра. |

**Роли «Call Center Operator» и «Call Center Moderator» (оператор с 17.10.2026,
модератор с 18.10.2026):** права объявлены в `Config/roles.php`, создаются
командой, правок ядра нет. Модератор объявлен через `inherits => operator`:
права объединяются, остальные ключи ребёнка перекрывают родителя, поэтому
экран, выданный оператору, на следующем прогоне появляется и у модератора.
Разрешение наследования живёт в `OperatorScope::definitions()` — её читают
команда, слушатели и тесты, так что роли не расходятся.

| Что | Как устроено |
|---|---|
| Состав прав | 23 ключа: `leads`, `leads.create`, `leads.create.quick-create`, `leads.view`, `leads.edit`; `contacts`, `contacts.persons`, `contacts.persons.create` (с quick-create), `contacts.persons.view`, `contacts.persons.edit`, `contacts.organizations`, `contacts.organizations.create` (с quick-create), `contacts.organizations.edit`; `activities`, `activities.create`, `activities.edit`; `crypto_call_center`, `crypto_call_center.balance` (изменение баланса — только по своим лидам, см. блок ниже); `call_logs` (история звонков); `withdrawals`, `deposits` (маскированные очереди). Удаление лидов, правка чужих активностей, настройки, пользователи, роли, группы, товары, предложения, почта и дашборд не выдаются; полный номер карты (`withdrawals.card`) и решение по депозиту (`deposits.review`) остаются у модератора. |
| Общая телефонная книга | `Webkul\CryptoCallCenter\Bouncer` наследует ядровой `Admin\Bouncer` и зарегистрирован в провайдере под строковым ключом `bouncer`, который резолвят и `bouncer()`, и фасад. На маршрутах `admin.contacts.*` для аккаунтов с объявленной ролью `getAuthorizedUserIds()` возвращает `null` — ядровой способ сказать «без фильтра по владельцу», поэтому оператор открывает персону и организацию коллеги в гриде, на карточке и в форме правки. Остальные маршруты (лиды, активности, баланс) и остальные роли идут в родителя без изменений. Таймлайн контакта (`admin.contacts.persons.activities.*`) исключён намеренно: карточка общая, а звонки и заметки на ней — только свои. |
| Создатель лида = sales owner | Флаг `assign_creator_as_lead_owner` в объявлении: `Listeners/AssignLeadSalesOwner` на `lead.create.after` переписывает `user_id` лида на создавшего (`saveQuietly()`, без второй записи в истории). Иначе оператор, чей скоуп показывает только его лиды, создал бы лид и потерял бы его сразу после сохранения. Админ, веб-форма и API сохраняют переданного владельца. |
| Видимость лидов | Роль объявляет `view_permission = individual`, но Krayin хранит data-scope **на пользователе**, а не на роли, поэтому значение навязывается слушателем `User::saving` (`Support/OperatorScope`): аккаунт с ролью получает `individual` при создании и при каждом сохранении, включая Settings → Users. Грид лидов фильтруется ядровым `bouncer()->getAuthorizedUserIds()`, так что оператор видит только свои лиды. Существующие аккаунты переносит `--sync-users`. |
| Создание | `php artisan crypto-call-center:operator-role {role=operator}` (идемпотентно). Ключ роли — аргумент из `Config/roles.php`, `--all` провижнит все объявленные роли в порядке конфига (родитель раньше ребёнка), `--sync` — перезаписать права существующей роли из конфига, `--sync-users` — проставить объявленный data-scope всем пользователям роли (без флага команда только сообщает, сколько аккаунтов расходятся), `--name=` / `--description=` — свои значения (с `--all` запрещены). Неизвестный ключ завершает команду ошибкой со списком объявленных ролей; все сообщения называют ключ роли. Роль назначается пользователю в Settings → Users. |
| Почему ключей достаточно | `Menu::getItems('admin')` печатает пункт только при `bouncer()->hasPermission($key)`, а `User::hasPermission()` — строгое `in_array` без иерархии (родитель НЕ выдаётся вместе с ребёнком и наоборот). Поэтому в списке есть и группы, и их дети: без `contacts` пункт «Контакты» не отрисуется, а `Arr::undot()` потеряет атрибуты группы. |
| Что даёт `contacts` | Ключ указывает на `admin.contacts.users.index` — такого маршрута в Admin нет, так что кроме родительского узла меню он ничего не открывает. |
| Лендинг без дашборда | `SessionController::store()` при отсутствии `dashboard` редиректит на первый доступный пункт меню (у оператора — «Лиды»), лого в override-шапке ведёт туда же, крошки не печатают корневую крошку «Дашборд», а кнопка на странице ошибок ведёт на первый доступный раздел и подписана его именем. Иначе роль упиралась бы в 401 на лого, в крошках и на самой странице 401. |
| Кнопки | Создание лида, теги, предложения, правка персоны и атрибуты уже гейтятся в ядре через `bouncer()->hasPermission()`; строка «Удалить» в гриде лидов — тоже (роль без `leads.delete` её не получает, override вьюхи не нужен). Массовые действия ядро печатает без проверки, поэтому они фильтруются трейтом `HidesUnauthorizedMassActions` (см. `DataGrids/Overrides`). Сам `DELETE admin/leads/{id}` всё равно отвечает 401 и на свой, и на чужой лид — эндпоинт закрыт отсутствием права, а не только интерфейсом. |
| Проверка | `OperatorRoleTest` (9 кейсов): меню, экраны (200 для лидов/контактов/активностей/кошельков/истории звонков/очереди выплат/очереди пополнений/создания лида, 401 для дашборда/предложений/товаров/пользователей/ролей/групп/конфигурации/карты выплаты/заявки на депозит/`DELETE` лида), массовые действия и лид, созданный оператором, с ним же в роли sales owner. `OperatorScopeTest` (9 кейсов): объявленный скоуп, перезапись при сохранении пользователя, неприкосновенность других ролей, `--sync-users`, отказ на неизвестном скоупе, грид лидов только со своими лидами, скрытая строка «Удалить», `DELETE` своих и чужих лидов → 401, а также общая телефонная книга: чужие персона и организация видны в гридах, карточка и форма правки открываются, но грид активностей и таймлайн чужой персоны отвечают 401, свой — 200. |

**Роль «Call Center Moderator» (с 18.10.2026):** оператор, который ещё и
курирует стол — подтверждает выплаты и правит балансы подчинённых. Объявление —
`'moderator'` в `Config/roles.php`, провижн — та же команда (`--all` или
`operator-role moderator`).

| Что | Как устроено |
|---|---|
| Наследование | `inherits => operator`: права объединяются с операторскими (сейчас 25 ключей против 23), остальные ключи ребёнка перекрывают родителя. Своих добавлений два — `withdrawals.card` (полный номер карты на экране выплаты) и `deposits.review` (страница заявки на пополнение и само решение), ради которых модератор и существует: он подтверждает выплаты и зачисляет пополнения. |
| Видимость | `view_permission => global` — скоуп запиннен так же, как `individual` у оператора: `OperatorScope::enforce()` переписывает значение при создании и каждом сохранении аккаунта, поэтому форма Settings → Users не может сузить модератора до `group` или `individual`. Глобальный скоуп даёт ядровому `bouncer()->getAuthorizedUserIds()` `null`, то есть все лиды, все карточки контактов и все активности стола; `Bouncer` пакета на модератора ничего не меняет. |
| Баланс | Флаг `supervisor` снимает требование владения: `BalanceAdjustmentAccess` пускает роль к любому лиду, который покрывает data-scope (`bouncer()->getAuthorizedUserIds()` действующего аккаунта). Модератор на `global` двигает деньги любого лида; `permission_type = all` остаётся отдельным правилом администратора. |
| Чего нет | Settings (включая Users, Roles и Groups), Configuration, Products, Quotes, Mail, Dashboard, удаление лидов — 401, как и у оператора. Пункт «Settings» в меню не печатается, потому что у роли нет ключа `settings`. |
| Проверка | `ModeratorRoleTest` (12 кейсов): надмножество прав оператора, `OperatorScope::scope('moderator') === 'global'` при запинненном операторе, перезапись формы в `global`, флаги `supervisor` / `assign_creator_as_lead_owner`, состав меню, доступные экраны + раскрытая карта выплаты и страница депозита, 401 на Settings/Users/Roles/Groups/Configuration, все лиды/контакты/активности двух операторов при `global`, права на баланс любого лида, `--all` создаёт обе роли, отказ `--all` + `--name` и неизвестного ключа. |

**Брендинг (с 17.10.2026, знак и имя — CRYPTOISK):** четыре независимые
поверхности, у каждой своя точка расширения, правок ядра нет.

| Поверхность | Механизм |
|---|---|
| Лого в шапке и в мобильном drawer'е | `View::prependNamespace('admin', .../Resources/views/overrides)` внутри `$this->app->booted()` → наши копии `components.layouts.header.index` и `components.layouts.sidebar.mobile.index` находятся раньше ядра, все остальные `admin::*` падают в оригинал. |
| Лого логина / forgot-password / reset-password | Копии `sessions/{login,forgot-password,reset-password}` в том же `overrides`-каталоге (тот же `prependNamespace`): от ядра отличаются **только** блоком лого — вместо `images/logo.svg` стоит `@include('crypto_call_center::components.branding.session-logo')`. Партиал печатает логотип из Configuration, иначе лок-ап «знак + CRYPTOISK» (`h-12 w-12` / `text-2xl`); размеры лок-апа задаёт вызывающий: `components.branding.logo` принимает `markClass` и `textClass` (дефолт `text-lg`). `View::prependNamespace` применяется и к именам `sessions.*`, поэтому копии подхватываются без правки Admin. |
| Футер авторизованных страниц | Печатается значением `core()->getConfigData('general.settings.footer.label')`, поэтому в провайдере подменяется **дефолт** поля в `config('core_config')`: БД не пишется, а в Configuration → General → Settings текст остаётся редактируемым. Строка всегда английская (`brandingLine()` читает ключ с явной локалью `en`), в том числе на русских экранах. |
| Футер логина / forgot-password / reset-password | `View::composer('admin::sessions.*')` + `Translator::addLines()` для `admin::app.components.layouts.powered-by.description`. Группа `admin::app` сначала грузится явно (`load('admin', 'app', $locale)`), иначе частичный массив затирает остальные строки группы и экран печатает сырые ключи. Текст берётся из `brandingLine()`, то есть тоже английский в любой локали. |

Загруженный вручную логотип (Configuration → General → Settings) по-прежнему
главнее ребрендинга — это осознанно: и в шапке/drawer'е, и на трёх экранах
входа проверка `general.general.admin_logo.logo_image` стоит первой.

**Русификация (с 17.10.2026):** `ru` — локаль по умолчанию, правок ядра нет.

| Что | Механизм |
|---|---|
| Список локалей | `registerRussianLocale()` дописывает `ru => Русский` в `config('app.available_locales')` (источник `core()->locales()`) и сортирует список. |
| Локаль по умолчанию | `defaultLocaleToRussian()` подменяет **дефолт** поля `general.general.locale_settings.locale` в `config('core_config')`; значение, сохранённое в БД, по-прежнему главнее. |
| Строки ядра | `registerRussianTranslations()` обходит `Resources/lang/overrides/ru/{namespace}/{group}.php`: сначала `$translator->load(...)`, затем `addLines(Arr::dot([$group => require $file]))`. **Ключи обязаны быть вложены под группу**: `addLines()` режет ключ по первой точке и считает этот сегмент группой, поэтому плоский массив затёр бы всю группу `admin::app` и непереведённые строки печатались бы сырыми ключами. |
| Футер авторизованных страниц | `localizeFooterLabel()` пишет строку в **живой** конфиг `settings.footer.label` (+ слушатель `LocaleUpdated`, т.к. локаль ставится middleware на каждый запрос): `SystemConfig::getDefaultConfig()` берёт фолбэк именно оттуда, а сам массив `core_config` кэшируется при первом `getConfigData()` — это происходит в locale-middleware, до того как локаль запроса известна. Сохранённый в Configuration текст по-прежнему главнее. Строка **не зависит от локали**: `brandingLine()` всегда читает англоязычный ключ. |
| Сообщения валидации/авторизации | `lang/ru/*.php` в корне проекта (Laravel ищет `lang/{locale}`). |
| Клиентская валидация (vee-validate) | **Не переведена**: `Admin/src/Resources/assets/js/plugins/vee-validate.js` передаёт в `localize()` только `ar, en, es, fa, tr` (`ru.json` импортирован, но не зарегистрирован), а без `node_modules` ассеты `public/admin/build` не пересобрать — см. §9. |

**Подписи из БД (с 18.10.2026):** часть интерфейса берётся не из lang-файлов, а
из строк БД, поэтому переводится только командой (правок ядра нет).

| Что | Почему файла переводов недостаточно |
|---|---|
| Заголовки полей (`attributes.name`) | Подпись поля формы, колонки и истории — это `name` строки атрибута, а сид установщика пишет её по-английски; `admin::app.*` на неё не влияет. |
| Опции справочников (`lead_sources`, `lead_types`, `lead_pipelines`, `lead_pipeline_stages`) | Название опции — поле `name` её строки, поэтому «Phone» / «New Business» / «Default Pipeline» остаются английскими. Команда ищет строку по английскому `name`, так что вручную переименованная опция просто не находится и остаётся как есть. |
| Уже сохранённая история | `Traits\LogsActivity` записывает подпись поля и подпись опции **в момент сохранения** (`activities.title`, `additional.attribute`, `additional.new/old.label`), поэтому после переименования справочников старые записи остались бы английскими навсегда. Команда трогает только `type = 'system'`, разрешает сущность по пивоту и подменяет имя только хвостовым совпадением в заголовке («Обновлено: Source» → «Обновлено: Источник»), сохраняя остальные ключи payload. Отдельно пересобирается подпись даты: ядро форматирует её в `D M d, Y` до записи, поэтому команда восстанавливает дату из сырого `value` (`Tue Oct 20, 2026` → `20.10.2026`), а неразбираемое значение оставляет как есть. |
| Свои опции (`attribute_options`) | Не переводятся: это словарь заказчика, а не текст поставки. |
| Повторный запуск | `php artisan crypto-call-center:localize-catalog` идемпотентна, `--dry-run` печатает только план, `--skip-history` не трогает историю, `--force` перезаписывает поле, переименованное вручную. После `migrate:fresh --seed` команду нужно запустить снова. |

Покрытие: **все** листья `admin::app.*` из `Admin/src/Resources/lang/en/app.php`
переведены (~2540 строк в `overrides/ru/admin/app.php`), кроме
`components.layouts.powered-by.description` — брендинг остаётся английским по
требованию. Инвариант закреплён тестом
`LocaleTest → it('translates every admin line except the branding footer')`,
который сравнивает плоские списки ключей `en` и `ru` (ни пропусков, ни лишних
ключей). Пакетные строки `crypto_call_center::app.*` переведены полностью.

**Демо-данные (с 18.10.2026):** пустую установку наполняет сидер пакета,
правок ядра нет.

| Что | Как устроено |
|---|---|
| Запуск | `php artisan crypto-call-center:demo-data`; `--fresh` — заменить прежние демо-строки, `--no-calls` — без истории звонков. Повторный запуск без `--fresh` идемпотентен: печатает, что данные уже есть. |
| Что создаётся | 10 персон (`demo.klimiata.test`), 15 лидов (4 `new`, 3 `follow-up`, 2 `prospect`, 2 `negotiation`, 2 `won`, 2 `lost`; 4 закрытых, суммы 100–5000 $), 5 кошельков (BTC/ETH/TRON) и 8 звонков (5 завершённых, 2 пропущенных, 1 неудачный). |
| Как помечены строки | Домен `demo.klimiata.test` в `persons.unique_id`; лиды находятся через `person_id`, кошельки и звонки — через `lead_id`. Отдельной таблицы учёта нет, поэтому удаление демо-набора не задевает данные разработчика. |
| Требования | Аккаунт с ролью `permission_type = all`, дефолтный пайплайн с шестью стадиями, хотя бы один источник и один тип лида (иначе запуск падает с объяснением и ничего не пишет — все вставки в одной транзакции). |
| Зачем | Без данных канбан, виджеты дашборда, экран кошельков и история звонков показывают пустые колонки; сид идёт через репозитории, поэтому история активности, `unique_id` и контактные данные ведут себя как при ручном сохранении. |

**События торгового приложения (с 18.10.2026):** отдельное приложение
`crypto-trading` (Laravel 12, `D:\KLIMIATA\PROJECT\crypto-trading`, порт 8001)
работает в **той же БД `krayin_crm`** и пишет журнал действий клиента в таблицу
`trading_events`. CRM её **только читает** — миграция принадлежит торговому
приложению, а `Webkul\CryptoCallCenter\Models\TradingEvent` лишь описывает
готовую таблицу (своей миграции у модели пакета нет).

| Что | Как устроено |
|---|---|
| Схема | `id`, `user_id` (FK → `users.id`, `ON DELETE CASCADE`), `event_type(64)`, `payload` (JSON), `created_at`; `updated_at` нет — строки append-only. Индексы `(user_id, created_at)` и `(event_type, created_at)`. |
| Типы событий | `deposit` (пополнение), `position.opened` (открытие позиции), `position.closed` (закрытие), `withdrawal.requested` (клиент запросил вывод в кабинете). Константы — `App\Models\TradingEvent::TYPE_*`. |
| `payload` | Снимок операции, чтобы CRM не джойнила торговые таблицы: `deposit` → `amount`, `balance_before`, `balance_after`; `position.opened` → `position_id`, `symbol`, `side`, `amount`, `entry_price`, `notional`, `balance_after` плюс `size_unit` (`coin` \| `usd`) и `requested_amount` — то, что клиент ввёл в кабинете, поэтому CRM не выводит единицы из `amount` (ключи добавлены позже, старые события читаются как раньше); `position.closed` → те же плюс `close_price`, `pnl`. Числа — `float` с округлением до 8 знаков. |
| Атомарность | `TradingService::recordEvent()` вызывается **внутри** того же `DB::transaction()` + `lockForUpdate()`, что и действие: отказ (нехватка средств, нет цены) не оставляет события, а откат транзакции убирает и событие. Закреплено тестом `TradingEventsTest::test_event_is_rolled_back_with_the_action`. |
| Миграция | `crypto-trading/database/migrations/2026_10_18_000004_create_trading_events_table.php` (таблица миграций приложения — `trading_migrations`, применено 4 миграции). |
| Чтение из CRM | `Contracts\TradingEvent` / `Models\TradingEvent` (+ прокси `TradingEventProxy`) построены на готовую таблицу **без собственной миграции** и подключены в `ModuleServiceProvider`. `Repositories\TradingEventRepository` отдаёт `pending()` (строки без записи в книге обработки), `latestForUser()` (блок на карточке), `markProcessed()` / `attachActivity()` (книга обработки) и `available()` (обе таблицы на месте). |

**Мост в таймлайн лида (с 18.10.2026):** торговое приложение делит с CRM БД, но
не процесс, поэтому событие не может быть поднято внутри CRM — журнал
**вычитывается по расписанию**, а книга обработки `trading_event_activities`
(миграция пакета, PK `trading_event_id`, `activity_id`, `lead_id`) делает прогон
идемпотентным. Сама `trading_events` не меняется.

| Что | Как устроено |
|---|---|
| Расписание | `registerTradingFeed()` в `$app->booted()` (только при `runningInConsole()`) вешает `crypto-call-center:sync-trading-events` на `everyMinute()->withoutOverlapping()`; `php artisan schedule:list` его показывает. В ядро (`routes/console.php`) ничего не дописано. |
| Команда | `crypto-call-center:sync-trading-events {--limit=200}`: `TradingEventFeed::dispatchPending()` читает `pending()` и на каждую строку поднимает `Events\TradingEventReceived`; в ответ печатает число прочитанных. Без `trading_events` команда печатает предупреждение и выходит с `0`. |
| Слушатель | `EventServiceProvider::$listen`: `TradingEventReceived` → `Listeners\TradingActivityListener`. Он в одной транзакции резервирует событие (`markProcessed()`, `insertOrIgnore` по PK книги — вставка и есть блокировка), пишет `system`-активность через `ActivityRepository::create()` и привязывает её к лиду, затем `attachActivity()`. Системная активность не редактируется и не удаляется из таймлайна. |
| Резолв лида | `Services\TradingLeadResolver::resolve($userId)`: последний по `created_at`/`id` лид с этим `user_id` (`leads.user_id` — владелец счёта, то есть клиент, а не оператор). Нет лида — событие **не** резервируется и попадёт в следующий прогон; чужой лид не используется. |
| Формулировка | `Support\TradingEventPresenter` — общий для таймлайна и карточки: суммы `$1 000` / `0.5 BTCUSDT` / `$60 000`, сторона и результат (`PnL > 0` → прибыль, `< 0` → убыток); текст пишется на языке `general.general.locale_settings.locale`, потому что у планировщика нет `admin_locale` из запроса. |
| Неизвестный тип | Резервируется **без** активности (`activity_id = NULL`): событие «припарковано» и не переобрабатывается на каждом прогоне. |
| Карточка лида | Слушатель `admin.leads.view.activities.before` (в `registerTradingFeed()`) добавляет шаблон `crypto_call_center::leads.trading-events` — блок «События из трейдинга» над таймлайном. Блок читает `latestForUser($lead->user_id, 5)` напрямую, поэтому сделка видна до следующего прогона расписания. Views ядра не переопределялись. |
| Установка без торговой части | Оба входа (`Event::listen` карточки и команда) проверяют `TradingEventRepository::available()` и молча выключаются, если таблиц нет. |
| Тесты | `tests/Feature/CryptoCallCenter/TradingEventFeedTest.php` (11 кейсов, 31 assertions): три типа событий и их формулировки, новый лид у оператора с несколькими лидами, идемпотентность повторной доставки, парковка неизвестного типа, событие аккаунта без лида остаётся в очереди, связка провайдера `TradingEventReceived` → слушатель, резолв контракта, блок на карточке, форматирование сумм. Событие собирается через `(new TradingEvent)->newFromBuilder([...])`, поэтому тесты не пишут в общую `trading_events`; тест карточки читает реальную таблицу и делает `markTestSkipped`, если торговой части нет. |

**Ручное управление балансом трейдера (с 18.10.2026):** `trading_accounts.balance_usd`
принадлежит торговому приложению, поэтому CRM не пишет в его таблицы, а
**просит** изменить баланс. Транспорт спрятан за контрактом
`Services\Balance\TradingBalanceGateway`, так что мост по HTTP и запись прямо в
общие таблицы — взаимозаменяемые реализации одного правила.

| Что | Как устроено |
|---|---|
| Блок на карточке | `registerBalanceBlock()` в провайдере вешает `crypto_call_center::leads.balance-adjustments` на тот же `admin.leads.view.activities.before`, что и лента событий. Блок печатается только при `BalanceAdjustmentAccess::allows()` **и** `TradingBridge::ready()`, показывает текущий баланс (`TradingAccountRepository::balanceFor()`, `null` без счёта или таблицы), форму «Сумма, USD» + «Причина», строку успеха из flash `balance_adjustment_success`, отказ из `balance_adjustment` и пять последних изменений с оператором и датой. Views ядра не переопределялись. |
| Маршрут | `POST admin/crypto-call-center/leads/{lead}/balance` → `admin.crypto_call_center.balance_adjustments.store` (`Http\Controllers\BalanceAdjustmentController::store`); форма — обычный POST с `@csrf`, поэтому работает без JavaScript: успех и отказ возвращаются `back()` на ту же карточку. |
| Транспорт | `driver = http` (по умолчанию) — `HttpTradingBalanceGateway` шлёт `POST {CRM_TRADING_URL}/api/balance/adjust` с Bearer `CRM_TRADING_TOKEN`, таймаутом `CRM_TRADING_TIMEOUT` (5 с) и телом `user_id`, `amount`, `reason`, `adjustment_id`, `operator_name`; ответ читается как `balance_before`, `balance_after`, `event_id`, `applied`. `driver = database` — `DatabaseTradingBalanceGateway` пишет в общие таблицы сам (нужны `trading_accounts` + `trading_events`, иначе `errors.unavailable`). |
| Отказы | `422` трейдинга отдаётся оператору дословно; `5xx` и `ConnectionException` подменяются прямой записью **только** при `CRM_TRADING_FALLBACK=true`, иначе причина уходит в `report()`, а в форму — `errors.unreachable`; пустые url/token — `errors.not-configured`; прочие не-2xx — `errors.rejected` со статусом. |
| Правила суммы | Обе реализации повторяют правила `TradingService::adjustBalance()`: нулевая сумма, пустая причина, потолок `max_amount` (по умолчанию $1 000 000) и запрет уходить в минус. `BalanceAdjustmentRequest` ловит те же границы **до** запроса (`amount` в `between:-max,max`, `after()` на ноль, причина 2–255), поэтому отказ по сумме не создаёт ни строки, ни события. |

| Идемпотентность | `adjustment_id` — это `id` строки `crypto_balance_adjustments`: трейдинг ищет событие `balance.adjustment` с `payload.reference` = этому id и на повторе возвращает `applied = false` с прежними балансами. Строка пишется **до** вызова (id нужен заранее) и удаляется, если трейдинг отказал (`TradingPlatformException`), — история не показывает неприменённое изменение. |
| Журнал изменений | Миграция пакета `2026_10_18_000100_create_crypto_balance_adjustments_table`: `lead_id` (FK → `leads`, cascade), `user_id`/`admin_id` (FK → `users`, `nullOnDelete` — история переживает удаление), `amount` signed `decimal(24,2)`, `balance_before`/`balance_after` `decimal(24,8)`, `trading_event_id`, `reason(255)`, timestamps, индексы `(lead_id, created_at)` и `(user_id, created_at)`. Модель/контракт/прокси — `Models\BalanceAdjustment`, выборка — `Repositories\BalanceAdjustmentRepository::latestForLead()`. |
| Таймлайн | `Services\BalanceAdjustmentService::adjust()` после успеха в одной транзакции дописывает балансы и `trading_event_id`, создаёт `system`-активность («Баланс изменён на +$50. Причина: …», комментарий «Баланс: $100 → $150») через `ActivityRepository` и привязывает её к лиду. Чтобы планировщик не написал ту же смену второй раз, событие сразу резервируется в книге обработки (`TradingEventRepository::markProcessed()` + `attachActivity()`); если строку уже заняла лента, её запись побеждает. |
| Событие в ленте | Трейдинг зеркалит изменение как `balance.adjustment` (`TradingEvent::TYPE_BALANCE_ADJUSTMENT`, payload `reference`, `amount`, `reason`, `operator_name`, `balance_before`, `balance_after`), поэтому `TradingEventPresenter` формулирует его и без CRM-активности: `adjustment-credit` / `adjustment-write-off` + `signedMoney()`, бейдж `badge.adjustment`. |

| Права | Ключ `crypto_call_center.balance` (`Config/acl.php`, строка `crypto_call_center::app.acl.balance`) маппится на этот POST-маршрут, поэтому роль без ключа получает **401** от bouncer'а ещё до валидации. `Support\BalanceAdjustmentAccess` требует ключ **и** владение лидом либо `permission_type = all`: держатель ключа без владения получает **403**, и блок ему не показывается. Роль «Call Center Operator» получает ключ из `Config/roles.php` (существующие роли — `operator-role --sync`). |
| Конфигурация | `Config/trading.php` мержится как `crypto_call_center.trading.*` из `CRM_TRADING_DRIVER`, `CRM_TRADING_URL`, `CRM_TRADING_TOKEN`, `CRM_TRADING_FALLBACK`, `CRM_TRADING_TIMEOUT`, `CRM_TRADING_MAX_AMOUNT`; `CRM_TRADING_TOKEN` обязан совпасть с `TRADING_BRIDGE_TOKEN` трейдинга. `Support\TradingBridge::ready()` повторяет признак, по которому работают гейтвеи: http — заполнены url+token, database — есть общие таблицы. |
| Установка без торговой части | Блок не рендерится (`ready()` = false), POST отвечает понятным сообщением (`errors.not-configured` / `errors.unavailable`), строка аудита не остаётся. |
| Тесты | `tests/Feature/CryptoCallCenter/BalanceAdjustmentTest.php` (11 кейсов / 60 assertions): начисление с аудитом и flash, `adjustment_id` = id строки, списание, ноль и потолок без вызова шлюза, чужой лид → 403, роль без ключа → 401, отказ трейдинга удаляет строку, повтор на общих таблицах применяет один раз, резерв зеркального события, блок у владельца и его отсутствие у чужого сотрудника. Шлюз подменяется через контейнер (`app()->instance(TradingBalanceGateway::class, …)`), поэтому CRM-прогон не требует запущенного трейдинга; два кейса на общие таблицы пропускаются, если торговой части нет. Полный прогон CRM: 279 тестов / 741 assertion; на стороне трейдинга — 26 тестов / 117 assertions, включая `BridgeBalanceTest` (6 кейсов / 25 assertions). `pint --test` чистый по затронутым путям обоих проектов (трейдинг — все 49 файлов); в корне CRM остаются 4 предсуществующих замечания `line_ending` в `lang/ru/{auth,pagination,passwords,validation}.php`, к пакету не относящиеся. |

**Самозапись клиента из торгового приложения (с 18.10.2026):** публичная форма
`GET/POST /register` живёт в `crypto-trading`, но пишет **напрямую в общую БД
`krayin_crm`** — CRM об этом не знает и правок не требует, потому что строки
создаются сырыми `DB::table()`-вставками в одной транзакции.

| Что | Как устроено |
|---|---|
| Записи | `users` (`status = 1`, пароль кладётся открытым — модель `User` кастует его в `hashed`) → `roles` (или переиспользование роли из `TRADING_CLIENT_ROLE_ID`) → `persons` → `leads` → `trading_accounts` (`balance_usd = 0`). Любая ошибка откатывает всё, аккаунт-полуфабрикат в CRM не остаётся. |
| Владелец лида | `leads.user_id` = id **клиента**. Иначе блок баланса и лента сделок на карточке (`TradingAccountRepository::balanceFor($lead->user_id)`, `TradingLeadResolver`) указали бы на счёт сотрудника. `Listeners\AssignLeadSalesOwner` при этом не срабатывает: это не Eloquent `create`, а вставка через query builder, и создаёт лид не человек, а приложение. |
| Роль клиента | `permission_type = custom`, `permissions = []`, `view_permission = individual` — аккаунт входит в CRM-таблицу `users`, но не получает ни одного ACL-ключа, поэтому админка для него закрыта (bouncer режет до валидации). Роль одна на всех, повторные регистрации её переиспользуют. |
| Справочники | `lead_pipelines` (по `is_default`), `lead_pipeline_stages` (`code = new`), `lead_sources` («Сайт», иначе первая), `lead_types` («Новый бизнес») ищутся запросами: id в разных установках разные. Нет воронки — лид всё равно создаётся, в лог пишется предупреждение. |
| Видимость для сотрудников | Лид виден тем, чья область данных его покрывает: `global`/`group` — сразу, `individual` — только собственные лиды. Автор записи — клиент, поэтому в «моих лидах» оператора регистрация не появится, пока админ не переназначит владельца или не расширит data scope. |
| Тесты | `crypto-trading/tests/Feature/ClientRegistrationTest.php` (9 кейсов): форма, редирект авторизованного, создание всех пяти строк, `$0.00` на дашборде, нормализация e-mail, дубль без побочных записей, валидация пароля и согласия, переиспользование роли, `leads.user_id` = клиент. |


**Очередь выплат (заявки на вывод, с 18.10.2026; решение в CRM — с 19.10.2026):**
таблицы `withdrawal_requests` / `withdrawal_cards` принадлежат торговому
приложению, поэтому CRM читает очередь и **пишет только решение**
(`pending` → `approved` / `rejected`, затем `approved` → `paid`). Сами по себе
одобрение и отказ денег не двигают: выплату делает бэк-офис трейдинга.
Закрытие выплаты (`approved →
paid`) — единственный переход, который списывает сумму с баланса, и делает это
торговая сторона, чтобы счёт оставался в одних руках.

| Что | Как устроено |
|---|---|
| Экран | `GET admin/crypto-call-center/withdrawals` → `admin.crypto_call_center.withdrawals.index` (`Http\Controllers\WithdrawalController::index`) отдаёт `crypto_call_center::withdrawals.index` с `x-admin::datagrid`; AJAX-запрос обслуживает `DataGrids\WithdrawalDataGrid` (свежие сверху, join `users` + `withdrawal_cards`). Пункт меню — `Config/menu.php` (`crypto_call_center.withdrawals`). |
| Раскрытие номера | `GET admin/crypto-call-center/withdrawals/{withdrawal}/card` → `...withdrawals.card` (`WithdrawalController::card`), `whereNumber`, 404 на неизвестный id. |
| Решение | `POST .../withdrawals/{withdrawal}/approve` и `.../reject` (`whereNumber`): `WithdrawalController::approve()` принимает необязательную `note` (≤255), `::reject()` — обязательную `reason` (3–255), правила живут в `Http\Requests\ApproveWithdrawalRequest` / `RejectWithdrawalRequest`. `Services\Withdrawal\WithdrawalReviewService` — единственный путь записи: заявку не в `pending` он отклоняет **до** обращения к шлюзу, заметку из пробелов превращает в `null`, при одобрении и отказе деньги не трогает. Успех — `withdrawal_success`, отказ — error bag `withdrawal` (форма возвращается на `withdrawals.card`). Формы печатаются только на `pending`; после решения страница показывает оператора, время и заметку клиента вместо кнопок. |
| Шлюз | `Services\Withdrawal\WithdrawalReviewGateway`; по умолчанию `HttpWithdrawalReviewGateway` — `POST {base_url}/api/withdrawals/review` с `request_id` / `status` / `note` / `reviewer_id` и Bearer `CRM_TRADING_TOKEN`. `422` трейдинга пробрасывается оператору **дословно** (правила перехода статусов принадлежат торговой стороне), `ConnectionException`/5xx/незаданный конфиг уходят в `DatabaseWithdrawalReviewGateway` **только** при `crypto_call_center.trading.fallback`, иначе — «заявка всё ещё pending, повторите». `Database...` пишет общую строку под `lockForUpdate()`; тот же статус → `false` (повтор потерянного ответа ничего не перезаписывает: `reviewed_at` и заметка остаются прежними), другая на уже решённой → `WithdrawalReviewException` с текущим статусом. Транспорт `database` — вырожденный режим: он закрывает заявку, но баланс не двигает, поэтому выплату в нём закрывать нельзя (платёж прошёл бы без списания). |
| Завершение (`paid`) | `POST .../withdrawals/{withdrawal}/complete` → `WithdrawalReviewService::complete()` шлёт шлюзу `paid`; трейдинг в той же транзакции, что и статус, списывает сумму с `trading_accounts.balance_usd` ровно один раз на переход, поэтому повтор (статус уже `paid`) в деньги не заходит. Если после одобрения счёт опустел, мост отвечает 422 «Недостаточно средств: сумма вывода $… больше доступного баланса $…», CRM печатает отказ **дословно**, заявка остаётся `approved` и закрывается после пополнения. Ответ моста несёт `balance_before` / `balance_after` (`null` у одобрения, отказа и повтора) — CRM их не хранит, ими проверяют списание. |
| CVV | Код, который клиент ввёл при оформлении, лежит в самой заявке (`withdrawal_requests.card_cvv`, открытым текстом — он нужен кассиру, а не хранится «на будущее»); печатается **только** на `withdrawals.card` рядом с маской карты. В очередь, экспорт и лог запросов он не попадает, у сохранённой карты секрета нет вовсе, у кошелька кода не бывает. |
| Права | Два ключа в `Config/acl.php`: `withdrawals` (очередь) и `withdrawals.card` (раскрытие номера, CVV, маршруты `approve`/`reject` и действие «Открыть заявку»). Второй **не выводится** из первого: без него страница отвечает 401, а решение — 401 (`FormRequest::authorize()` дублирует проверку маршрута). Роль оператора получает только `withdrawals` (`Config/roles.php`, выравнивание — `crypto-call-center:operator-role --sync`). |
| Маскирование | Очередь печатает `•••• •••• •••• 4242` + бренд (`datagrid/requisites.blade.php`) или **полный** адрес кошелька (публичные данные, нужен для сверки) — номер карты в грид, экспорт и лог не попадает никогда. Ветка выбирается по самим реквизитам, а не по `$row->method`: колонка `method` рендерится раньше и подменяет значение переведённой подписью. Неизвестный бренд/статус печатается как есть либо как `brands.unknown`, а не ключом перевода. |
| Ключ карт | `Support\CardCipher` открывает `v1:base64(nonce):base64(tag):base64(cipher)` (AES-256-GCM), ключ = `hash('sha256', содержимое секрета, true)`; секрет — `WITHDRAWAL_CARD_KEY` → `crypto_call_center.trading.withdrawal.card_key` (`Config/trading.php`). Пустой ключ → экран говорит «не настроено», чужой ключ → «не читается» (тег GCM не сходится), «сырой» `Crypt::` payload пробуется как fallback. CRM только расшифровывает: `Models\WithdrawalCard` хранит `pan_encrypted`/`fingerprint` в `$hidden`, `pan()`/`formattedPan()` отдают номер группами по 4. |
| Лента событий | `TradingEvent::TYPE_WITHDRAWAL_REQUESTED` (`withdrawal.requested`), бейдж `trading-events.badge.withdrawal`, заголовок `activity.withdrawal` с суммой, комментарий — `withdrawalRequisites()`: карта `**** 4242` (+бренд) либо кошелёк `TQn9Y2…bLSE`. Бейдж нейтральный (`label-inactive`): это деньги «наружу», зелёный остаётся за пополнением и плюсом по позиции. `balance()` читает `balance_after ?? balance` — payload заявки несёт только `balance`. В таймлайн лида событие попадает обычным слушателем и тоже **без номера карты**. |
| Установка без торговой части | `Repositories\WithdrawalRequestRepository::available()` (`Schema::hasTable` на обе таблицы) — экран объясняет отсутствие таблиц вместо падения грида, `findWithCard()` возвращает `null`. |
| Тесты | `tests/Feature/CryptoCallCenter/WithdrawalFeedTest.php` (12 кейсов / 43 assertions): маска в очереди и отсутствие PAN, полный кошелёк, 401 без `withdrawals`, раскрытие только с `withdrawals.card`, действие «Открыть заявку» только с правом, «не настроено»/«не читается», 404, резолв контракта и `findWithCard()`, формулировки презентера (карта и кошелёк), запись события в таймлайн лида. `WithdrawalReviewTest.php` (16 кейсов / 83 assertions): одобрение через шлюз и через общую таблицу, отказ с причиной, второе решение отклонено, недоступный трейдинг без fallback и с ним, повторная доставка (`applied = false`), форма запроса к мосту, дословный отказ трейдинга, CVV на странице заявки, закрытие выплаты и повторное закрытие. Таблицы трейдинга в тестах реальные, при их отсутствии кейсы делают `markTestSkipped`; мост всегда подменён (`Http::fake()` или стаб-объект), живое торговое приложение не вызывается. |


**Очередь пополнений (заявки на депозит, с 18.10.2026):** пополнение больше
не зачисляется само — клиент в кабинете оформляет заявку с картой, а баланс
двигает только подтверждение оператора. Таблицы `deposit_requests` /
`deposit_cards` принадлежат трейдингу, CRM их читает и **пишет только решение**.

| Что | Как устроено |
|---|---|
| Экран | `GET admin/crypto-call-center/deposits` → `admin.crypto_call_center.deposits.index` (`Http\Controllers\DepositController::index`) отдаёт `crypto_call_center::deposits.index` с `x-admin::datagrid`; AJAX обслуживает `DataGrids\DepositDataGrid` (свежие сверху, join `users` + `deposit_cards`, сумма через `TradingEventPresenter::money`). Пункт меню — `Config/menu.php` (`crypto_call_center.deposits`, sort 10). |
| Скоуп | Грид **намеренно** не фильтруется по `bouncer()->getAuthorizedUserIds()`: депозит — деньги клиента, у строки нет CRM-владельца, и видимость решает только ACL-ключ, а не область лидов (иначе модератор на `individual` не увидел бы очередь вовсе). |
| Права | Два ключа в `Config/acl.php`: `deposits` (очередь, маска карты, клиент, сумма) и `deposits.review` (маршруты `show`/`approve`/`reject` + действие «Открыть заявку»). Второй не выводится из первого: без него страница заявки отвечает 401, а решение — тоже 401 (`FormRequest::authorize()` дублирует проверку на всякий случай). |
| Раскрытие номера | `GET admin/crypto-call-center/deposits/{deposit}` → `...deposits.show`, `whereNumber`, 404 на неизвестный id; номер открывается только здесь (`CardCipher` + общий `WITHDRAWAL_CARD_KEY`), колонка `requisites` в гриде не searchable — иначе `LIKE` писал бы PAN в лог запросов. `Models\DepositCard` держит `pan_encrypted`/`fingerprint` в `$hidden`. |
| CVV | Код пополнения печатается на странице заявки (`deposits/show.blade.php`, `deposits.show.cvv`) из самой заявки (`deposit_requests.card_cvv`), не из сохранённой карты: в очередь, экспорт и лог он не попадает, страница — только с `deposits.review`. |
| Подтверждение | `Services\Deposit\DepositReviewService::approve()` в одной транзакции: `lockForUpdate()` строки → отказ, если статус не `pending` → `gateway->credit(user_id, amount, 'deposit:{id}')` → `status = approved`, `reviewed_by/at`, заметка. Ссылка `deposit:{id}` — ключ идемпотентности: трейдинг ищет её в `trading_events.payload.reference` и на повторе отвечает `applied = false`, поэтому потерянный ответ и повторное нажатие не платят дважды. |
| Отклонение | `reject()` пишет `status = rejected` и причину (`review_note`, 3–255 символов), шлюз не вызывается вовсе — деньги не двигаются, клиент читает причину в кабинете. |
| Шлюз | `Services\Deposit\DepositBalanceGateway`: `http` (`POST {base_url}/api/deposits/credit`, Bearer `CRM_TRADING_TOKEN`, таймаут `CRM_TRADING_TIMEOUT`) или `database` (прямая запись в общие таблицы, повторяет правила трейдинга). Ответ 422 трейдинга пробрасывается дословно и оставляет заявку `pending`; недоступность/5xx → fallback на прямую запись **только** при `crypto_call_center.trading.fallback` (`CRM_TRADING_FALLBACK`), иначе оператор видит ошибку и ничего не меняется. |
| Лента событий | `TradingEvent::TYPE_DEPOSIT_REQUESTED` (`deposit.requested`), бейдж `trading-events.badge.deposit-requested`, заголовок `activity.deposit-requested` с суммой, реквизиты — `depositRequisites()`: `**** 4242` (+бренд), никогда полный номер. В таймлайн лида событие попадает обычным слушателем. |
| Установка без торговой части | `Repositories\DepositRequestRepository::available()` (`Schema::hasTable` на обе таблицы) — экран объясняет отсутствие таблиц, `findWithCard()` возвращает `null`. |
| Тесты | `tests/Feature/CryptoCallCenter/DepositReviewTest.php` (10 кейсов / 61 assertion): резолв контракта и маска в очереди, 401 без `deposits`, скрытое действие и 401 на решение без `deposits.review`, полный номер только с правом, CVV рядом с картой заявки, зачисление через подменённый шлюз, отказ трейдинга оставляет `pending`, причина отказа без вызова шлюза, повторное решение отклонено, формулировки презентера. При отсутствии таблиц трейдинга кейсы делают `markTestSkipped`. |

**Sales Owner — только сотрудники (с 18.10.2026):** самозарегистрированный
клиент — обычная строка `users` CRM, поэтому до этого правки селектор «Sales
Owner» в лиде и фильтр списка предлагали и клиентов.

| Что | Как устроено |
|---|---|
| Кто клиент | `Support\TradingClients`: роль, которую создаёт трейдинг («Trading Client»), из `crypto_call_center.trading.client_roles.names` (`CRM_TRADING_CLIENT_ROLES`, фолбэки `TRADING_CLIENT_ROLE_NAME`) и `.ids` (`CRM_TRADING_CLIENT_ROLE_IDS`, `TRADING_CLIENT_ROLE_ID`); имена резолвятся в id запросом к `roles` (только если таблица есть). `filter()` — один `where`-group с `whereNull('users.role_id')`, чтобы аккаунт без роли не исчез из списка владельцев. |
| Модель и репозиторий | `Models\SalesOwner extends User` (та же `users`) с глобальным скоупом `staff-only`; `Repositories\SalesOwnerUserRepository extends UserRepository` — имя важно, `AttributeRepository::getLookUpOptions()` распознаёт users-lookup по подстроке `UserRepository` и навешивает `status = 1` + data-scope. Отдельный класс, а не правка ядра, потому что клиент должен остаться видимым в Settings → Users, на карточке лида и в ленте сделок. |
| Где подключено | `DataGrids\Overrides\LeadDataGrid::prepareColumns()` переписывает `filterable_options` колонки `sales_person` (`repository` → `SalesOwnerUserRepository`); `CryptoCallCenterServiceProvider::registerSalesOwnerLookup()` правит `attribute_lookups.users.repository` в конфиге, поэтому и селектор в форме лида, и `POST admin/attributes/lookup/users` отвечают только сотрудниками. |
| Проверка | `tests/Feature/CryptoCallCenter/SalesOwnerLookupTest.php` (6 кейсов / 15 assertions): определение клиента по роли, staff-only репозиторий, `AttributeRepository::getLookUpOptions('users')`, опции колонки `sales_person` и реальный HTTP-эндпоинт `admin.datagrid.look_up`. |


**Прокрутка сайдбара (с 19.10.2026):** после добавления «Пополнений» меню
перестало помещаться на экране ноутбука, а обёртка ядра
(`admin::components.layouts.sidebar.desktop.index`, `#admin-sidebar >
.journal-scroll`) объявлена как `h-[calc(100vh-100px)] overflow-hidden` — нижние
пункты просто обрезаются. Копировать сайдбар ради одного класса нельзя (это ещё
одна копия ядра к пересинхронизации), поэтому правка сделана CSS-правилом.

| Что | Как устроено |
|---|---|
| Вьюха | `Resources/views/partials/sidebar-scroll.blade.php` — один `<style data-crypto-call-center="sidebar-scroll">` c `@media (min-width: 1024px)` (Tailwind `lg`), применяется только к `.sidebar-not-collapsed #admin-sidebar > .journal-scroll`: свёрнутый рельс сохраняет `overflow-visible`, иначе его flyout-подменю не выйдет за 70px. Высота остаётся ядровой `calc(100vh - 100px)` — 60px шапки + 40px полосы сворачивания, `100vh - 80px` уехал бы под неё. |
| Как попадает в страницу | `CryptoCallCenterServiceProvider::registerSidebarScroll()`: `View::composer('admin::components.layouts.index')` + `$view->getFactory()->startPush('styles', ...)`. Именно composer, а не `@push` из вьюхи: `@stack('styles')` печатается в `<head>` до сайдбара и до слота страницы, поэтому push, добавленный позже, до стека не доходит, а composer срабатывает в начале рендера макета. |
| Скроллбар | Ядро прячет скроллбары у всех `.journal-scroll`; здесь тонкий (8px) возвращён (`scrollbar-width: thin` + `::-webkit-scrollbar-thumb`, `#4b5563` в тёмной теме) — иначе не видно, что меню продолжается. |
| Проверка | `tests/Feature/CryptoCallCenter/SidebarScrollTest.php`: правило ровно один раз и до `</head>` на странице кошельков, отсутствие селектора для свёрнутого рельса, страница логина без правила, а `admin::components.layouts.sidebar.desktop.index` резолвится в файл Admin (ядро не переопределено). |



**Переопределённые вьюхи ядра (перепроверять при апгрейде Krayin):**

- `overrides/components/layouts/header/index.blade.php` ←
  `packages/Webkul/Admin/src/Resources/views/components/layouts/header/index.blade.php`
- `overrides/components/layouts/sidebar/mobile/index.blade.php` ←
  `packages/Webkul/Admin/src/Resources/views/components/layouts/sidebar/mobile/index.blade.php`
- `overrides/partials/breadcrumbs.blade.php` ←
  `packages/Webkul/Admin/src/Resources/views/partials/breadcrumbs.blade.php`
- `overrides/errors/index.blade.php` ←
  `packages/Webkul/Admin/src/Resources/views/errors/index.blade.php`

Отличаются от ядра брендингом (+ null-guard на `#logo-image` в
dark/light-свопе, т.к. вордмарк — не `<img>`), а шапка ещё и ссылкой логотипа:
она ведёт на первый доступный роли пункт меню (`menu()->getItems('admin')->first()`
с фолбэком на дашборд), иначе роль без права `dashboard` получала бы 401 по
клику на лого. Крошки теряют корневую крошку «Дашборд», а страница ошибок ведёт
кнопкой на первый доступный раздел — по той же причине. После апгрейда ядра все
четыре копии нужно пересинхронизировать с новой версией Admin.

**Важная ловушка (проверено):** у модели `Webkul\Lead\Models\Lead` подключён
трейт `Webkul\Attribute\Traits\CustomAttribute`, чей `getAttribute()` трактует
любой неизвестный ключ как кастомный атрибут. Поэтому relation, добавленный
через `resolveRelationUsing('cryptoWallets', ...)`, **не отдаётся** по
`$lead->cryptoWallets` (возвращается `null`). Обратная сторона связи поэтому
реализована не на модели ядра, а запросом
`CryptoWalletRepository::findByLead($leadId)`.

---

## 7. Точки кастомизации (без правки ядра)

### 7.1 Иерархия способов (выбирать сверху вниз)

| # | Способ | Для чего | Риск при апгрейде |
|---|---|---|---|
| 1 | **Кастомные атрибуты** (EAV) | Новые поля: кошелёк, опыт трейдинга, KYC-статус и т.п. | нет |
| 2 | **Новый пакет** `packages/Webkul/{Name}/` | Новые сущности, экраны, бизнес-логика, интеграции | низкий |
| 3 | **Конфиги через пакет** (`menu.admin`, `acl`, `core_config`) | Пункт меню, права, настройки | низкий |
| 4 | **`view_render_event` + listener** | Вставка своего блока в существующую страницу | низкий |
| 5 | **`view()->prependNamespace('admin', ...)`** | Переопределение конкретного Blade-шаблона | средний (нужно сравнивать с новой версией) |
| 6 | **Правка пакета Admin** | Крайний случай | высокий (конфликт при merge) |
| 7 | **Правка `vendor/`** | — | **запрещено** |

### 7.2 Чек-лист создания нового пакета

Пример имени: `{Crypto}` → `packages/Webkul/Crypto/`, namespace `Webkul\Crypto`.

**Шаг 1. Структура**

```text
packages/Webkul/Crypto/
├── composer.json
└── src/
    ├── Config/{acl.php, core_config.php, menu.php}
    ├── Contracts/
    ├── Database/Migrations/
    ├── Http/Controllers/
    ├── Models/
    ├── Providers/
    │   ├── CryptoServiceProvider.php
    │   └── ModuleServiceProvider.php
    ├── Repositories/
    ├── Resources/
    │   ├── lang/{en}/...      # только потому, что есть views/
    │   └── views/
    └── Routes/admin.php
```

**Шаг 2. Регистрация (3 обязательных места)**

1. `composer.json` (корень) → `autoload.psr-4`:
   `"Webkul\\Crypto\\": "packages/Webkul/Crypto/src"`.
2. `bootstrap/providers.php` → `CryptoServiceProvider::class`.
3. `config/concord.php` → `CryptoModuleServiceProvider::class`.

После — `composer dump-autoload`. Path-repository `packages/*/*` уже настроен,
поэтому отдельный `composer require` не нужен.

**Шаг 3. `ModuleServiceProvider`** (Concord: модели + репозитории)

```php
namespace Webkul\Crypto\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;
use Webkul\Crypto\Models\Call;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        Call::class,
    ];
}
```

**Шаг 4. `CryptoServiceProvider`** (миграции, вьюхи, маршруты, конфиги, хуки)

```php
namespace Webkul\Crypto\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CryptoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'crypto');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'crypto');

        Route::middleware(['web', 'admin_locale', 'user'])
            ->prefix(config('app.admin_path'))
            ->group(__DIR__.'/../Routes/admin.php');

        Event::listen('admin.leads.index.header.before', function ($event) {
            $event->addTemplate('crypto::partials.lead-header');
        });
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/menu.php', 'menu.admin');
        $this->mergeConfigFrom(__DIR__.'/../Config/acl.php', 'acl');
        $this->mergeConfigFrom(__DIR__.'/../Config/core_config.php', 'core_config');
    }
}
```

**Шаг 5. Меню** (`Config/menu.php`) — ключ уникален, `route` существует,
иконка — из существующего набора `icon-*`:

```php
return [
    [
        'key' => 'crypto',
        'name' => 'crypto::app.layouts.crypto',
        'route' => 'admin.crypto.calls.index',
        'sort' => 8,
        'icon-class' => 'icon-activity',
    ],
];
```

**Шаг 6. ACL** (`Config/acl.php`) — ключи должны совпадать с ключами меню,
иначе пункт скрыт для ролей без `permission_type = all`:

```php
return [
    [
        'key' => 'crypto',
        'name' => 'crypto::app.acl.crypto',
        'route' => 'admin.crypto.calls.index',
        'sort' => 8,
    ],
];
```

**Шаг 7. Маршруты** (`Routes/admin.php`) — имена с префиксом `admin.crypto.`:

```php
use Illuminate\Support\Facades\Route;
use Webkul\Crypto\Http\Controllers\CallController;

Route::controller(CallController::class)->prefix('crypto/calls')->group(function () {
    Route::get('', 'index')->name('admin.crypto.calls.index');
    Route::get('create', 'create')->name('admin.crypto.calls.create');
    Route::post('create', 'store')->name('admin.crypto.calls.store');
});
```

**Шаг 8. UI** — Blade-вьюхи с `admin::` layout и своими Vue-компонентами
(см. 5.2). Для таблиц использовать `<x-admin::datagrid :src="route(...)" />`
и свой DataGrid-класс.

### 7.3 Вставка UI через `view_render_event` (без копирования шаблонов)

В ядре расставлены именованные точки (`Select-String -Path packages\Webkul\Admin\src\Resources\views -Pattern "view_render_event\("`),
например `admin.leads.index.header.before`, `admin.persons.edit.form.after`,
`admin.layout.content.before`, `admin.layout.head` и т.п.

```php
// В своём ServiceProvider::boot()
Event::listen('admin.leads.index.header.before', function ($event) {
    $event->addTemplate('crypto::partials.lead-header');
});
```

- Слушатель получает `Webkul\Core\ViewRenderEventManager`.
- В шаблоне доступны переменные родительской вьюхи.
- Это **предпочтительный** способ добавить кнопку/панель/поле на существующий экран.

### 7.4 Переопределение Blade-шаблонов ядра

```php
// boot(): только после регистрации вьюх Admin
$this->app->booted(function () {
    view()->prependNamespace('admin', __DIR__.'/../Resources/views/overrides');
});
```

Файл `Resources/views/overrides/leads/index.blade.php` заменит
`admin::leads.index`.

- Копировать **только** нужный шаблон, не весь каталог.
- Каждый такой файл — потенциальный конфликт при обновлении: фиксировать
  список переопределённых вьюх в разделе 10.
- Для мелких правок сначала попробовать `view_render_event`
  (добавление) или CSS через `@push('styles')`.

> Переводы ядра лежат одним файлом `Admin/src/Resources/lang/{locale}/app.php`.
> Переопределение через `lang/vendor/admin/...` заменит файл целиком —
> не делать; вместо этого использовать свои ключи `crypto::app.*`.

### 7.5 Таблицы (DataGrid)

- Базовый класс: `Webkul\DataGrid\DataGrid`; методы
  `prepareQueryBuilder`, `prepareColumns`, `prepareActions`, `processRequestedFilters`.
- Рендер: `<x-admin::datagrid :src="route('admin.x.datagrid')" />` — компонент
  сам делает AJAX-запросы, хранит сортировку/фильтры/пагинацию, умеет экспорт.
- Свои таблицы — в своём пакете (`Crypto\DataGrids\...`), не в Admin.
- Изменение **существующей** таблицы ядра (добавить колонку в `LeadDataGrid`)
  требует правки Admin-класса — избегать. Альтернативы:
  - кастомные атрибуты (попадают в **экспорт** лидов автоматически, в гриде
    скрыты — см. `LeadDataGrid::prepareQueryBuilder`);
  - отдельный экран/виджет со своей таблицей.

### 7.6 Кастомные атрибуты (главный инструмент для крипто-полей)

1. Settings → **Attributes** → выбрать сущность (`leads`, `persons`,
   `organizations`, `products`, `quotes`, `warehouses`).
2. Создать атрибут: код (`wallet_address`, `trading_experience`, `kyc_status`),
   тип (`text`, `textarea`, `select`, `multiselect`, `price`, `date`, `boolean`,
   `lookup`…), валидация, группа, сортировка.
3. Поле автоматически появляется в формах создания/редактирования и в
   карточке (рендерится компонентом `x-admin::attributes`).

Плюсы: **ноль кода, ноль миграций**, безопасно при апгрейде. Ограничения:
нельзя использовать в сложной бизнес-логике как обычную колонку (значения в
`attribute_values`), фильтрация в гридах ограничена.

### 7.7 Настройки, права, уведомления

- **Настройки**: `Admin/Config/core_config.php` → `core()->getConfigData('general.general.locale_settings.locale')`.
- **Права**: ключи ACL = ключи меню; проверка `bouncer()->hasPermission('crypto')`.
  `hasPermission()` строгое (`in_array`): роль обязана содержать и родительский
  ключ группы (иначе пункт меню не отрисуется), и ключи детей.
- **Роли из пакета**: объявлены в `CryptoCallCenter/src/Config/roles.php` —
  `operator` и `moderator` (второй через `inherits => operator`, поэтому его
  права всегда надмножество операторских). Создаются идемпотентно командой
  `php artisan crypto-call-center:operator-role [role]` (`--all` — все
  объявленные, `--sync` — обновить права существующей роли). Ручные правки роли
  без `--sync` не затираются.
- **Массовые действия**: ядро печатает их без проверки прав; свой грид
  оборачивается трейтом `HidesUnauthorizedMassActions`, который сопоставляет URL
  действия с маршрутом и спрашивает `acl()->getRoles()` + `bouncer()`.
- **Data-scope**: `user->view_permission` (`global`/`group`/`individual`) —
  учитывать в собственных запросах, иначе утечка чужих лидов.
- **Уведомления**: события `Webkul\Admin\Events\...` + `Notification`-модель,
  канал `notifications.{userId}` (Reverb/Pusher).
- **События домена** для интеграций: `lead.create.after`,
  `lead.update.after`, `activity.create.after`, `quote.create.after` —
  подписываться в своём пакете, не править слушатели ядра.
- **API**: `routes/api.php` (Sanctum) + `Admin/src/Routes/RestApi/*`.

### 7.8 Чего не делать

- Не редактировать `vendor/` и не коммитить его.
- Не править существующие миграции — только новые.
- Не менять `KRAYIN_VERSION` и `CHANGELOG.md` вне релизного коммита.
- Не отключать глобально CSRF, `SanitizeUrl`, ACL и data-scope.
- Не добавлять `Resources/lang/` пакету без `Resources/views/`.
- Не хардкодить ID ролей/пользователей/пайплайнов — только через настройки/репозитории.

---

## 8. Команды и рабочие сценарии

### 8.1 Ежедневные команды (PowerShell, из корня проекта)

```powershell
# Запуск
php artisan serve                       # http://127.0.0.1:8000/admin
php artisan krayin-crm:version          # 2.2.7
php artisan about                       # сводка окружения

# База данных
php artisan migrate
php artisan migrate:status
php artisan migrate:fresh --seed        # !!! удаляет ВСЕ данные, только для локальной пересборки

# Тесты (Pest)
php artisan test --compact
php artisan test --compact --filter=Lead
php -d xdebug.mode=off artisan test --compact    # без шума xdebug
./vendor/bin/pest tests/Feature/Leads/LeadPartialUpdateTest.php

# Стиль кода
./vendor/bin/pint --test
./vendor/bin/pint

# Автозагрузка (после нового класса/пакета)
composer dump-autoload

# Кэши
php artisan optimize:clear              # route/config/view cache

# Роли пакета (идемпотентно; --sync — перезаписать права существующей роли)
php artisan crypto-call-center:operator-role                          # только оператор
php artisan crypto-call-center:operator-role moderator                # только модератор
php artisan crypto-call-center:operator-role --all --sync             # все объявленные роли
php artisan crypto-call-center:operator-role --sync                   # права существующей роли
php artisan crypto-call-center:operator-role --sync-users             # data-scope аккаунтам роли

# Русские подписи в БД: поля, опции справочников и уже сохранённая история (идемпотентно)
php artisan crypto-call-center:localize-catalog
php artisan crypto-call-center:localize-catalog --dry-run        # только план, без записи
php artisan crypto-call-center:localize-catalog --skip-history    # не трогать историю активности
php artisan crypto-call-center:localize-catalog --force           # перезаписать поля, переименованные вручную

# Демо-данные: 10 персон, 15 лидов, 5 кошельков, 8 звонков (идемпотентно)
php artisan crypto-call-center:demo-data
php artisan crypto-call-center:demo-data --fresh                  # заменить прежние демо-строки
php artisan crypto-call-center:demo-data --no-calls               # без истории звонков

# Почта: показать транспорт и отправить тестовое письмо (пароль в выводе маскируется)
php artisan mail:test                                   # письмо на 5trgb4llki7h@mail.ru
php artisan mail:test --dry-run                         # только проверить .env, ничего не отправлять
php artisan mail:test ops@example.com --mailer=smtp     # другой адрес/мейлер, `--timeout=15` по умолчанию

# Почта: входящие (IMAP) — что настроено, какие папки видны, что будет импортировано
php artisan mail:inbox                                  # настройки + папки + 5 последних писем в каждой
php artisan mail:inbox --folders                        # только список папок и счётчики, без чтения писем
php artisan mail:inbox --days=10 --limit=20             # окно импорта и сколько писем печатать
php artisan inbound-emails:process                      # сам импорт (его же дёргает планировщик)

# Фронтенд Admin (только если правили JS/CSS)
cd packages/Webkul/Admin
npm install
npm run dev                             # vite watch → public/admin/build
npm run build
```

### 8.2 Тестовая среда — важные нюансы

- `.env.testing` **нет**; `phpunit.xml` переопределяет только кэш/очередь/сессию,
  но **не БД** → тесты идут в `krayin_crm` из `.env`.
- Все feature-тесты используют `DatabaseTransactions` — изменения
  откатываются, реальные данные не теряются.
- `migrate:fresh` в тестах **не используется** (нет `RefreshDatabase`), но
  всё равно **не запускать** `migrate:fresh` на рабочей БД.
- `tests/Pest.php` содержит хелперы `getDefaultAdmin()`,
  `actingAsSanctumAuthenticatedAdmin()`, `securityTestPipeline()`,
  `webFormAttributeFor()`; сьют рассчитан и на параллельный запуск
  (`php artisan test --parallel`), где БД создаются с суффиксами.

### 8.3 Типовые задачи → что делать

| Задача | Рецепт |
|---|---|
| Добавить поле в лид/контакт | Кастомный атрибут (7.6), без кода |
| Изменить логотип, название, цвет, футер | Configuration → General |
| Добавить пункт меню и экран | Новый пакет (7.2) + ACL |
| Добавить кнопку/блок на существующую страницу | `view_render_event` (7.3) |
| Переписать кусок существующей страницы | `prependNamespace` (7.4), зафиксировать в журнале |
| Новая таблица с фильтрами/экспортом | Свой DataGrid + `<x-admin::datagrid>` |
| Реакция на создание лида/сделку | Слушатель события домена в своём пакете |
| Внешняя интеграция по HTTP | Controller в своём пакете + `routes/api.php` (Sanctum) или admin-маршрут с ACL |
| Массовая рассылка/воронка | Штатные Campaigns/Quotes/Activities, не дублировать |

---

## 9. Открытые вопросы (TODO — уточнить до старта разработки)

Это не факты, а решения, которые ещё не приняты. При ответе — заполнять,
а не догадываться.

- [x] **Имя первого пакета**: `Webkul\CryptoCallCenter` (namespace
      `Webkul\CryptoCallCenter\`, каталог `packages/Webkul/CryptoCallCenter/src`).
- [ ] **Список крипто-атрибутов**: точные коды/типы (кошелёк, биржа, опыт,
      депозит, KYC, риск-профиль…) и к каким сущностям.
- [ ] **Воронка**: используются штатные `leads` + pipelines или отдельная
      сущность «звонок/сделка»?
- [ ] **Телефония/мессенджеры**: какая АТС/провайдер (интеграция через события
      `activity.create.after` / webhook-контроллер).
- [x] **Роли и видимость**: оператор — `view_permission = individual`
      (навязывается пакетом на пользователя, см. 6.5), удаление лидов запрещено,
      созданный им лид закрепляется за ним как sales owner; модератор — те же
      права плюс полная карта выплаты и баланс по всем видимым ему лидам, скоуп
      (`group`/`global`) выбирает админ; остальные роли пока штатные Krayin.
- [x] **Локализация RU**: `ru` добавлена пакетом `CryptoCallCenter` и стоит
      локалью по умолчанию (см. 6.5). Переведены логин / forgot / reset, шапка,
      сайдбар, дашборд, аккаунт и общие элементы таблиц; серверные сообщения
      (`lang/ru/*.php`) — тоже.
- [x] **RU-перевод остальных модулей**: лиды, контакты, товары, предложения,
      активности, почта, настройки, конфигурация, дашборд, меню, ошибки, экспорт,
      справка — переведены в
      `CryptoCallCenter/src/Resources/lang/overrides/ru/admin/app.php`.
      Полнота покрытия (1:1 с `en`, кроме строки брендинга) проверяется тестом.
- [ ] **vee-validate `ru`**: в `Admin/src/Resources/assets/js/plugins/vee-validate.js`
      добавить `ru` в объект `localize()` (`ru.json` уже импортирован, но не
      зарегистрирован) — клиентские сообщения валидации пока английские.
      Требует `npm install && npm run build`: без `node_modules` собранный
      `public/admin/build` не обновится.
- [x] **Брендинг**: `APP_NAME="Crypto Call Center"` в `.env`; логотип CRYPTO и
      футер KLIMIATA — из пакета `CryptoCallCenter` (переопределение шапки и
      мобильного drawer'а + дефолт `core_config` + строка `powered-by`), см. 6.5.
      Свой логотип и цвет по-прежнему задаются в Configuration → General → Settings.
- [x] **Связь торгового события с лидом**: общего ключа, кроме `users.id`, у
      двух приложений нет, поэтому событие привязывается к **последнему** лиду
      владельца события (`leads.user_id`, сортировка `created_at`/`id`), а не к
      сделке из payload. Нет лида — событие остаётся в очереди. Как читается
      журнал (расписание, книга обработки, блок на карточке) — см. 6.5.
- [ ] **IMAP mail.ru**: импорт входящих по-прежнему упирается в настройки ящика —
      сервер отвечает `NO [AUTHENTICATIONFAILED] ... Application password is
      REQUIRED`. Нужно включить доступ по IMAP в настройках ящика, создать пароль
      приложения, положить его в `IMAP_PASSWORD` (не в `MAIL_PASSWORD`, если они
      разные) и выполнить `php artisan config:clear`, после чего проверить
      `php artisan mail:inbox --folders` и `php artisan inbound-emails:process`.

---

## 10. Журнал выполненных задач

| Дата | Задача | Что сделано | Файлы |
|---|---|---|---|
| 2026-10-17 | Аудит проекта и база знаний для AI | Полный read-only аудит репозитория; создан `PROJECT_CONTEXT.md` (стек, карта каталогов, архитектура, точки кастомизации, команды) | `PROJECT_CONTEXT.md` |
| 2026-10-17 | PHP 8.5 deprecation ломал тесты | `PDO::MYSQL_ATTR_SSL_CA` заменён на версионно-зависимую константу; `php artisan test` снова зелёный | `config/database.php` |
| 2026-10-17 | Первый кастомный пакет `Webkul\CryptoCallCenter` + экран «Crypto wallets» | Создан изолированный пакет (PSR-4 `Webkul\CryptoCallCenter\` → `src/`): провайдеры (пакетный + Concord `ModuleServiceProvider`), контракт/модель/прокси/repository для `crypto_wallets` (`lead_id` FK → `leads`, `wallet_address`, `network`, unique `lead_id+address+network`), миграция, `Config/menu.php` + `Config/acl.php`, маршрут `admin/crypto-call-center/wallets`, `CryptoWalletDataGrid` на `Webkul\DataGrid\DataGrid`, blade `x-admin::datagrid`, переводы `en`. Зарегистрирован в `composer.json` (psr-4), `bootstrap/providers.php`, `config/concord.php`. Добавлен Pest-тест (4 кейса, включая ACL: 401 без права `crypto_call_center`), `CHANGELOG.md` пакета. Правок ядра нет. | `packages/Webkul/CryptoCallCenter/**`, `composer.json`, `bootstrap/providers.php`, `config/concord.php`, `tests/Feature/CryptoCallCenter/CryptoWalletTest.php` |
| 2026-10-17 | Ребрендинг админки: логотип CRYPTO, футер «Powered by KLIMIATA…» | Правок ядра нет, всё внутри пакета. Ключи `crypto_call_center::app.branding.name` / `powered-by` (единый источник текста) + партиалы `components/branding/{mark,logo}`. Лого в шапке и в мобильном drawer'е — переопределённые копии `admin::components.layouts.header.index` и `admin::components.layouts.sidebar.mobile.index` через `View::prependNamespace('admin', '../views/overrides')` в `$this->app->booted()` (+ null-guard на `#logo-image` в dark/light-свопе). Футер авторизованных страниц печатается из `core()->getConfigData('general.settings.footer.label')`, поэтому подменён **дефолт** поля в `config('core_config')` (в БД ничего не пишем, текст остаётся редактируемым в Configuration). Футер логина/forgot/reset — `View::composer('admin::sessions.*')` + `Translator::addLines()` для `admin::app.components.layouts.powered-by.description`, причём группа `admin::app` грузится заранее (иначе частичный массив затирает остальные строки и экран печатает ключи). Pest `BrandingTest` (2 кейса: авторизованная страница + логин, отсутствие `krayincrm.com`, живость остальных `admin::app.*`). `php artisan test --compact` — 211 тестов / 423 assertions зелёные, Pint чистый, `view:cache` собирается. **Позже в этот же день подмена дефолта футера заменена на локалезависимый `settings.footer.label` — см. следующую строку.** | `packages/Webkul/CryptoCallCenter/src/{Providers/CryptoCallCenterServiceProvider.php,Resources/lang/en/app.php,Resources/views/components/branding/*,Resources/views/overrides/components/layouts/**}`, `packages/Webkul/CryptoCallCenter/CHANGELOG.md`, `tests/Feature/CryptoCallCenter/BrandingTest.php` |
| 2026-10-17 | Русская локаль (`ru`) в админке | Добавлена локаль `ru`: `registerRussianLocale()` дописывает её в `app.available_locales` (источник `core()->locales()`), `defaultLocaleToRussian()` подменяет дефолт поля `general.general.locale_settings.locale` в `config('core_config')`. Строки ядра подмешиваются в переводчик из `Resources/lang/overrides/ru/{namespace}/{group}.php`: сначала `$translator->load()`, затем `addLines(Arr::dot([$group => ...]))` — ключи вложены под группу, иначе `addLines()` затирает всю группу `admin::app`. Серверные сообщения — `lang/ru/{auth,passwords,validation,pagination}.php` (+ `attributes`). Футер авторизованных страниц сделан локалезависимым: `localizeFooterLabel()` пишет строку в живой конфиг `settings.footer.label` (из него `SystemConfig::getDefaultConfig()` берёт фолбэк) и обновляется по `LocaleUpdated`, т.к. `core_config` кэшируется раньше, чем известна локаль запроса. Pest `LocaleTest` (5 кейсов), `CryptoWalletTest` больше не хардкодит английский заголовок, `HelpTest` читает заголовок «сырым» (экранированный дубль приходил из английской подписи сайдбара). Правок ядра нет. Полный прогон — 216 тестов / 441 assertion. | `packages/Webkul/CryptoCallCenter/src/{Providers/CryptoCallCenterServiceProvider.php,Resources/lang/ru/app.php,Resources/lang/overrides/ru/admin/app.php}`, `lang/ru/{auth,passwords,validation,pagination}.php`, `tests/Feature/CryptoCallCenter/{LocaleTest.php,CryptoWalletTest.php}`, `tests/Feature/HelpTest.php` |
| 2026-10-17 | Экран «История звонков» (`call_logs`) | Сущность звонка: контракт `Contracts\CallLog`, модель `Models\CallLog` (+прокси) со статусами `completed/missed/failed`, каст `duration` в секундах и `formatDuration()`, `belongsTo` на лида и менеджера; `CallLogRepository::findByLead()`; миграция `call_logs` (`lead_id` cascade, `user_id` nullOnDelete, `phone_number`, `duration`, `status`, `recording_url`, `transcript`, `call_date`, индексы `status` и `lead_id/user_id + call_date`). Экран: `CallLogController` + маршрут `admin.crypto_call_center.call_logs.index`, пункт меню и право `call_logs` (`icon-call`, sort 8) — ключи меню и ACL совпадают, поэтому middleware `user` сам отдаёт 401 роли без права. `CallLogDataGrid` сортирует по `call_date`, джойнит `leads`/`users`, ограничивает строки через `bouncer()->getAuthorizedUserIds()` (учитывает `view_permission` роли), фильтрует по диапазону дат/статусу/менеджеру, скрывает транскрипт по умолчанию, ссылку «Прослушать» печатает только при наличии `recording_url`, action «Открыть лид» — только при `leads.view`. Бейдж статуса — отдельная вьюха на уже собранных классах `label-active`/`label-inactive` (новые Tailwind-утилиты не добавлялись: `node_modules` нет, ассеты не пересобрать). Переводы `en`/`ru`, `ModuleServiceProvider` дополнен `CallLog`, Pest `CallLogTest` (6 кейсов). Правок ядра нет. Полный прогон — 222 теста / 467 assertions зелёные, Pint чистый. | `packages/Webkul/CryptoCallCenter/src/{Contracts/CallLog.php,Models/CallLog.php,Models/CallLogProxy.php,Repositories/CallLogRepository.php,Database/Migrations/2026_10_17_000100_create_call_logs_table.php,DataGrids/CallLogDataGrid.php,Http/Controllers/CallLogController.php,Routes/admin.php,Config/menu.php,Config/acl.php,Providers/ModuleServiceProvider.php,Resources/views/call-logs/**,Resources/lang/{en,ru}/app.php}`, `tests/Feature/CryptoCallCenter/CallLogTest.php`, `PROJECT_CONTEXT.md`, `packages/Webkul/CryptoCallCenter/CHANGELOG.md` |
| 2026-10-17 | Роль «Call Center Operator»: права в пакете, скрытие запрещённых разделов | Роль объявлена данными пакета, а не правкой ядра: `Config/roles.php` (`operator` → имя, описание, список ACL-ключей) + идемпотентная команда `crypto-call-center:operator-role` (создаёт `permission_type = custom`; без `--sync` существующую роль не трогает и печатает diff; падает на ключе, которого нет в `config('acl')`). Права: `leads`, `leads.view`, `leads.edit`; `contacts`, `contacts.persons`, `contacts.persons.view`, `contacts.organizations`; `activities`, `activities.create`; `crypto_call_center` — настроек, пользователей, ролей, товаров, предложений, почты, дашборда и истории звонков в списке нет. Скрытие разделов — следствие ядрового `Menu::getItems('admin')` (пункт печатается только при `bouncer()->hasPermission($key)`), но `hasPermission()` строгое, поэтому в роли есть и родительские ключи групп (`contacts`), и дети. Найденная дыра: ядро печатает массовые действия гридов без проверки прав («Удалить» на лидах/активностях/организациях отдавало 401 только по факту запроса) — добавлен трейт `HidesUnauthorizedMassActions` (URL действия → URI зарегистрированного маршрута → `acl()->getRoles()` → `bouncer()`) и наследники `LeadDataGrid`/`ActivityDataGrid`/`OrganizationDataGrid`, забинденные в провайдере на имена ядровых классов. Без `dashboard` лендинг после логина идёт на первый доступный пункт меню (`SessionController`), поэтому лого в нашей override-шапке тоже ведёт на первый доступный пункт, а не на дашборд. Pest `OperatorRoleTest` (8 кейсов, включая ссылки в крошках и на странице ошибок). Полный прогон — 234 теста / 534 assertions зелёные, Pint чистый. | `packages/Webkul/CryptoCallCenter/src/{Config/roles.php,Console/Commands/ProvisionOperatorRole.php,DataGrids/Concerns/HidesUnauthorizedMassActions.php,DataGrids/Overrides/LeadDataGrid.php,DataGrids/Overrides/ActivityDataGrid.php,DataGrids/Overrides/OrganizationDataGrid.php,Providers/CryptoCallCenterServiceProvider.php,Resources/views/overrides/{components/layouts/header/index.blade.php,partials/breadcrumbs.blade.php,errors/index.blade.php}}`, `tests/Feature/CryptoCallCenter/OperatorRoleTest.php`, `PROJECT_CONTEXT.md`, `packages/Webkul/CryptoCallCenter/CHANGELOG.md` |
| 2026-10-17 | Полная русификация админки + футер всегда английский | `overrides/ru/admin/app.php` доведён до ~2540 строк: переведены `activities`, `mail`, `common`, `validations`, `export`, `errors` (401/403/404/500/503), `user.account`, `emails`, `help`, `leads` (index/datagrid/kanban/create/edit/common/view/products/persons/stages/tags/file), `configuration` (general/locale/admin-logo/settings/menu/dashboard/notifications/menu-color/email-imap/magic-ai) и ранее начатые группы. Покрытие теперь 1:1 с `Admin/src/Resources/lang/en/app.php` — закреплено тестом `LocaleTest → it('translates every admin line except the branding footer')` (сравнение плоских ключей: ни пропусков, ни лишних). Брендинг сделан языконезависимым: `CryptoCallCenterServiceProvider::brandingLine()` читает `crypto_call_center::app.branding.powered-by` с явной локалью `en`, и `localizeFooterLabel()`/`overridePoweredByLine()` используют только его, поэтому футер «Powered by KLIMIATA…» остаётся английским и на `ru` (логин, forgot/reset, авторизованные страницы) — значение в `Resources/lang/ru/app.php` приведено к английскому тексту. Хелпер `useAdminLocale()` перенесён в `tests/Pest.php` (нужен нескольким файлам), `LocaleTest` дополнен рендером лидов/почты/активностей и конфигурации (сырые ключи ищутся только в текстовых узлах: метаданные `core_config` попадают в `field-data`-атрибуты), `BrandingTest` — кейсом про русские экраны, `HelpTest` — фиксацией локали `en` (страница теперь переведена). Полный прогон — 227 тестов / 491 assertion, Pint чистый. Правок ядра нет. | `packages/Webkul/CryptoCallCenter/src/{Providers/CryptoCallCenterServiceProvider.php,Resources/lang/ru/app.php,Resources/lang/overrides/ru/admin/app.php}`, `tests/Pest.php`, `tests/Feature/{HelpTest.php,CryptoCallCenter/LocaleTest.php,CryptoCallCenter/BrandingTest.php}`, `PROJECT_CONTEXT.md` |
| 2026-10-18 | Роль оператора: только свои лиды + запрет удаления | Скоуп объявлен в `Config/roles.php` (`view_permission = individual`), но Krayin хранит data-scope на **пользователе**, поэтому значение навязывается, а не документируется: новый `Support/OperatorScope` перезаписывает `view_permission` в слушателе `User::saving` (регистрируется `pinOperatorScope()` в `boot()` провайдера), то есть форма Settings → Users, импортёр, сидер и tinker одинаково не могут расширить оператора до `group`/`global`. Грид лидов по-прежнему фильтруется ядровым `bouncer()->getAuthorizedUserIds()`. Команда `crypto-call-center:operator-role` падает на неизвестном скоупе (молча игнорировать его грид не должен) и получила `--sync-users` для ретрофита уже существующих аккаунтов; без флага только сообщает, сколько расходятся. Удаление лида закрыто на двух уровнях: права `leads.delete`/`leads.mass_delete` роли не выдаются, строку «Удалить» ядро печатает только при наличии права, массовое действие отфильтровано `HidesUnauthorizedMassActions`, а сам `DELETE admin/leads/{id}` отвечает 401 и на свой, и на чужой лид — Blade-переопределение не понадобилось. Новый `OperatorScopeTest` (8 кейсов: объявленный скоуп и отсутствие `leads.delete`/`mass_delete`, пиннинг при создании/обновлении, неприкосновенность других ролей, `--sync-users`, отказ на неизвестном скоупе, грид только со своими лидами, скрытая строка «Удалить», 401 на оба `DELETE`). В `OperatorRoleTest` фикстура переведена на `individual`, а кейс с неизвестным ACL-ключом проверяет уникальное имя роли: на dev-БД роль уже создана командой, и проверка «роли нет» по умолчанию ложно падала. Полный прогон — 243 теста / 566 assertions зелёные, Pint чистый. Правок ядра нет. | `packages/Webkul/CryptoCallCenter/src/{Config/roles.php,Support/OperatorScope.php,Providers/CryptoCallCenterServiceProvider.php,Console/Commands/ProvisionOperatorRole.php}`, `packages/Webkul/CryptoCallCenter/CHANGELOG.md`, `tests/Feature/CryptoCallCenter/{OperatorScopeTest.php,OperatorRoleTest.php}`, `PROJECT_CONTEXT.md` |
| 2026-10-18 | Русификация подписей из БД: поля, справочники, история активности | Часть админки печатала английский текст не из lang-файлов, а из строк БД: подпись поля — это `attributes.name`, название опции — `name` её lookup-строки, а `Traits\LogsActivity` и вовсе замораживает и то и другое в `activities.title` / `additional.attribute` / `new`-`old.label` в момент сохранения, поэтому перевод `admin::app.*` до них не достаёт. Добавлен каталог `Config/localization.php` (`en`+`ru` для полей `leads`/`persons`/`organizations`/`products`/`quotes`/`warehouses` и опций `lead_sources`/`lead_types`/`lead_pipelines`/`lead_pipeline_stages`; английский текст служит «сторожем», свои `attribute_options` не трогаются) и идемпотентная команда `crypto-call-center:localize-catalog`: атрибуты переименовываются по `entity_type`+`code` только пока несут английское имя (поле, переименованное вручную, сохраняется; `--force` перезаписывает), опции — по английскому `name` (если русское имя уже занято уникальным индексом, печатается предупреждение и строка пропускается вместо слияния), история — только `type = 'system'`, с разрешением сущности по пивотам `lead_/person_/product_/warehouse_activities` (общий «Name» товара не получает подпись персоны) и подменой имени только хвостовым совпадением в заголовке («Обновлено: Source» → «Обновлено: Источник»), остальные ключи payload сохраняются. Отдельно пересобирается подпись даты: ядро форматирует её в `D M d, Y` ещё до записи, поэтому дата берётся из сырого `value` и выводится как `20.10.2026` (`20.10.2026 14:30` для `datetime`), а неразбираемое значение сохраняет прежнюю подпись. `--dry-run` показывает план, `--skip-history` не трогает историю; счётчики сбрасываются в `handle()`, иначе повторный вызов в одном процессе (тесты, `Artisan::call` в цикле) печатал бы суммы прошлого прогона. Конфиг подключён `mergeConfigFrom` (`crypto_call_center.localization`), команда зарегистрирована в провайдере. На dev-БД прогнано по-настоящему: 41 поле, 14 опций, 17 записей истории (16 подписей полей и одна дата), повторный прогон — `0/0/0`. Новый `CatalogLocalizationTest` (10 кейсов, включая пропуск опции с занятым русским именем и обе ветки подписи даты). Полный прогон — 253 теста / 611 assertions зелёные. Правок ядра нет. | `packages/Webkul/CryptoCallCenter/src/{Config/localization.php,Console/Commands/LocalizeCatalog.php,Providers/CryptoCallCenterServiceProvider.php}`, `packages/Webkul/CryptoCallCenter/CHANGELOG.md`, `tests/Feature/CryptoCallCenter/CatalogLocalizationTest.php`, `PROJECT_CONTEXT.md` |

| 2026-10-18 | Демо-данные для чистой установки: 10 персон / 15 лидов / 5 кошельков / 8 звонков | Пустая установка показывала пустой канбан, виджеты дашборда, кошельки и историю звонков. Добавлен `Database/Seeders/DemoDataSeeder` и команда `crypto-call-center:demo-data` (`--fresh`, `--no-calls`): строки пишутся через репозитории ядра и пакета, поэтому `unique_id` персоны, контакты и записи истории активности — те же, что при ручном сохранении. 15 лидов разложены по шести стадиям (4 `new`, 3 `follow-up`, 2 `prospect`, 2 `negotiation`, 2 `won`, 2 `lost`; 4 закрытых, суммы 100–5000 $), у пяти лидов кошелёк BTC/ETH/TRON, у восьми — звонок (5 завершённых, 2 пропущенных, 1 неудачный). Признак своих строк — домен `demo.klimiata.test` в `persons.unique_id`, поэтому повторный запуск идемпотентен (печатает, что данные уже есть), а `--fresh` удаляет только прежний демо-набор (сначала лиды, затем персоны; кошельки и звонки уходят по каскаду FK). Владелец — первый аккаунт с `permission_type = all`, пайплайн — дефолтный; отсутствие админа, пайплайна, стадии, источника или типа валит запуск с объяснением, а не пишет половину набора (все вставки в одной транзакции), счётчики сбрасываются в `run()`. На dev-БД прогнано по-настоящему: `--fresh` печатает `Seeded 10 persons, 15 leads, 5 crypto wallets and 8 call logs.`, повторный запуск — `Demo data is already present…`, фактические строки сверены запросом. `DemoDataSeederTest` (4 кейса) закрепляет набор, идемпотентность и `--fresh`, рендер канбана лидов и дашборда и команду с `--no-calls`; хелпер `completeDemoPipeline()` дорисовывает шесть стадий в `securityTestPipeline()`, счётчики проверяются только по домену демо-данных. Полный прогон — 257 тестов / 650 assertions зелёные, Pint чистый. Правок ядра нет. | `packages/Webkul/CryptoCallCenter/src/{Database/Seeders/DemoDataSeeder.php,Console/Commands/SeedDemoData.php,Providers/CryptoCallCenterServiceProvider.php}`, `packages/Webkul/CryptoCallCenter/CHANGELOG.md`, `tests/Feature/CryptoCallCenter/DemoDataSeederTest.php`, `PROJECT_CONTEXT.md` |

| 2026-10-18 | Журнал событий `trading_events` для CRM | Торговое приложение `crypto-trading` (та же БД `krayin_crm`) публикует действия клиента в `trading_events` (`id`, `user_id` FK → `users` cascade, `event_type(64)`, JSON `payload`, только `created_at`): `deposit` (`amount`, `balance_before`, `balance_after`), `position.opened` и `position.closed` (`position_id`, `symbol`, `side`, `amount`, `entry_price`, `close_price`, `notional`, `pnl`, `balance_after`). Событие пишется в `TradingService::recordEvent()` **внутри** того же `DB::transaction()` + `lockForUpdate()`, что и действие, поэтому отказ или откат транзакции не оставляет «фантомного» события. Модель `App\Models\TradingEvent` с `$fillable` (`user_id`, `event_type`, `payload`), cast `payload => array`, `UPDATED_AT = null`, константы `TYPE_*`. Миграция аддитивная (4-я в `trading_migrations`), схема сверена: `id, user_id, event_type, payload, created_at` + индексы `(user_id, created_at)` и `(event_type, created_at)`. Новый `TradingEventsTest` (5 кейсов: три события, отсутствие события при отказе, откат события вместе с действием) — полный прогон 20 тестов / 92 assertions, Pint 44 файла чистый. Кода и миграций CRM не менялось: таблица — общий контракт, читается из той же БД; экран/грид событий в `CryptoCallCenter` — следующий шаг. | `crypto-trading/{app/Models/TradingEvent.php,app/Services/TradingService.php,database/migrations/2026_10_18_000004_create_trading_events_table.php,tests/Feature/TradingEventsTest.php,PROJECT_CONTEXT.md,CHANGELOG.md}`, `PROJECT_CONTEXT.md` |
| 2026-10-18 | События трейдинга в таймлайне лида + блок «События из трейдинга» на карточке | Журнал `trading_events` теперь доводится до CRM без правок ядра. Чтение: `Contracts/TradingEvent`, `Models/TradingEvent` + `TradingEventProxy` на готовую таблицу (своей миграции нет), `Repositories/TradingEventRepository` (`pending()`, `latestForUser()`, `markProcessed()`, `attachActivity()`, `available()`); книга обработки — миграция пакета `trading_event_activities` (`trading_event_id` PK, `activity_id` nullable, `lead_id`). Мост: `Services/TradingEventFeed` читает `pending()` и поднимает `Events/TradingEventReceived`, `Listeners/TradingActivityListener` в одной транзакции резервирует событие (`insertOrIgnore` — вставка и есть блокировка), пишет `system`-активность через `ActivityRepository::create()` и привязывает её к лиду. Лид — `Services/TradingLeadResolver` (последний по `created_at`/`id` лид оператора; нет лида — событие не резервируется и ждёт следующего прогона). Формулировки — `Support/TradingEventPresenter` (`$1 000`, `0.5 BTCUSDT`, `$60 000`, прибыль/убыток по `pnl`), язык берётся из `general.general.locale_settings.locale`, т.к. у планировщика нет `admin_locale`. Неизвестный тип паркуется (`activity_id = NULL`) и не переобрабатывается. Команда `crypto-call-center:sync-trading-events {--limit=200}` + расписание `everyMinute()->withoutOverlapping()` из `$app->booted()` (`schedule:list` показывает; в `routes/console.php` ничего не дописано). Карточка лида: `Event::listen('admin.leads.view.activities.before')` добавляет `crypto_call_center::leads.trading-events` — блок читает `latestForUser($lead->user_id, 5)`, поэтому сделка видна до прогона расписания; views ядра не переопределялись. Оба входа проверяют `available()` и молча выключаются без торговой части. Новый `TradingEventFeedTest` (11 кейсов / 31 assertions): три типа событий, новый лид при нескольких лидах, идемпотентность, парковка неизвестного типа, аккаунт без лида, связка провайдера, резолв контракта, блок на карточке (пропускается без `trading_events`), форматирование сумм; события собираются через `newFromBuilder()`, в общую таблицу тесты не пишут. Полный прогон — 268 тестов / 681 assertions зелёные, Pint чистый, `schedule:list` и `crypto-call-center:sync-trading-events --limit=1` проверены на dev-БД. | `packages/Webkul/CryptoCallCenter/src/{Contracts/TradingEvent.php,Models/TradingEvent.php,Models/TradingEventProxy.php,Repositories/TradingEventRepository.php,Services/TradingEventFeed.php,Services/TradingLeadResolver.php,Support/TradingEventPresenter.php,Events/TradingEventReceived.php,Listeners/TradingActivityListener.php,Console/Commands/SyncTradingEvents.php,Database/Migrations/2026_10_18_000000_create_trading_event_activities_table.php,Providers/CryptoCallCenterServiceProvider.php,Providers/EventServiceProvider.php,Providers/ModuleServiceProvider.php,Resources/views/leads/trading-events.blade.php,Resources/lang/ru/app.php,Resources/lang/en/app.php}`, `packages/Webkul/CryptoCallCenter/CHANGELOG.md`, `tests/Feature/CryptoCallCenter/TradingEventFeedTest.php`, `PROJECT_CONTEXT.md` |

| 2026-10-18 | Ручное управление балансом трейдера на карточке лида | Баланс меняет только трейдинг: CRM не пишет в `trading_accounts`, а просит изменение через контракт `Services/Balance/TradingBalanceGateway`. `HttpTradingBalanceGateway` шлёт `POST {CRM_TRADING_URL}/api/balance/adjust` (Bearer `CRM_TRADING_TOKEN`, таймаут 5 с, тело `user_id`/`amount`/`reason`/`adjustment_id`/`operator_name`, ответ `balance_before`/`balance_after`/`event_id`/`applied`): `422` отдаётся оператору дословно, `5xx`/`ConnectionException` подменяются прямой записью только при `CRM_TRADING_FALLBACK=true`, иначе `report()` + «трейдинг не ответил». `DatabaseTradingBalanceGateway` — установка без моста (нужны `trading_accounts` + `trading_events`): повторяет правила `TradingService::adjustBalance()` (ноль, пустая причина, потолок `max_amount`, запрет минуса), пишет `balance.adjustment` с `payload.reference` = id строки CRM и на повторе возвращает `applied = false`. Результат — `BalanceChange` (`balanceBefore`, `balanceAfter`, `tradingEventId`, `applied`). Конфиг `Config/trading.php` мержится как `crypto_call_center.trading.*` из `CRM_TRADING_DRIVER/URL/TOKEN/FALLBACK/TIMEOUT/MAX_AMOUNT`; `Support/TradingBridge::ready()` — общий признак «мост живой» для гейтвеев и блока. Аудит: миграция `2026_10_18_000100_create_crypto_balance_adjustments_table` (`lead_id` cascade, `user_id`/`admin_id` nullOnDelete, signed `amount` decimal(24,2), `balance_before`/`balance_after` decimal(24,8), `trading_event_id`, `reason`, индексы `(lead_id, created_at)` и `(user_id, created_at)`), `Contracts/BalanceAdjustment`, `Models/BalanceAdjustment` + прокси, `Repositories/BalanceAdjustmentRepository::latestForLead()`. `Services/BalanceAdjustmentService::adjust()` пишет строку **до** запроса (её id — ключ идемпотентности), удаляет её при `TradingPlatformException`, а после успеха в одной транзакции дописывает балансы и `trading_event_id`, создаёт `system`-активность («Баланс изменён на +$50. Причина: …» + «Баланс: $100 → $150») через `ActivityRepository`, привязывает к лиду и сразу резервирует зеркальное событие в `trading_event_activities` (`markProcessed()` + `attachActivity()`), чтобы планировщик не написал его второй раз. Форма без JavaScript: `Http/Requests/BalanceAdjustmentRequest` (signed `amount` в `between:-max,max`, `after()` на ноль, причина 2–255, `lead()` терпит уже привязанную модель), `Http/Controllers/BalanceAdjustmentController` → `POST admin/crypto-call-center/leads/{lead}/balance` (`admin.crypto_call_center.balance_adjustments.store`), успех во flash `balance_adjustment_success`, отказ — в bag ошибок `balance_adjustment`, оба через `back()`. Блок `Resources/views/leads/balance-adjustments.blade.php` вешается `registerBalanceBlock()` на `admin.leads.view.activities.before`, рендерится только при `BalanceAdjustmentAccess::allows()` и `TradingBridge::ready()`, показывает баланс из `TradingAccountRepository::balanceFor()`, форму и пять последних изменений. Права: ключ `crypto_call_center.balance` в `Config/acl.php` (без ключа bouncer отдаёт 401), тот же ключ в `Config/roles.php` для оператора, `Support/BalanceAdjustmentAccess` — ключ + владение лидом либо `permission_type = all` (чужой лид → 403, блок скрыт). Лента: `TradingEvent::TYPE_BALANCE_ADJUSTMENT`, `TradingEventPresenter` (`adjustment-credit`/`adjustment-write-off` + `signedMoney()`), бейдж `badge.adjustment`, переводы en/ru и `.env.example` (`CRM_TRADING_*`). Тесты: `BalanceAdjustmentTest` 11 кейсов / 60 assertions (шлюз подменяется через контейнер, кейсы на общие таблицы сами пропускаются), `BridgeBalanceTest` на стороне трейдинга — 6 кейсов / 25 assertions. Прогоны: CRM 279 тестов / 741 assertion, трейдинг 26 тестов / 117 assertions (в т.ч. `BridgeBalanceTest` — 6 кейсов / 25 assertions), `pint --test` по затронутым путям чистый (трейдинг — 49 файлов целиком; в корне CRM остаются 4 предсуществующих `line_ending` в `lang/ru/*.php`). Роль оператора в рабочей БД выровнена через `crypto-call-center:operator-role --sync` (11 прав, включая `crypto_call_center.balance`, описание с «Balance changes: own leads only»). Views ядра не переопределялись. | `packages/Webkul/CryptoCallCenter/src/**`, `packages/Webkul/CryptoCallCenter/CHANGELOG.md`, `tests/Feature/CryptoCallCenter/BalanceAdjustmentTest.php`, `crypto-trading/{app/Http,app/Services/TradingService.php,config/trading.php,routes/api.php,tests/Feature/BridgeBalanceTest.php,CHANGELOG.md,PROJECT_CONTEXT.md}`, `.env.example`, `PROJECT_CONTEXT.md` |

| 2026-10-18 | Очередь выплат (заявки на вывод) + событие `withdrawal.requested` | Read-only лента заявок на вывод из торгового кабинета (таблицы `withdrawal_requests` / `withdrawal_cards` принадлежат трейдингу, CRM их не пишет). Экран `GET admin/crypto-call-center/withdrawals` (`Http\Controllers\WithdrawalController::index`, `DataGrids\WithdrawalDataGrid` — join `users` + `withdrawal_cards`, свежие сверху, фильтры по клиенту/сумме/способу/статусу, пункт меню `Config/menu.php`) и раскрытие номера `GET .../withdrawals/{withdrawal}/card` (`card()`, `whereNumber`, 404 на неизвестный id). Два независимых права: `withdrawals` (очередь) и `withdrawals.card` (раскрытие) — без второго нет действия «Открыть заявку» и маршрут отвечает 401; роль оператора получает только `withdrawals` (`Config/roles.php`). Очередь маскирует карту (`•••• •••• •••• 4242` + бренд) и печатает кошелёк полностью (публичные данные); номер карты не попадает ни в грид, ни в экспорт, ни в лог — он открывается только на странице заявки. `Support\CardCipher` открывает `v1:base64(nonce):base64(tag):base64(cipher)` (AES-256-GCM, ключ `hash('sha256', WITHDRAWAL_CARD_KEY, true)`), при пустом ключе экран говорит «не настроено», при чужом — «не читается» (тег GCM не сходится); `Models\WithdrawalCard` держит `pan_encrypted`/`fingerprint` в `$hidden`. Чтение защищено `WithdrawalRequestRepository::available()` — установка без таблиц трейдинга видит объяснение вместо падения. Лента: `TradingEvent::TYPE_WITHDRAWAL_REQUESTED`, бейдж `trading-events.badge.withdrawal` (нейтральный `label-inactive` — деньги «наружу»), заголовок с суммой, комментарий `withdrawalRequisites()` (`**** 4242` + бренд либо кошелёк `TQn9Y2…bLSE`) — в таймлайн лида номер карты не попадает; `balance()` читает `balance_after ?? balance`, т.к. payload заявки несёт `balance`. Попутные правки: ветка реквизитов в `datagrid/requisites.blade.php` выбирается по реквизитам, а не по `$row->method` (колонка `method` рендерится раньше и подменяет значение подписью), неизвестные статус/бренд печатаются как есть / `brands.unknown`, статус на странице заявки — через `statusLabel()`. Новый `tests/Feature/CryptoCallCenter/WithdrawalFeedTest.php` (12 кейсов / 43 assertions) + `TradingEventFeedTest` переведён на действительно неизвестный тип `kyc.requested` (иначе `withdrawal.requested` больше не паркуется). Прогоны: `tests/Feature/CryptoCallCenter` — 86 тестов / 388 assertions, Pint по затронутым файлам чистый. Переопределённых вьюх ядра нет. | `packages/Webkul/CryptoCallCenter/src/{DataGrids/WithdrawalDataGrid.php,Http/Controllers/WithdrawalController.php,Models/Withdrawal*.php,Contracts/WithdrawalRequest.php,Repositories/WithdrawalRequestRepository.php,Support/{CardCipher,TradingEventPresenter}.php,Config/{acl,menu,roles,trading}.php,Routes/admin.php,Resources/views/withdrawals/**,Resources/lang/{en,ru}/app.php}`, `packages/Webkul/CryptoCallCenter/CHANGELOG.md`, `tests/Feature/CryptoCallCenter/{WithdrawalFeedTest,TradingEventFeedTest}.php`, `PROJECT_CONTEXT.md` |
| 2026-10-18 | Почта: проверка `.env` и команда `mail:test` | Сверены `MAIL_MAILER` / `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` / `MAIL_ENCRYPTION` (+ `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME`): `smtp`, `smtp.mail.ru:465`, `ssl`, ящик `5trgb4llki7h@mail.ru`; выполнен `php artisan config:clear` (закэшированного конфига не было). Добавлена команда `mail:test` (`Console/Commands/MailTest`, зарегистрирована в `registerCommands()`): печатает таблицу резолвнутого транспорта с **маскированным** паролем (длина вместо значения — вывод можно вставлять в тикет), падает с именем пустой переменной (`MAIL_HOST`, `MAIL_PASSWORD`, …) вместо стектрейса SMTP, предупреждает о закэшированном `bootstrap/cache/config.php`, о пустом `MAIL_ENCRYPTION` на 465/587 и о расхождении `MAIL_FROM_ADDRESS` с `MAIL_USERNAME` (mail.ru не даёт отправлять от чужого имени). `--dry-run` только проверяет настройки, `--mailer=` выбирает мейлер из `config/mail.php`, `--timeout=15` ограничивает ожидание мёртвого сервера (в `config/mail.php` таймаут не задан — был бы сокетный дефолт). Ошибка отправки ловится и переводится в подсказку: 535/authenticate → «нужен пароль приложения mail.ru», недоступный хост/таймаут → проверь `MAIL_HOST/PORT/ENCRYPTION`, сертификат/TLS → `ssl` для 465 и `tls` для 587. Реальная отправка на `5trgb4llki7h@mail.ru`: письмо принято сервером `smtp.mail.ru` за 1150 мс (доставку подтверждает только сам ящик — об этом печатает команда). `MailTestCommandTest` — 8 кейсов / 34 assertions (адрес по умолчанию, отказ на невалидном адресе без обращения к транспорту, перечисление пустых переменных, пароль не попадает в вывод, `--dry-run` без отправки, подсказка на 535, предупреждение о шифровании, мок мейлера через фасад). Полный прогон — 299 тестов / 818 assertions зелёные, Pint по затронутым файлам чистый. Правок ядра нет. | `packages/Webkul/CryptoCallCenter/src/{Console/Commands/MailTest.php,Providers/CryptoCallCenterServiceProvider.php}`, `packages/Webkul/CryptoCallCenter/CHANGELOG.md`, `tests/Feature/CryptoCallCenter/MailTestCommandTest.php`, `PROJECT_CONTEXT.md` |
| 2026-10-18 | Логотип CRYPTOISK на всех экранах входа | Экран логина больше не печатает Krayin-логотип: в пакете появились копии `admin::sessions.{login,forgot-password,reset-password}` (`views/overrides/sessions/*`), от ядра они отличаются **только** блоком лого — вместо `vite()->asset('images/logo.svg')` стоит `@include('crypto_call_center::components.branding.session-logo')`. Партиал один на три экрана: сначала логотип из Configuration → General → Settings (`general.general.admin_logo.logo_image`, путь подтверждён по `core_config.php` и ядровым шаблонам), иначе лок-ап «знак + CRYPTOISK» (`h-12 w-12` / `text-2xl`). Размеры лок-апа теперь задаёт вызывающий: `components.branding.logo` принимает `textClass` (дефолт `text-lg`) рядом с `markClass`, шапка и drawer зовут его без параметров. Имя продукта переименовано CRYPTO → **CRYPTOISK** в `lang/{en,ru}/app.php` (`branding.name`, подпись футера не тронута). Событие `admin.sessions.forgor_password.*` сохранено дословно (с опечаткой), футер сессий по-прежнему подменяет `View::composer('admin::sessions.*')`, а не копия. `BrandingTest` — 6 кейсов / 41 assertion: словоформ + `viewBox` знака на логине и на обоих парольных экранах, отсутствие `images/logo.svg`, путь вьюхи внутри `CryptoCallCenter` (доказательство, что override реально резолвится) и приоритет загруженного логотипа. Полный прогон `tests/Feature/CryptoCallCenter` — 97 тестов / 443 assertions зелёные, Pint чистый. Правок ядра нет. | `packages/Webkul/CryptoCallCenter/src/Resources/views/{overrides/sessions/*,components/branding/{logo,session-logo}.blade.php}`, `packages/Webkul/CryptoCallCenter/src/Resources/lang/{en,ru}/app.php`, `packages/Webkul/CryptoCallCenter/{CHANGELOG.md}`, `tests/Feature/CryptoCallCenter/BrandingTest.php` |
| 2026-10-18 | Роль «Call Center Moderator», расширенный оператор и sales owner созданного лида | Пакет объявляет вторую роль данными: `'moderator'` в `Config/roles.php` с `inherits => operator` — права объединяются с операторскими (20 против 19), остальные ключи ребёнка перекрывают родителя, поэтому экран, выданный оператору, попадает и модератору на следующем прогоне. Своё добавление одно — `withdrawals.card` (полный номер карты на экране выплаты). Наследование резолвится один раз в `Support/OperatorScope` (`keys()`/`declared()`/`definition()`/`definitions()`), их же читают команда, слушатели и тесты, так что роли не расходятся; там же появились `isSupervisor()` и `assignsCreatorAsLeadOwner()`, а `enforce()` молчит, когда объявленный `view_permission` — `null`, поэтому скоуп модератора выбирает админ в Settings → Users, а оператор по-прежнему жёстко `individual`. Оператор расширен до полного цикла: `leads.create` (+ quick-create), `contacts.persons.create`/`.edit`, `activities.edit`, `call_logs`, `withdrawals`; удаление лидов, чужие активности, настройки, пользователи, роли, товары и предложения остаются закрытыми. `Listeners/AssignLeadSalesOwner` на `lead.create.after` (регистрация в `Providers/EventServiceProvider`) переписывает `user_id` лида на создавшего через `saveQuietly()` — без второй записи в истории, и только для ролей с флагом `assign_creator_as_lead_owner`, так что админ, веб-форма и API сохраняют переданного владельца. `Support/BalanceAdjustmentAccess` получил ветку супервизора: владение и `permission_type = all` остаются, а модератор проходит, только если data-scope действующего аккаунта (`bouncer()->getAuthorizedUserIds()`) покрывает лид. Команда обобщена: `crypto-call-center:operator-role {role=operator}`, `--all` (все роли в порядке конфига, родитель раньше ребёнка), отказ `--all` + `--name`/`--description`, неизвестный ключ завершается ошибкой со списком объявленных ролей, все сообщения называют ключ. Тесты: новый `ModeratorRoleTest` (12 кейсов / 77 assertions), `OperatorRoleTest` вырос до 9 кейсов (59 assertions), `OperatorScopeTest` — 8 / 25; прогон `tests/Feature/CryptoCallCenter` — 110 тестов / 529 assertions, полный `vendor/bin/pest` — 315 / 925 зелёные, Pint по пакету и тестам чистый. Провижн в рабочей БД: `--all --sync` создал «Call Center Moderator» (20 прав) и обновил «Call Center Operator» (19). Правок ядра нет. | `packages/Webkul/CryptoCallCenter/src/{Config/roles.php,Support/OperatorScope.php,Support/BalanceAdjustmentAccess.php,Listeners/AssignLeadSalesOwner.php,Providers/EventServiceProvider.php,Console/Commands/ProvisionOperatorRole.php}`, `packages/Webkul/CryptoCallCenter/CHANGELOG.md`, `tests/Feature/CryptoCallCenter/{ModeratorRoleTest,OperatorRoleTest,OperatorScopeTest}.php` |
| 2026-10-18 | Самозапись клиента: зафиксирован контракт с торговым приложением | Правок кода CRM **нет** — публичная форма `/register` живёт в `crypto-trading` и пишет в общую БД `krayin_crm` сырыми `DB::table()`-вставками в одной транзакции (`users` → `roles` → `persons` → `leads` → `trading_accounts` с нулевым балансом). В `PROJECT_CONTEXT.md` (6.5) описано, почему `leads.user_id` — это **клиент**, а не оператор: от этой колонки зависят `BalanceAdjustmentService::adjust()` (начисление уходит на `$lead->user_id`) и `TradingLeadResolver::resolve()` (лента сделок ищет лид по `trading_events.user_id`), плюс `TradingAccountRepository::balanceFor($lead->user_id)` на карточке. `Listeners\AssignLeadSalesOwner` при такой записи не срабатывает: строка создаётся через query builder, а не Eloquent `create`, и создаёт её приложение, поэтому `Config/roles.php` `assign_creator_as_lead_owner` не участвует. Отмечено следствие для видимости: лид попадает в область данных по data scope (`global`/`group` видят, `individual` — только свои), а разделение «владелец лида / владелец счёта» потребует нового поля в CRM. Строка «Резолв лида» в таблице моста уточнена. Тесты и `Pint` — на стороне трейдинга (45 тестов / 234 assertions, 63 файла чисто). | `PROJECT_CONTEXT.md` |
| 2026-10-18 | Пополнения через подтверждение оператора + Sales Owner только из сотрудников | Зачисление депозита вынесено в решение оператора: клиент в `crypto-trading` оформляет заявку с картой (Luhn/срок/держатель, PAN под `WITHDRAWAL_CARD_KEY`, CVV сохраняется в самой заявке (с 19.10.2026)), CRM показывает очередь `admin/crypto-call-center/deposits` (`DataGrids/DepositDataGrid`, маска `**** 4242`, скоуп намеренно не по data-scope), страница заявки открывается отдельным ключом `deposits.review` и только там расшифровывается номер. `Services/Deposit/DepositReviewService::approve()` под `lockForUpdate()` вызывает шлюз (`http` → `POST /api/deposits/credit`, `database` как резерв) со ссылкой `deposit:{id}` — трейдинг ищет её в `trading_events.payload.reference` и на повторе отвечает `applied = false`, поэтому двойного зачисления нет; 422 трейдинга пробрасывается и оставляет заявку `pending`, 5xx/недоступность → fallback только при `CRM_TRADING_FALLBACK`. `reject()` пишет причину и денег не трогает. Событие `deposit.requested` добавлено в презентер с маской реквизитов. Параллельно закрыт «Sales Owner»: `Support/TradingClients` определяет клиента по роли трейдинга (`CRM_TRADING_CLIENT_ROLES` / `CRM_TRADING_CLIENT_ROLE_IDS`), `Models/SalesOwner` (скоуп `staff-only`) + `Repositories/SalesOwnerUserRepository` подставлены в колонку `sales_person` override-грида лидов и в `attribute_lookups.users.repository`, поэтому ни фильтр списка, ни селектор в форме лида клиентов больше не предлагают; в Settings → Users и на карточке лида клиент остаётся видим. Попутно исправлена тихая обрезка описаний ролей: `roles.description` — `varchar(255)`, а объявления были 273 и 295 символов, теперь 207 и 243 + guard-тест `RolePresetsTest`. Тесты: `DepositReviewTest` (9 / 58), `SalesOwnerLookupTest` (6 / 15), `RolePresetsTest` (1 / 6), дополнены `OperatorRoleTest` и `ModeratorRoleTest` (очередь пополнений, 401 на решение у оператора, 404 на неизвестную заявку у модератора); полный `php artisan test --compact` — 331 тест / 1013 assertions зелёные, `Pint` чистый, в `phpunit.xml` поднят `memory_limit` до 512M (набор перерос дефолтные 128M). В рабочей БД `crypto-call-center:operator-role --all --sync` обновил обе роли: 20 и 22 права, описания 207 и 243 символа; роль клиента — «Trading Client» (id 4016), `TradingClients::roleIds()` её находит. Правок ядра нет. | `packages/Webkul/CryptoCallCenter/src/{Config/{acl,menu,roles,trading}.php,Contracts/DepositRequest.php,Models/{DepositCard,DepositRequest,DepositRequestProxy,TradingEvent,SalesOwner}.php,Repositories/{DepositRequestRepository,SalesOwnerUserRepository}.php,DataGrids/DepositDataGrid.php,DataGrids/Overrides/LeadDataGrid.php,Exceptions/DepositReviewException.php,Services/Deposit/*,Http/Controllers/DepositController.php,Http/Requests/{Approve,Reject}DepositRequest.php,Providers/{CryptoCallCenterServiceProvider,ModuleServiceProvider}.php,Resources/views/deposits/*,Resources/lang/{en,ru}/app.php,Routes/admin.php,Support/{TradingClients,TradingEventPresenter}.php}`, `tests/Feature/CryptoCallCenter/{DepositReviewTest,SalesOwnerLookupTest,RolePresetsTest}.php` |
| 2026-10-19 | Входящая почта не доезжала до Inbox (IMAP) | Причина в ядре: `WebklexImapEmailProcessor` подключается к IMAP **в конструкторе**, а контейнер собирает процессор просто при регистрации `inbound-emails:process` — любая artisan-команда, трогавшая контейнер (`about`, `migrate`, `mail:test`), падала при недоступном ящике; обход дерева папок шёл только до листьев, поэтому INBOX с дочерними папками не импортировался вообще; карта папок знала 6 английских имён, и `Отправленные` / `Спам` / `Корзина` / `Sent Items` писались под пустым именем папки (в интерфейсе такие письма не видны). Ядро не правилось: `InboundEmail/MailboxEmailProcessor` наследует процессор и переопределяет три места — конструктор не зовёт `parent::__construct()` (репозитории присваиваются напрямую, клиент создаётся лениво при первом обращении, а `__destruct` закрывает соединение только если оно было открыто), `processFolderTree()` обходит **все** узлы и рекурсивно `children`, `supportedFolder()` переводит EN/RU имена в `SupportedFolderEnum` (`Входящие`, `Отправленные`, `Черновики`, `Корзина`, `Спам`, `Удалённые`, `Sent Items`, `Junk E-mail`), а неизвестную папку возвращает `null` — она пропускается, а не пишется в никуда (`All Mail` / `Archive` / `Архив` игнорируются намеренно, иначе дубли). `processMessage()` повторяет ядровой, но берёт папку из карты, изолирует сбойную папку (`Log::warning`, прогон продолжается) и выдаёт письму без `Message-ID` стабильный суррогат `uid.md5(путь папки)@localhost`, чтобы дедупликация работала и на следующих прогонах. `Support/MailboxConnection` собирает аккаунт как ядро (Configuration → Email → IMAP, затем блок `imap.accounts.default` из `IMAP_*`), но `validate_cert` разбирает через `filter_var(... FILTER_NULL_ON_FAILURE)` — ядровой каст превращал сохранённый `0` в `true`. Контракт `InboundEmailProcessor` перепривязан в `CryptoCallCenterServiceProvider::preferInboundMailbox()` через `$this->app->booted()`: `bootstrap/providers.php` ставит пакет **раньше** `EmailServiceProvider`, чей `boot()` биндит тот же контракт, а `booted()`-колбэки идут после всех `boot()`; драйвер читается при резолве, поэтому `sendgrid` по-прежнему даёт `SendgridEmailProcessor`, неизвестный — исключение. Диагностика: `php artisan mail:inbox` (`Console/Commands/CheckInbox`) — резолвнутые host/port/encryption/username, маскированный пароль и `validate_cert`, таблица папок (IMAP-имя → папка CRM, всего писем, попадает в окно `--days=10`), затем `--limit=5` последних писем на папку; `--folders` останавливается на списке, сбой папки печатается предупреждением, а сбой соединения — причиной сервера плюс подсказкой (mail.ru: включить IMAP и пароль приложения; 993 → `ssl`; самоподписанный сертификат → `IMAP_VALIDATE_CERT=false`). Тест `tests/Feature/CryptoCallCenter/InboundMailboxTest.php` — 33 кейса / 35 assertions: карта папок EN/RU (включая `Входящие`/`Удалённые`), `null` для неизвестной и пустой, игнор `All Mail`/`Archive`/`Архив`, привязка обоих драйверов, отсутствие соединения при сборке процессора (родительский `client` остаётся `null`), стабильность суррогатного id. Полный `php artisan test --compact` — 366 тестов / 1063 assertions зелёные, Pint чистый, `php -l` чистый. **Осталось на стороне пользователя:** mail.ru отвечает `AUTHENTICATIONFAILED ... Application password is REQUIRED` — нужен пароль приложения и `php artisan config:clear`; проверочный `_imap_probe.php` удалён. Правок ядра нет. | `packages/Webkul/CryptoCallCenter/src/{InboundEmail/MailboxEmailProcessor.php,Support/MailboxConnection.php,Console/Commands/CheckInbox.php,Providers/CryptoCallCenterServiceProvider.php}`, `packages/Webkul/CryptoCallCenter/CHANGELOG.md`, `tests/Feature/CryptoCallCenter/InboundMailboxTest.php`, `PROJECT_CONTEXT.md` |
| 2026-10-19 | Последний пункт сайдбара («Пополнения») недостижим | После добавления очереди пополнений меню перестало помещаться на экране ноутбука, а ядро рендерит его в `#admin-sidebar > .journal-scroll` с `h-[calc(100vh-100px)] overflow-hidden` — нижние пункты обрезались, а не прокручивались. Ядро не правилось и сайдбар не копировался: `Resources/views/partials/sidebar-scroll.blade.php` — один `<style data-crypto-call-center="sidebar-scroll">` c `@media (min-width: 1024px)` и селектором `.sidebar-not-collapsed #admin-sidebar > .journal-scroll`, поэтому свёрнутый рельс сохраняет `overflow-visible` (иначе его flyout-подменю не выйдет за 70px), а мобильный drawer не затрагивается. Высота осталась ядровой `calc(100vh - 100px)` (60px шапки + 40px полосы сворачивания), тонкий скроллбар возвращён (ядро прячет его у `.journal-scroll`, в тёмной теме `#4b5563`) — иначе не видно, что меню продолжается. Правило добавляет `CryptoCallCenterServiceProvider::registerSidebarScroll()` через `View::composer('admin::components.layouts.index')` + `$view->getFactory()->startPush('styles', ...)`: `@stack('styles')` печатается в `<head>` до сайдбара и до слота страницы, поэтому `@push` из вьюхи опаздывает. Тест `SidebarScrollTest` (2 кейса / 15 assertions): правило ровно один раз и до `</head>`, у свёрнутого рельса своего правила нет, на экране логина правила нет, `admin::components.layouts.sidebar.desktop.index` по-прежнему резолвится в файл Admin. | `packages/Webkul/CryptoCallCenter/src/Providers/CryptoCallCenterServiceProvider.php`, `packages/Webkul/CryptoCallCenter/src/Resources/views/partials/sidebar-scroll.blade.php`, `tests/Feature/CryptoCallCenter/SidebarScrollTest.php`, `packages/Webkul/CryptoCallCenter/CHANGELOG.md`, `PROJECT_CONTEXT.md` |
| 2026-10-19 | Решение по заявкам на вывод в CRM и CVV кассиру | Очередь выплат умела только читать — согласование жило в бэк-офисе трейдинга. Теперь CRM закрывает заявку, не двигая деньги: `POST admin/crypto-call-center/withdrawals/{withdrawal}/approve|reject` (`whereNumber`, ACL `withdrawals.card`, `FormRequest::authorize()` дублирует проверку маршрута) с `note` ≤255 и обязательной `reason` 3–255. `Services\Withdrawal\WithdrawalReviewService` отклоняет заявку не в `pending` **до** обращения к шлюзу и превращает заметку из пробелов в `null`. Шлюз `WithdrawalReviewGateway` → `HttpWithdrawalReviewGateway`: `POST {base}/api/withdrawals/review` (`request_id`, `status`, `note`, `reviewer_id`, Bearer-токен), 422 трейдинга печатается оператору дословно, а `ConnectionException`/5xx/пустой конфиг уходят в `DatabaseWithdrawalReviewGateway` только при `crypto_call_center.trading.fallback` (иначе «заявка всё ещё pending»); DB-транспорт пишет общую строку под `lockForUpdate()`, тот же статус → `applied = false` (повтор не переписывает `reviewed_at` и заметку), другой на решённой → `WithdrawalReviewException`. На `withdrawals/card.blade.php` появились формы решения (`withdrawal_success`, error bag `withdrawal`) и CVV из самой заявки (`withdrawal_requests.card_cvv`), на `deposits/show.blade.php` — CVV пополнения (`deposits.show.cvv`); в очередь, экспорт и лог запросов код не попадает, у сохранённой карты секрета нет. Ленг-ключи en+ru: `withdrawals.review.*`, `withdrawals.errors.*`. Трейдинг: миграция `2026_10_19_000001_add_card_cvv_to_requests_tables`, `WithdrawalService::review()`, мост `POST /api/withdrawals/review` + `ReviewWithdrawalRequest` (`status` только `approved`/`rejected`, `reviewer_id` → `exists:users,id` — оператор CRM живёт в той же `users`). Тесты: `WithdrawalReviewTest` 12/53, `DepositReviewTest` 10/61, трейдинг `BridgeBalanceTest` 13/60 (+7 кейсов выплат); прогоны 379/1119 и 58/321 зелёные, Pint чистый (829 и 73 файла). | `packages/Webkul/CryptoCallCenter/src/{Http/Controllers/WithdrawalController.php,Http/Requests/ApproveWithdrawalRequest.php,Http/Requests/RejectWithdrawalRequest.php,Repositories/WithdrawalRequestRepository.php,Services/Withdrawal/DatabaseWithdrawalReviewGateway.php,Services/Withdrawal/HttpWithdrawalReviewGateway.php,Services/Withdrawal/WithdrawalReviewGateway.php,Services/Withdrawal/WithdrawalReviewService.php,Exceptions/WithdrawalReviewException.php,Routes/admin.php,Config/acl.php,Providers/CryptoCallCenterServiceProvider.php,Resources/views/withdrawals/card.blade.php,Resources/views/deposits/show.blade.php,Resources/lang/en/app.php,Resources/lang/ru/app.php}`, `packages/Webkul/CryptoCallCenter/CHANGELOG.md`, `tests/Feature/CryptoCallCenter/WithdrawalReviewTest.php`, `tests/Feature/CryptoCallCenter/DepositReviewTest.php`, `crypto-trading/app/{Http/Controllers/BridgeBalanceController.php,Http/Controllers/WithdrawalController.php,Http/Requests/ReviewWithdrawalRequest.php,Services/WithdrawalService.php,Models/WithdrawalRequest.php}`, `crypto-trading/database/migrations/2026_10_19_000001_add_card_cvv_to_requests_tables.php`, `crypto-trading/tests/Feature/{BridgeBalanceTest.php,WithdrawalFlowTest.php,DepositFlowTest.php}`, `crypto-trading/CHANGELOG.md`, `PROJECT_CONTEXT.md` |
| 2026-10-19 | Клиентский кабинет: размер в USD, превью сохранённой карты, `review_note` | Закрыты пять пробелов UX трейдинга и сверена CRM-сторона. Трейдинг: `size_unit` (`coin`/`usd`) + `position.min_notional`, конвертация по цене входа той же транзакции, событие `position.opened` дополнено `size_unit` и `requested_amount` — **только добавленные ключи**, `TradingEventPresenter::notional()` читает `notional`, поэтому старые и новые события печатаются одинаково; превью сохранённой карты строится из `data-brand` / `data-masked` / `data-holder` / `data-expiry` выбранной опции, полный PAN не рендерится; `review_note` виден у формы и в истории; спиннер и блокировка повторного клика на «Закрыть позицию»; срок хранения карт `cards.retention_days` с `touch()` при каждом использовании. CRM менять не пришлось: `review_note` есть в `$fillable` обеих моделей, пишется `DepositReviewService` (одобрение и отказ) и `DatabaseWithdrawalReviewGateway`, печатается в `deposits/show.blade.php` и `withdrawals/card.blade.php`, формы шлют `note` через `Approve*Request` / `Reject*Request`. Новый ключ payload — аддитивное расширение контракта, читающая сторона его игнорирует. Тесты трейдинга: 73 / 448 assertions, `Pint` — 73 файла без замечаний; из CRM-набора прогнаны только `DepositReviewTest` + `WithdrawalReviewTest` (28 / 156) — код CRM не менялся. | `crypto-trading/{app/Http/Requests/OpenPositionRequest.php,app/Services/{TradingService,DepositService,WithdrawalService}.php,resources/views/{dashboard.blade.php,partials/saved-card.blade.php},tests/Feature/{TradingFlowTest,DepositFlowTest,WithdrawalFlowTest}.php,CHANGELOG.md,PROJECT_CONTEXT.md,.env.example}`; в CRM проверены без правок `packages/Webkul/CryptoCallCenter/src/{Models/DepositRequest.php,Models/WithdrawalRequest.php,Services/Deposit/DepositReviewService.php,Services/Withdrawal/DatabaseWithdrawalReviewGateway.php,Resources/views/deposits/show.blade.php,Resources/views/withdrawals/card.blade.php}` |
| 2026-10-19 | Закрытие выплаченной заявки на вывод («Выплачено») | CRM умела одобрить вывод, но не отметить, что бэк-офис его действительно выплатил. Добавлен `POST admin/crypto-call-center/withdrawals/{withdrawal}/complete` (`whereNumber`, ACL `admin.crypto_call_center.withdrawals.complete`, ключ меню/права `withdrawals.card`; `Http/Requests/CompleteWithdrawalRequest::authorize()` дублирует проверку маршрута) → `WithdrawalReviewService::complete()`: заявка не в `approved` отклоняется до обращения к шлюзу (`withdrawals.errors.not-approved`), уже `paid` возвращается как есть (повтор идемпотентен и не зовёт мост второй раз), `reviewed_by` / `reviewed_at` / `review_note` не переписываются — закрытие выплаты не меняет решения. Трейдинг: `WithdrawalRequest::STATUS_PAID` + `isCompleted()` / `isApproved()`, `statusLabel()` → «Выплачено», `is-success`; `WithdrawalService::review()` разрешает `approved → paid` под `lockForUpdate()`, из `pending` / `rejected` в `paid` нельзя, повтор отвечает `applied = false`; `ReviewWithdrawalRequest` принимает `paid`, мост `POST /api/withdrawals/review` не менялся. `withdrawals/card.blade.php` показывает форму «Выплачено» только у `approved`, а у `paid` — панель решения (оператор, дата, заметка), очередь печатает статус через `datagrid/status.blade.php` (`label-active`); денег CRM не двигает. Ленг-ключи en+ru `withdrawals.review.complete*` / `withdrawals.review.completed`. Тесты: CRM `WithdrawalReviewTest` — 16 кейсов зелёные (переход, повтор, отказ из `pending`, гейт по правам), трейдинг `BridgeBalanceTest` — 16 кейсов / 85 assertions (оплата без перезаписи решения, отказ из `pending`, повтор). | `packages/Webkul/CryptoCallCenter/src/{Http/Controllers/WithdrawalController.php,Http/Requests/CompleteWithdrawalRequest.php,Models/WithdrawalRequest.php,DataGrids/WithdrawalDataGrid.php,Services/Withdrawal/{WithdrawalReviewService.php,WithdrawalReviewGateway.php,HttpWithdrawalReviewGateway.php,DatabaseWithdrawalReviewGateway.php},Routes/admin.php,Config/acl.php,Resources/views/withdrawals/{card.blade.php,datagrid/status.blade.php},Resources/lang/{en,ru}/app.php}`, `crypto-trading/app/{Models/WithdrawalRequest.php,Services/WithdrawalService.php,Http/Requests/ReviewWithdrawalRequest.php}`, `tests/Feature/CryptoCallCenter/WithdrawalReviewTest.php`, `crypto-trading/tests/Feature/BridgeBalanceTest.php`, `packages/Webkul/CryptoCallCenter/CHANGELOG.md`, `crypto-trading/CHANGELOG.md`, `PROJECT_CONTEXT.md` |
| 2026-10-19 | Роли: общая телефонная книга оператора, модератор на `global`, Settings закрыт | Оператор видит все контакты, но не чужие лиды/активности/таймлайны; модератор видит весь стол, но не администрирование. Вместо копирования контроллеров контактов добавлен `Webkul\CryptoCallCenter\Bouncer`, наследующий ядровой `Admin\Bouncer`; в провайдере он зарегистрирован под строковым ключом `bouncer` (`$this->app->singleton('bouncer', Bouncer::class)`), потому что и `bouncer()`, и фасад резолвят ключ, а не класс. `getAuthorizedUserIds()` возвращает `null` (ядровое «без фильтра по владельцу») только на маршрутах `admin.contacts.*` и только для аккаунтов с объявленной ролью; таймлайн `admin.contacts.persons.activities.*` исключён, поэтому карточка коллеги открывается, а его звонки и заметки — нет. Остальные маршруты и роли идут в родителя. В роли оператора добавлены недостающие `contacts.organizations.create` (+ quick-create) и `contacts.organizations.edit`: без них форма организации коллеги отвечала 401, хотя описание роли обещает общую книгу с созданием и правкой. Модератор запиннен на `view_permission = global` (было `null`, т.е. админ мог сузить его до `individual`): глобальный скоуп даёт `null` из ядрового Bouncer и открывает все лиды/контакты/активности, а `supervisor` разрешает менять баланс любого лида; Settings, Users, Roles, Groups и Configuration по-прежнему отвечают 401 и не печатаются в меню. Тесты: `OperatorScopeTest` — 9 кейсов, включая общую книгу и изоляцию таймлайнов; `ModeratorRoleTest` — 12 кейсов, включая глобальные лиды/контакты/активности двух операторов, 401 на Settings/Users/Roles/Groups/Configuration и отсутствие пункта Settings; весь `tests/Feature/CryptoCallCenter` — 181 тест / 787 assertions зелёные. Правок ядра нет. | `packages/Webkul/CryptoCallCenter/src/{Bouncer.php,Providers/CryptoCallCenterServiceProvider.php,Config/roles.php,Support/OperatorScope.php}`, `tests/Feature/CryptoCallCenter/{OperatorScopeTest.php,ModeratorRoleTest.php}`, `packages/Webkul/CryptoCallCenter/CHANGELOG.md`, `PROJECT_CONTEXT.md` |
| 2026-10-19 | Списание баланса при закрытии выплаты (`approved → paid`) | CRM закрывала заявку как «Выплачено», но деньги клиента оставались на счёте: `paid` менял только статус, поэтому одну и ту же сумму можно было вывести повторно. Теперь `approved → paid` — единственный переход, который двигает `trading_accounts.balance_usd`, и делает это торговая сторона в `WithdrawalService::review()` через приватный `debit()`: строка заявки уже под `lockForUpdate()`, счёт дополнительно запирает `TradingService::lockAccount()` (метод и `accountFor()` проверены), чтобы сделка, депозит или ручная корректировка не перезаписали баланс. Сумма округляется до 8 знаков, списывается из `balance_usd`, в ответ идут `balance_before` / `balance_after`, отдельного события не пишется. Нехватка проверяется до записи: сумма больше баланса → `TradingException` 422 «Недостаточно средств: сумма вывода $… больше доступного баланса $…», транзакция откатывает и списание, и статус, заявка остаётся `approved` и после пополнения закрывается тем же маршрутом. Повтор на уже `paid` отвечает `applied = false` **до** `debit()`, поэтому второй раз не списывает; из `pending` / `rejected` в `paid` по-прежнему нельзя. `BridgeBalanceController::withdrawal()` отдаёт округлённые `balance_before` / `balance_after` (`null` у одобрения, отказа и повтора), docblock сервиса, контроллера и модели описывает новое поведение. CRM-код менялся минимально: `WithdrawalReviewService` только передаёт вызов, а `DatabaseWithdrawalReviewGateway` теперь отказывает в `paid` с `withdrawals.errors.requires-bridge` (en+ru) — при недоступном мосте с `CRM_TRADING_FALLBACK=true` или `CRM_TRADING_DRIVER=database` заявка остаётся `approved`, а не закрывается без списания; одобрение и отказ по-прежнему могут писать в общую таблицу. Тесты `crypto-trading/tests/Feature/BridgeBalanceTest.php` — 18 кейсов / 115 assertions: одобрение не списывает, выплата $500 из $500 оставляет $0, $4000 из $4000 оставляет $0 (цель задачи), нехватка $100 из $50 → 422 и заявка `approved`, повтор после пополнения списывает ровно один раз, повторное закрытие `applied = false` и баланс не двигается, из `pending` в `paid` нельзя. Полный прогон трейдинга — 63 теста / 376 assertions, Pint чистый (73 файла). CRM `WithdrawalReviewTest` — 18 тестов / 95 assertions: закрытие через шлюз (подменяет трейдинг) с проверкой единственного вызова `paid` без оператора, повтор не зовёт шлюз второй раз, отказ закрыть заявку в общей таблице при недоступном мосте (`requires-bridge`, заявка остаётся `approved`), страница одобренной выплаты без моста скрывает форму и объясняет `requires-bridge`, отказ из `pending`, гейт по правам. Полный прогон CRM — 385 тестов / 1161 assertion; Pint чистый по изменённым файлам CRM и по всем 73 файлам трейдинга. | `crypto-trading/app/{Services/WithdrawalService.php,Http/Controllers/BridgeBalanceController.php,Models/WithdrawalRequest.php}`, `crypto-trading/{tests/Feature/BridgeBalanceTest.php,CHANGELOG.md}`, `packages/Webkul/CryptoCallCenter/src/Services/Withdrawal/{WithdrawalReviewService.php,DatabaseWithdrawalReviewGateway.php,HttpWithdrawalReviewGateway.php}`, `packages/Webkul/CryptoCallCenter/src/Http/Controllers/WithdrawalController.php`, `packages/Webkul/CryptoCallCenter/src/Support/TradingBridge.php`, `packages/Webkul/CryptoCallCenter/src/Resources/views/withdrawals/card.blade.php`, `packages/Webkul/CryptoCallCenter/src/Resources/lang/{en,ru}/app.php`, `tests/Feature/CryptoCallCenter/WithdrawalReviewTest.php`, `packages/Webkul/CryptoCallCenter/CHANGELOG.md`, `PROJECT_CONTEXT.md` |
| 2026-10-19 | Ложная ошибка «Error validating email» под Email/Phone на форме лида | Форма лида спрашивала у ядра `admin.settings.attributes.check_unique_validation`, занят ли email/телефон; у маршрута нет своей ACL-записи, поэтому Bouncer проверял ближайший объявленный предок `admin.settings.*` — оператор, которому лид создавать можно, а Settings нет, получал 401, и компонент печатал свой catch-all текст «Error validating email. Please try again.» под полем (телефонный компонент ловил тот же ответ и печатал ту же фразу). Вопрос теперь задаёт `GET admin/leads/check-unique-contact` (`admin.leads.check_unique_contact`, `Http/Controllers/ContactUniquenessController`), названный под фичей лида намеренно: необъявленный маршрут авторизуется по ближайшему объявленному предку, поэтому проверке хватает права, которое и так открывает форму, а аккаунт без прав на лиды по-прежнему получает 401; отдельный ACL-ключ отклонён — его пришлось бы досдавать всем ранее созданным ролям, а до перепрошивки они продолжали бы получать тот самый 401. Запрос уже, чем ядровой: `entity_type` только `persons`, `attribute_code` только `emails,contact_numbers`, атрибут ищется по коду и типу сущности сразу (код `contact_numbers` есть и у персон, и у складов), `entity_id` необязателен и превращается в `0`, потому что `isValueUnique()` с null-ом сравнивает `entity_id != NULL` и объявляет свободным любой занятый адрес. Две blade-копии (`Resources/views/overrides/components/attributes/edit/{email,phone}.blade.php`, уже отдаются через `prependNamespace('admin')`) шлют запрос на новый маршрут и считают недоступную проверку «непроверенной», а не ошибкой формы: вспомогательный запрос не должен блокировать корректную форму, серверные правила `LeadForm` остаются решающими, а дубль внутри одной формы ловится локально; `entity_id` здесь — id персоны (форма лида строит компонент с `person?.id`), а в формах атрибутов (код `emails`) проверка не выполняется вовсе, как и в ядре. `LeadContactUniquenessTest` (4 кейса): оператор получает 200 вместо 401, занятый адрес считается занятым и исключается для своей же персоны, аккаунт только с `contacts.persons` по-прежнему 401, а сохранение лида с валидными email и `+37529…` проходит без единой ошибки валидации, и сохранённый адрес потом читается как занятый. Полный прогон — 390 тестов / 1198 assertions зелёные, Pint чистый. Правок ядра нет. | `packages/Webkul/CryptoCallCenter/src/{Http/Controllers/ContactUniquenessController.php,Routes/admin.php,Resources/views/overrides/components/attributes/edit/email.blade.php,Resources/views/overrides/components/attributes/edit/phone.blade.php}`, `tests/Feature/CryptoCallCenter/LeadContactUniquenessTest.php`, `packages/Webkul/CryptoCallCenter/CHANGELOG.md`, `PROJECT_CONTEXT.md` |













> Формат записи: дата, задача, суть изменения, затронутые файлы.
> Отдельно фиксировать **переопределённые вьюхи ядра** и **правки пакетов
> Webkul** (если такие появятся) — это список для проверки при апгрейде.


