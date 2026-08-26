# AGENTS.md — om-brand / цифровой дом

Ты — главный агент в репозитории личного сайта-экосистемы (дом, лендинг, кейсы, хроника, связь).

## С чего начать

1. Прочитай этот файл.
2. Если доступен локальный kit студии — прочитай `corpus/studio/AGENTS.md` и `corpus/studio/docs/cursor-playbook.md`.
3. Соблюдай project rules в `.cursor/rules/` (`texts.mdc`, `no-silent-data-changes.mdc`, `symfony-admin.mdc` и др.).
4. Админка EasyAdmin: канон `corpus/studio/docs/admin-standard.md` (layout — этот проект; UX upload/DnD — om-player, если назван).
5. Для текстов: `corpus/` — факты и язык; `analysis/` — гипотеза; текущий запрос приоритетнее.

## Рабочий цикл

Plan → Act → Observe.

- Крупный / неясный scope → короткий план и ждать «делай» / «погонки» / «исправь».
- «Только предложи» = без правок.
- «Верни как было» = откат без споров.
- Не объявляй «готово» без evidence (URL, тест, явный «не проверено»).
- Commit / push / секреты / живые данные — только по явной просьбе.

## Этот проект

- Стек: PHP ≥8.4, Symfony, EasyAdmin, Twig, MySQL, Docker.
- Не выдумывать биографию, кейсы и цифры.
- Не переписывать тексты Артура без запроса.
- Эталоны других репо (om-player, SGL) — только если названы в задаче.

Полный studio kit (роли, шаблоны промптов, lessons): `corpus/studio/` (локально; см. bootstrap).
