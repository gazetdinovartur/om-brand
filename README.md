# Личный бренд · лендинг

Сайт-визитка с формой заявок, админкой, оплатой по ссылке и **хроникой** (блог по эпохам).

**Стек:** Symfony 8, EasyAdmin, MySQL, Docker (локально).

## Быстрый старт

```bash
cp .env.example .env.local   # задать ADMIN_PASSWORD
docker compose up -d --build
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console app:seed
docker compose exec php php bin/console app:chronicle:seed-meta
```

| | |
|--|--|
| Сайт | http://localhost:8085 |
| Хроника | http://localhost:8085/chronicle |
| Админка | http://localhost:8085/admin |

## Документация

**Полная инструкция:** [docs/guide.md](docs/guide.md)

Локальная разработка · лендинг · **хроника** · админка · деплой · прод-импорт · бэкапы.

## Контент лендинга

1. `src/Content/LandingContent.php` — эталон текстов  
2. `php bin/console app:content:sync` — применить в БД  
3. Или админка → Блоки контента

## Хроника (кратко)

- Каталог эпох / тегов / каналов: `config/content/catalog.json`
- Сборка корпуса и импорт — **локальные** скрипты (`scripts/`, не в git) + `app:chronicle:import`
- На сайте публикуются каналы с `status: published` (сейчас: Да и Да, VK, Instagram)
- Подробности и перенос на прод → [docs/guide.md § Хроника](docs/guide.md#4-хроника-блог)

Локальные тяжёлые данные (`content/`, `corpus/`, `analysis/`, `scripts/`) в репозиторий не входят.  
Деплой хроники с media и оценка места: [docs/guide.md §4](docs/guide.md#перенос-хроники-на-прод-с-media).
