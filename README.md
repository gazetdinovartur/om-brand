# Личный бренд · лендинг

Сайт-визитка с формой заявок, админкой и оплатой по ссылке.

**Стек:** Symfony 8, EasyAdmin, MySQL, Docker (локально).

## Быстрый старт

```bash
cp .env.example .env.local   # задать ADMIN_PASSWORD
docker compose up -d --build
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console app:seed
```

| | |
|--|--|
| Сайт | http://localhost:8085 |
| Админка | http://localhost:8085/admin |

## Документация

**Вся инструкция в одном файле:** [docs/guide.md](docs/guide.md)

Локальная разработка · контент · админка · деплой (NetAngels/VPS) · форма · бэкапы.

## Контент

1. `src/Content/LandingContent.php` — эталон текстов  
2. `php bin/console app:content:sync` — применить в БД  
3. Или админка → Блоки контента
