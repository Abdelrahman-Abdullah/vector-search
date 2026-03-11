# Vector Search API (Laravel + pgvector)

A Laravel API for uploading documents, chunking text, generating embeddings with Gemini, and querying semantic similarity using PostgreSQL `pgvector`.

## Stack

- Laravel 12
- PostgreSQL 16 + `pgvector`
- Redis (cache)
- Queue workers for async chunk + embedding processing
- Gemini embeddings (`batchEmbedContents`)

## API Endpoints

- `POST /api/articles/upload`
- `GET /api/articles/search/vector?q=...&top=5`
- `GET /api/articles/{id}/status`
- `GET /api/articles/{id}/chunks?q=...&top=10`

All endpoints are currently rate limited via `throttle:30,1`.

## Quick Start

1. Start infrastructure:

```bash
docker compose up -d
```

2. Install dependencies:

```bash
composer install
npm install
```

3. Configure environment:

```bash
cp .env.example .env
php artisan key:generate
```

4. Migrate DB:

```bash
php artisan migrate
```

5. Start app + queue worker:

```bash
php artisan serve
php artisan queue:work --queue=embeddings
```

## Environment Variables

Important keys in `.env`:

- `DB_CONNECTION=pgsql`
- `DB_HOST=127.0.0.1`
- `DB_PORT=5432`
- `DB_DATABASE=vector_search`
- `DB_USERNAME=root`
- `DB_PASSWORD=secret`
- `REDIS_HOST=127.0.0.1`
- `GEMINI_API_KEY=...`
- `GEMINI_EMBEDDING_MODEL=gemini-embedding-001`
- `GEMINI_EMBEDDING_DIMENSIONS=1536`
- `EMBEDDING_CACHE_TTL=86400`

## Processing Flow

1. Upload file (`txt`, `md`, `csv`, `pdf`)
2. Parse to plain text
3. Store article row and dispatch `ProcessArticleJob`
4. Chunk text with overlap
5. Generate embeddings (batched + cached)
6. Store chunk embeddings in `article_chunks.embedding`
7. Search by nearest neighbors with pgvector cosine distance (`<=>`)

## Notes

- `embedding` vector dimensions must match:
  - migration column dimension
  - `services.gemini.embedding_dimensions`
- `search/vector` returns top unique articles by best chunk match.
- `articles/{id}/chunks` performs in-DB vector ranking (no in-memory full sort).

## Tests

Run:

```bash
php artisan test
```
