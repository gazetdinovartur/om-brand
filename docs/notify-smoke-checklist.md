# Smoke: email / push notify

Ручная проверка перед продом и после деплоя. Автотесты: `php vendor/bin/phpunit --filter 'Email|Push|Notifier|Publisher'`.

## Предусловия (prod `.env`)

- [ ] `MAILER_DSN` — реальный SMTP (не `null://null`)
- [ ] `MAILER_FROM` — совпадает с SMTP-логином
- [ ] `APP_SITE_URL=https://arturlun.ru`
- [ ] `DEFAULT_URI=https://arturlun.ru`
- [ ] `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT=mailto:…`
- [ ] PHP: `php -m | grep -E 'gmp|bcmath'`
- [ ] Миграции: `doctrine:migrations:migrate --no-interaction --env=prod`
- [ ] Nginx: `/sw.js` с `Cache-Control: no-cache` (не 30d immutable)

Генерация VAPID:

```bash
php -r 'require "vendor/autoload.php"; print_r(Minishlink\WebPush\VAPID::createVapidKeys());'
```

## 1. Email subscribe → confirm

1. Открыть `/chronicle` (или страницу с блоком подписки).
2. Ввести тестовый email → «Подписаться по email».
3. Проверить почту → письмо «Подтвердите подписку».
4. Клик по ссылке → страница «Подписка подтверждена».
5. В БД: `email_subscriber.status = confirmed`.

## 2. Push subscribe (браузер)

1. Chrome / Edge (не приватный режим).
2. Разрешить уведомления в блоке подписки.
3. В БД: строка в `push_subscription`.
4. DevTools → Application → Service Workers: `sw.js` активен.

## 3. Notify по опубликованной записи

```bash
php bin/console app:chronicle:notify <slug> --env=prod
```

- [ ] Email на подтверждённый адрес («Новая запись: …»)
- [ ] Push в браузере с активной подпиской
- [ ] В БД: `chronicle_entry.subscribers_notified_at` заполнен

Повторный publish из админки **не** должен слать всем снова (guard `subscribers_notified_at`). Команда `app:chronicle:notify` — принудительный re-notify (smoke).

## 4. Unsubscribe

1. Ссылка «Отписаться» в письме → «Вы отписались».
2. Push: отключить в UI / `POST /api/push/unsubscribe`.
3. Повторный `app:chronicle:notify` — email не приходит на отписанный адрес.

## 5. Регрессия

```bash
php vendor/bin/phpunit
php bin/console lint:container --env=prod
```
