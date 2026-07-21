# Цифровой дом

Личный сайт-экосистема: **дом** на `/`, лендинг разработчика на `/dev--null`, кейсы, хроника, универсальная связь.

**Стек:** Symfony 8, EasyAdmin, MySQL, Docker (локально).

## Быстрый старт

```bash
cp .env.example .env.local   # задать ADMIN_PASSWORD
docker compose up -d --build
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console app:seed
docker compose exec php php bin/console app:chronicle:seed-meta
docker compose exec php php bin/console app:chronicle:seed-likes
```

| | |
|--|--|
| Дом | http://localhost:8085 |
| Разработка | http://localhost:8085/dev--null |
| Кейсы | http://localhost:8085/cases |
| Хроника | http://localhost:8085/chronicle |
| Связь | http://localhost:8085/contact |
| Админка | http://localhost:8085/admin |

## Документация

**Полная инструкция:** [docs/guide.md](docs/guide.md)

Локальная разработка · карта сайта · контент · хроника · админка · деплой · прод-импорт · бэкапы.

## Контент

| Что | Где |
|-----|-----|
| Тексты лендинга `/dev--null` | `src/Content/LandingContent.php` → `app:content:sync` |
| Дом, комнаты, страница связи | `src/Content/HouseContent.php` (только код, без sync) |
| Политика | `src/Content/LegalContent.php` |
| Контакты / аватар / email заявок | Админка → **Настройки** |

Блоки лендинга в админке **не редактируются** — эталон в коде.

## Хроника (кратко)

- Каталог эпох / тегов / каналов: `config/content/catalog.json`
- Сборка корпуса и импорт — **локальные** скрипты (`scripts/`, не в git) + `app:chronicle:import`
- На сайте публикуются каналы с `status: published`
- Лайки: живые (гость) + импорт счётчиков VK → `app:chronicle:seed-likes`
- Подробности и перенос на прод → [docs/guide.md § Хроника](docs/guide.md#4-хроника-блог)

Локальные тяжёлые данные (`content/`, `corpus/`, `analysis/`, `scripts/`) в репозиторий не входят.  
Деплой: [docs/guide.md §8](docs/guide.md#8-деплой-на-хостинг-netangels-vps-shared).
