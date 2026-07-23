# Laravel Lern- & Onboarding-Begleiter

Walking Skeleton (M1): Laravel 13 + Neuron AI, lokal mit **Ollama** und **Chroma** als Docker-Container.

Alles liegt unter `D:\lern-begleiter-laravel` – Code, Compose-Stack und persistente Daten (`./data`).

## Voraussetzungen

- Docker Desktop + Docker Compose
- PHP 8.4 + Composer (für lokale Checks/CI-Parität)
- Node 20+ (Vite/Tailwind, optional lokal)

## Schnellstart (kompletter Stack)

```bash
cd D:\lern-begleiter-laravel
cp .env.example .env
docker compose up --build
```

Was startet:

| Dienst | Rolle | Host-Port |
|---|---|---|
| `app` | Laravel (`artisan serve`) | `http://localhost:8080` |
| `queue` | Queue-Worker | – (nur intern) |
| `ollama` | LLM + Embeddings | – (nur Compose-Netz) |
| `chroma` | Vektor-Store | – (nur Compose-Netz) |
| `ollama-init` | zieht `nomic-embed-text` + `qwen2.5:1.5b` einmalig | – |

Persistenz unter `D:\lern-begleiter-laravel\data`:

- `data/ollama` – Ollama-Modelle
- `data/chroma` – Chroma-Index
- `data/app/database.sqlite` – SQLite (Systemzustand)

Ollama/Chroma sind absichtlich **nicht** auf den Host gemappt (Security-Prüfpunkt Spec M1).

## Neuron-Smoke (manuell, kein CI-Test)

Nach `docker compose up` und erfolgreichem `ollama-init`:

```bash
docker compose exec app php artisan tinker
```

```php
\App\Neuron\SmokeAgent::make()->chat(new \NeuronAI\Chat\Messages\UserMessage('ping'))->getMessage()->getContent();
```

(In Tinker keine `use`-Statements und kein vorangestelltes `php` — eine Zeile mit FQCN.)

Erwartung: eine kurze textuelle Antwort vom lokalen Modell `qwen2.5:1.5b`.

Hinweise:
- Erst nach erfolgreichem Modell-Pull (`ollama list` zeigt `qwen2.5:1.5b`).
- Timeout 180s; Antwortlänge begrenzt (`num_predict: 128`). Parallel keine zweite Ollama-Anfrage.
- Optional schwerer: `OLLAMA_MODEL=qwen3:4b` (Qualität, auf CPU oft zu langsam).
- Kein echter LLM-Call in Pest.

## Qualitätssicherung (lokal)

```bash
vendor/bin/pint
vendor/bin/phpstan analyse
vendor/bin/pest
```

CI (GitHub Actions) führt dieselben drei Checks aus.

## Projektstruktur (AI-Kern)

- `app/Contracts/` – App-Interfaces (ab AP-2/3)
- `app/Data/Dto/` – readonly DTOs
- `app/Services/` – Fachlogik
- `app/Neuron/` – Neuron-Agenten/RAG
- `config/neuron.php` – Provider, Embeddings, Vector Stores
- `config/companion.php` – App-Defaults (Modelle, Timeouts, topK)

## Hinweise

- Default-Chat-Modell: `qwen2.5:1.5b` (CPU-Dev). Optional `qwen3:4b` / `qwen3:8b` via `OLLAMA_MODEL` bei mehr RAM/GPU.
- Embedding-Modell: `nomic-embed-text`.
- Livewire: Spec nennt v3; mit Laravel 13 ist nur Livewire 4 installierbar – dokumentiert in AP-1.
