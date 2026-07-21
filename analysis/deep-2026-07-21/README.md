# Углублённый анализ контента · 21 июля 2026

Развёрнутый психолингвистический, интегральный и практический разбор публичного и полупубличного корпуса **Артура Газетдинова / Луна** (arturlun.ru).

## Что внутри

| Файл | Содержание |
|------|------------|
| [00-methodology-and-sources.md](00-methodology-and-sources.md) | Метод, источники, ограничения |
| [01-language-profile.md](01-language-profile.md) | Язык: лексика, синтаксис, ритм, регистры |
| [02-consciousness-map.md](02-consciousness-map.md) | Карта сознания (AQAL-lite), квадранты |
| [03-personality-facets.md](03-personality-facets.md) | Грани личности, архетипы, роли |
| [04-strengths-weaknesses.md](04-strengths-weaknesses.md) | Сильные и слабые стороны |
| [05-blind-spots.md](05-blind-spots.md) | Слепые пятна — что не замечаешь о себе |
| [06-content-landscape.md](06-content-landscape.md) | Какой контент генерируешь и какой можешь |
| [07-development-paths.md](07-development-paths.md) | Направления человеческого и профессионального роста |
| [08-therapy-and-selfhelp.md](08-therapy-and-selfhelp.md) | Методы терапии и самопомощи |
| [09-executive-summary.md](09-executive-summary.md) | Краткое резюме для автора |
| [10-channel-architecture.md](10-channel-architecture.md) | Архитектура каналов и тематических тегов |
| [eras/](eras/) | Зеркала эпох (биография как география) |

## Как обновить анализ

Когда локально есть `content/` и `corpus/chronicle_entries.jsonl`:

```bash
python3 scripts/analyze_corpus_deep.py
```

Скрипт пересчитает статистику языка, распределение по эпохам/каналам/тегам и допишет `data/corpus-stats.json`.

## Дисклеймер

Это не клиническая диагностика и не замена работы с живым специалистом. Анализ построен на текстах, метаданных хроники и публичных материалах. Интерпретации — гипотезы для размышления, а не приговоры.
