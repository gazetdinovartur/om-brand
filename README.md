# Личный бренд · лендинг

Сайт-визитка с формой заявок, админкой и опциональной оплатой по ссылке.

**Стек:** Symfony 8, EasyAdmin, MySQL, Docker (nginx + php-fpm).

## Быстрый старт

```bash
docker compose up -d --build
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console app:seed
```

| Сервис | URL |
|--------|-----|
| Сайт | http://localhost:8085 |
| Админка | http://localhost:8085/admin |
| MySQL | `127.0.0.1:33085` (user/pass/db: `site` / `site` / `site`) |

Логин админа по умолчанию: `admin@localhost` / `admin` (из `.env`).

## Документация

| Файл | О чём |
|------|--------|
| [docs/content-and-seed.md](docs/content-and-seed.md) | **Где лежит контент**, seed, sync, slug-блоки, админка vs код |
| [docs/development.md](docs/development.md) | Окружение, переменные, команды, структура проекта |

## Где править тексты лендинга

1. **Эталон в коде** — `src/Content/LandingContent.php` (версионирование, деплой).
2. **Применить в БД** — `php bin/console app:content:sync`.
3. **На проде без деплоя** — админка → «Блоки контента» / «Настройки сайта`.

Подробная схема: [docs/content-and-seed.md](docs/content-and-seed.md).

## Полезные команды

```bash
# Первичное заполнение (настройки + блоки + админ, если пусто)
docker compose exec php php bin/console app:seed

# Обновить тексты блоков из LandingContent.php
docker compose exec php php bin/console app:content:sync

# Очистить кеш
docker compose exec php php bin/console cache:clear
```
