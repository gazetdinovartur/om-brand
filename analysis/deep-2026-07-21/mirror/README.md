# Corpus mirrors

Автогенерируемые зеркала из `corpus/chronicle_entries.jsonl`.

**Пока не сгенерировано.** Запусти локально (нужны `content/` и `corpus/`):

```bash
python3 scripts/corpus_build.py --instagram   # если обновлялся content/
python3 scripts/run_analysis_pipeline.py
```

Появятся:
- `by-era/` — срезы по эпохам
- `by-channel/` — срезы по каналам
- `manifest.json` — индекс
- `data/era-slices.json`, `data/channel-slices.json`

Качественный анализ (ручной): [`../eras/`](../eras/)
