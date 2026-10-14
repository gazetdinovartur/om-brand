# Практика: оплата, комната, сессии, Kinescope

Логика регулярной практики на сайте: заявка → оплата (ЮKassa) → доступ в комнату → эфир/запись (Kinescope) → списание встреч с баланса.

Пока страница **не в публичной навигации** (`HouseContent::PRACTICE_PUBLIC_ENABLED = false`). Прямой URL `/practice` открывается только **админу** (ROLE_ADMIN). В тестах принудительно публична через `PRACTICE_PUBLIC_ENABLED=1` в `phpunit.xml.dist`.

---

## Карта URL

| URL | Кто | Назначение |
|-----|-----|------------|
| `/practice` | админ (пока) / все (после публикации) | Лендинг + форма записи |
| `/practice/room` | ROLE_USER с доступом | Комната: эфир / запись / ожидание |
| `/account?tab=practice` | ROLE_USER | Заказы, остаток встреч, записи |
| `/webhooks/yookassa` | ЮKassa | `payment.succeeded` → paid |
| `/webhooks/kinescope` | Kinescope (Basic) | live.* / speak.* / media.update.status |
| `/meet/{token}` | по ссылке | Speak «просто встреча» (без оплаты) |
| `/admin` → Сессии практики | админ | CRUD, тип Live/Speak, «Создать в Kinescope», RTMP |
| `/admin` → Встречи Speak | админ | Комнаты без практики |
| `/admin` → Оплаты | админ | PaymentOffer |

---

## Форматы участия

`App\Practice\PracticeFormat`:

| Format | Цена | Встреч (total/left при создании) |
|--------|------|----------------------------------|
| `first` | 0 ₽ | 1 / 1 |
| `single` | 222 ₽ | 1 / 1 |
| `cycle` | 555 ₽ | 4 / 4 |

Поля на `PaymentOffer`: `practiceFormat`, `sessionsTotal`, `sessionsLeft`. Note по-прежнему содержит `Практика /practice · {format}`.

---

## Поток оплаты

1. Форма на `/practice` → `PracticeEnrollmentService::enroll`.
2. Создаётся `Inquiry` (тип Practice) + `PaymentOffer` с балансом.
3. Платные форматы: платёж ЮKassa, письмо/Telegram со ссылкой; статус pending.
4. Бесплатный `first`: сразу paid, доступ в комнату.
5. Оплата: webhook ЮKassa или возврат `?yk_return=1` → `markAsPaid` → письмо «комната открыта», Telegram.

Доступ в комнату (`userHasPracticeRoomAccess`):

- есть paid-офер **и**
- `sessionsLeft > 0` **или** есть сессия в статусе `live` / `replay` (чтобы досмотреть запись после списания последней встречи).

---

## Сессии и статусы

Сущность `PracticeSession`:

- `scheduled` — в расписании, в комнате только текст «ближайшая…»
- `live` — эфир в комнате (embed)
- `replay` — запись в комнате + в кабинете «Записи»
- `completed` — архив (не главный фокус комнаты), запись в кабинете может остаться

Приоритет в комнате: **live → последняя replay → иначе экран ожидания**.

В админке перед эфиром ставишь статус **live** и заполняешь **Kinescope event id**. После эфира обычно срабатывает webhook; запасной путь — кнопка **«Эфир завершён»**.

---

## Управление из админки (Kinescope API)

Env: `KINESCOPE_API_TOKEN`, `KINESCOPE_PARENT_ID` (проект arturlun / UUID), `KINESCOPE_SPEAK_OWNER_ID` (**user_id участника workspace**, не project id), webhook Basic, плюс JWT для ведущего (ниже).

Если Speak пишет `no such user in workspace`:

```bash
php bin/console app:kinescope:speak-owner
php bin/console app:kinescope:speak-owner --pick=1   # или email / uuid
```

В `.env` поставь выведенный `KINESCOPE_SPEAK_OWNER_ID`, `cache:clear`, снова создай комнату в карточке встречи.

1. Создай сессию (название, время, тип Live/Speak) → **сохранение сразу создаёт** event/room в Kinescope.
2. Live: в карточке RTMP URL + stream key для OBS; участники — `/practice/room`. Speak: та же комната сайта.
3. **В эфир** / webhook `live.connected` / `speak.call.started`.
4. **Эфир завершён** или webhook `live.finished` / Speak record → replay + списание + метаданные записи (постер).

Галерея в кабинете — карточки записей практики (poster/дата), не весь каталог Kinescope.

**Встречи Speak без практики:** админка «Встречи Speak» → сохранить → комната и ссылка `/meet/{token}` сразу.

### Speak host JWT (ведущий в iframe)

Без JWT участники заходят гостями; ведущий с правами админа Speak — только через `?token=` (RS256, `aud=speak`, `role=admin`, `room_id` = code комнаты).

Один раз локально / на сервере:

```bash
php bin/console app:kinescope:setup-jwt
```

Команда создаёт RSA-ключ в `var/kinescope/jwt_private.pem` (в git не попадает), заливает публичный JWK в Kinescope (`POST /v1/jwk`) и печатает строки для `.env`:

```env
KINESCOPE_JWT_PRIVATE_KEY_PATH=/полный/путь/к/var/kinescope/jwt_private.pem
KINESCOPE_JWT_KID=key-YYYY-MM-DD
```

После `cache:clear`: если ты залогинен как `ROLE_ADMIN`, iframe на `/practice/room` и `/meet/{token}` открывается уже с host-токеном. Участники по той же ссылке — без токена.

На проде: тот же PEM + kid (или снова `setup-jwt` с новым kid и загрузкой JWK). Приватный ключ не коммитить.

## Автосписание после эфира

Webhook `POST /webhooks/kinescope` (события live/speak/media):

1. Ищем сессию по event_id / room_id / video_id.
2. Пишем `kinescopeVideoId`, статус → `replay` (при finish).
3. Один раз (`debitedAt`): у practice paid-оферов с `sessionsLeft > 0` минус 1.

Списание = «занятие состоялось». Auth: HTTP Basic из `.env`.

Подписку на события (в кабинете их часто нет) создаёшь через API:

```bash
# локально с APP_SITE_URL=https://arturlun.ru или на проде:
php bin/console app:kinescope:setup-webhook
php bin/console app:kinescope:setup-webhook --list
```

Нужны `KINESCOPE_API_TOKEN`, `KINESCOPE_WEBHOOK_USER` / `PASSWORD`, `APP_SITE_URL` (или `--endpoint`).

---

## Комната (UX)

- **Экран A** (live/replay + id): плеер `https://kinescope.io/embed/{id}` + чат `https://kinescope.io/chat/{event_id}` (или свой URL в поле сессии). Чат после эфира оставляем для обратной связи (`chat_after_stream` + `chat_active` в Kinescope).
- **Экран B**: «Сейчас эфира нет», ближайшая scheduled, остаток встреч.

Стили: оболочка наша; UI плеера/чата — шаблоны Kinescope (наш CSS в iframe не заходит). CSP: `frame-src` включает `kinescope.io` и `player.kinescope.io`.

---

## Кабинет

- У paid-офера: «осталось X из Y».
- Блок **Записи практики**: сессии с `kinescopeVideoId` (replay/completed) → «Смотреть» в комнату.

---

## Как снова показать публично

1. `HouseContent::PRACTICE_PUBLIC_ENABLED = true` **или** в `.env` `PRACTICE_PUBLIC_ENABLED=1`.
2. Раскомментировать пункт меню и комнату на карте в `HouseContent` (см. TODO-комментарии).
3. Убрать `/practice` из `Disallow` в robots при желании индексировать.
4. Деплой + bump `ASSETS_VERSION` если трогали CSS.

---

## Связанные файлы

- Контроллеры: `PracticeController`, `WebhookController`, `LibraryAuthController`
- Сервисы: `PracticeEnrollmentService`, `PaymentOfferService`, `PracticeSessionService`
- Админка: `PracticeSessionCrudController`, `PaymentOfferCrudController`
- Миграция баланса/сессий: `Version20261014020000`
