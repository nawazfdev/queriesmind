# SiteBotAI Backend

SiteBotAI is a Laravel API backend for training chatbots on three knowledge-source types:
- uploaded documents
- free text entered directly by the user
- crawled website content

Embeddings and vector search are handled by an external FastAPI + ChromaDB service. Chat generation is handled through the OpenRouter-compatible API using NVIDIA models.

## Architecture

### Core flow
1. A training source is submitted from the API.
2. Laravel creates a `documents` row and a `chatbot_training_sources` row.
3. Laravel queues `GenerateEmbeddingJob` or `CrawlWebsiteJob`.
4. Content is chunked in Laravel.
5. Chunks are sent to the FastAPI Chroma service.
6. FastAPI creates embeddings and stores vectors in ChromaDB.
7. During chat, Laravel queries FastAPI for the most relevant chunks.
8. Retrieved context is passed to the text model to generate the answer.

### Services involved
- `app/Http/Controllers/DocumentController.php`
  Handles file upload and free-text training.
- `app/Http/Controllers/WebsiteController.php`
  Queues website crawling.
- `app/Jobs/CrawlWebsiteJob.php`
  Crawls a website, builds a document, then dispatches embedding.
- `app/Jobs/GenerateEmbeddingJob.php`
  Chunks and indexes one document.
- `app/Services/WebsiteCrawlerService.php`
  Crawls same-host HTML pages and extracts text plus links.
- `app/Services/TextChunker.php`
  Splits content into chunks for vector indexing.
- `app/Services/ChromaService.php`
  Calls the FastAPI service for add/query/delete/health.
- `app/Services/EmbeddingService.php`
  Resolves content, chunks it, indexes it, and performs retrieval.
- `app/Services/ChatService.php`
  Retrieves context and generates the final answer.
- `app/Services/OpenRouterService.php`
  Sends chat requests to the text model provider.
- `app/Services/TenantResolver.php`
  Resolves tenant context from auth, API keys, or chatbot id.

## Supported training sources

### 1. Website crawl
Endpoint:
`POST /api/add-website`

Request body:
```json
{
  "chatbot_id": 2,
  "url": "https://example.com",
  "name": "Example Website"
}
```

What happens:
- the URL is queued for crawling
- pages on the same host are discovered from anchor links
- page title, page URL, body text, and anchor links are added to the indexed content
- one combined `Document` is created for the crawl
- chunk embeddings are generated and stored in ChromaDB

Notes:
- crawler only indexes HTML responses
- crawler only follows links on the same host
- JS-rendered/SPA-only content may not appear if the raw HTML does not contain it
- linked PDFs are not yet parsed as documents during crawl

### 2. Document upload
Endpoint:
`POST /api/upload-document`

Form-data:
- `chatbot_id`: integer
- `file`: file

Current support:
- `.txt` works for indexing
- `.pdf` and `.docx` uploads are accepted by validation but are not text-extracted yet, so they are not usable for embedding until extraction is added

### 3. Free text training
Endpoint:
`POST /api/train-text`

Request body:
```json
{
  "chatbot_id": 2,
  "title": "Shipping Policy",
  "content": "Shipping takes 3 to 5 business days. Returns are allowed within 30 days with receipt."
}
```

What happens:
- Laravel creates a `Document` with `source_type = text`
- the text is chunked and indexed like any other source
- the source appears in the chatbot training list

## Chat flow
Endpoint:
`POST /api/chat`

Request body:
```json
{
  "chatbot_id": 2,
  "question": "How long does shipping take?",
  "session_id": "postman-test-1"
}
```

What happens:
1. Tenant is resolved.
2. Question is used to query FastAPI Chroma.
3. Top matching chunks are returned.
4. Laravel joins the chunks into one context string.
5. The text model generates the final answer.
6. The chat is stored in the database.

## Why a website answer can miss a link
A website question such as “what is the resume link?” can fail even when the page looks correct in the browser. Common reasons:
- the link lives on another page that was not crawled yet
- the link is rendered by JavaScript after page load
- the resume points to a PDF or external file and only the anchor element was visible
- the question wording does not semantically match the crawled text strongly enough

This project now improves website indexing by storing:
- page title
- page URL
- body text
- anchor text with href values

That makes questions about links more likely to match.

## Queue requirements
Training uses queued jobs. Run this while testing:
```bash
php artisan queue:work
```

## Environment variables
Important env values:
```env
APP_URL=http://localhost:8000
QUEUE_CONNECTION=database
CACHE_STORE=database
QUERYMIND_CACHE_STORE=database

CHROMA_HOST=http://127.0.0.1:8001
CHROMA_ENABLE_DELETE_ENDPOINT=false

NVIDIA_TEXT_API_KEY=
OPENROUTER_CHAT_MODEL=nvidia/nemotron-3-super-120b-a12b:free
OPENROUTER_EMBEDDING_MODEL=nvidia/llama-nemotron-embed-vl-1b-v2:free
```

## FastAPI Chroma service contract
Laravel expects the FastAPI service to expose:
- `GET /health`
- `POST /add_document`
- `POST /query`
- optional `POST /delete_document`

### `POST /add_document`
```json
{
  "id": "doc_1_chunk_0",
  "text": "chunk text",
  "collection_name": "tenant_2_chatbot_2"
}
```

### `POST /query`
```json
{
  "query": "What is the return policy?",
  "top_k": 5,
  "collection_name": "tenant_2_chatbot_2"
}
```

Expected response shape:
```json
{
  "query": "What is the return policy?",
  "results": [
    {
      "id": "doc_1_chunk_0",
      "text": "Returns are allowed within 30 days with receipt.",
      "distance": 0.12
    }
  ]
}
```

## API summary

### Auth
- `POST /api/register`
- `POST /api/login`

### Chat
- `POST /api/chat`

### Chatbot management
- `GET /api/chatbots`
- `POST /api/chatbots`
- `GET /api/chatbots/{chatbot}`
- `GET /api/chatbots/{chatbot}/training`

### Training
- `POST /api/upload-document`
- `POST /api/train-text`
- `POST /api/add-website`

## Recommended testing order
1. Start Laravel.
2. Start FastAPI Chroma.
3. Run `php artisan queue:work`.
4. Create or use a chatbot.
5. Train with free text.
6. Train with a website.
7. Ask `/api/chat` questions that directly match the training data.

## Current limitations
- PDF and DOCX extraction are not implemented yet.
- Website crawling is raw HTML based, not browser-rendered.
- Crawl depth is effectively controlled by page discovery and `QUERYMIND_CRAWLER_MAX_PAGES`.
- Chunk-level source metadata is stored in the DB, but FastAPI query responses currently return only `id`, `text`, and `distance`.

## Suggested next improvements
- add PDF text extraction
- add DOCX text extraction
- return source URL and chunk metadata from FastAPI query results
- expose matched chunks in `/api/chat` responses for debugging
- support JS-rendered websites using a headless browser crawler
- add recrawl/update endpoints for website sources
