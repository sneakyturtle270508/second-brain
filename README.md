# Second Brain - AI-Powered Knowledge Management System

A Laravel-based personal knowledge management system that uses local AI (Ollama) to search, summarize, and interact with your notes intelligently.

## Features

### 🧠 AI-Powered Search & Chat
- **Semantic Search**: Uses vector embeddings to find relevant notes based on meaning, not just keywords
- **Contextual Chat**: Ask questions about your notes and get AI-generated answers with source citations
- **Weekly Summaries**: Get AI-powered summaries of notes updated in the last 7 days
- **Smart Suggestions**: Pre-built prompts for common tasks (weekly summaries, next steps, themes)

### 📝 Content Management
- **Statamic CMS Integration**: Built on Statamic for powerful content management
- **Articles/Notes**: Create and manage knowledge articles with rich text (Bard editor)
- **Topics & Tags**: Organize content with taxonomies for better filtering
- **Metadata Tracking**: Automatic tracking of creation and update timestamps

### 🔍 Vector Search System
- **Embedding Generation**: Automatic vector embeddings using Ollama (nomic-embed-text)
- **Cosine Similarity**: Efficient similarity scoring for semantic search
- **Chunked Processing**: Handles large documents by splitting and averaging embeddings
- **Content Hashing**: Avoids re-embedding unchanged content

### 🎯 Smart Filtering
- **Tag-based Filtering**: Filter chat context by specific tags
- **Collection Filtering**: Scope searches to specific content collections
- **Recent Content Detection**: Automatically includes recent notes when relevant
- **Dynamic Thresholds**: Adjusts similarity thresholds based on query complexity

### 💻 Modern UI
- **ChatGPT-style Interface**: Familiar chat-based interaction pattern
- **Real-time Updates**: Instant message display with loading states
- **Source Cards**: Visual display of source documents with direct links
- **Responsive Design**: Works on desktop and mobile devices
- **Dark Mode**: Modern dark theme with gradient accents

### 🛠️ Developer Features
- **Artisan Commands**: 
  - `php artisan ai:index` - Index all entries with embeddings
  - `php artisan ai:index {collection}` - Index specific collection
- **API Endpoints**:
  - `POST /ai/ask` - Ask questions about your notes
  - `POST /ai/search` - Semantic search across documents
  - `GET /ai/ping` - Health check
- **Local AI**: No external API dependencies, runs entirely on Ollama
- **Content Hash Tracking**: Efficient re-indexing by detecting changes

### 📊 Data Management
- **SQLite Database**: Lightweight local database for storing embeddings
- **JSON Storage**: Efficient vector storage in JSON format
- **Migration System**: Easy database schema updates
- **Automatic Indexing**: Optional hooks for automatic re-indexing on content changes

## Tech Stack

- **Backend**: Laravel 12, PHP 8.2+
- **CMS**: Statamic 5.0
- **AI**: Ollama (llama3.2:3b, nomic-embed-text)
- **Frontend**: Vanilla JavaScript, Vite, Tailwind CSS 4
- **Database**: SQLite
- **Styling**: Custom CSS with dark theme

## Setup

### Prerequisites
- PHP 8.2+
- Composer
- Node.js & npm
- Ollama installed and running

### Installation

1. Clone the repository
```bash
git clone <your-repo>
cd second-brain
```

2. Install PHP dependencies
```bash
composer install
```

3. Install Node dependencies
```bash
npm install
```

4. Copy environment file
```bash
cp .env.example .env
```

5. Generate application key
```bash
php artisan key:generate
```

6. Configure Ollama in `.env`
```env
OLLAMA_URL=http://127.0.0.1:11434
OLLAMA_CHAT_MODEL=llama3.2:3b
OLLAMA_EMBED_MODEL=nomic-embed-text:latest
```

7. Run migrations
```bash
php artisan migrate
```

8. Create a Statamic user
```bash
php please make:user
```

9. Index your content
```bash
php artisan ai:index articles
```

10. Build frontend assets
```bash
npm run build
# or for development
npm run dev
```

11. Start the server
```bash
php artisan serve
```

## Usage

### Creating Content
1. Log in to the Statamic control panel at `/cp`
2. Navigate to Collections > Articles
3. Create a new article with title, summary, topics, and content
4. Content is automatically indexed on save (if configured)

### Using the AI Chat
1. Visit the homepage
2. Type questions about your notes in the chat interface
3. Use quick action buttons for common tasks
4. Click on source cards to view full articles

### Manual Indexing
Re-index all articles:
```bash
php artisan ai:index articles
```

Force re-index (ignores content hash):
```bash
php artisan ai:index articles --force
```

## Configuration

### Embedding Model
Change the embedding model in `.env`:
```env
OLLAMA_EMBED_MODEL=nomic-embed-text:latest
```

### Chat Model
Change the chat model in `.env`:
```env
OLLAMA_CHAT_MODEL=llama3.2:3b
```

### Search Thresholds
Adjust similarity thresholds in `routes/web.php`:
```php
$threshold = match (true) {
    $wordCount <= 2 => 0.35,  // Short queries need higher similarity
    $wordCount <= 5 => 0.3,
    $wordCount <= 9 => 0.27,
    default => 0.24,
};
```

## API Reference

### POST /ai/ask
Ask questions about indexed content.

**Request:**
```json
{
  "q": "What did I learn this week?",
  "k": 5,
  "collection": "articles",
  "tag": "learning",
  "include_recent": true
}
```

**Response:**
```json
{
  "answer": "Based on your notes...",
  "sources": [...],
  "primary_source": {...},
  "secondary_sources": [...]
}
```

### POST /ai/search
Semantic search across documents.

**Request:**
```json
{
  "q": "machine learning",
  "limit": 5
}
```

**Response:**
```json
{
  "ok": true,
  "q": "machine learning",
  "results": [...]
}
```

## Project Structure

```
second-brain/
├── app/
│   ├── Console/Commands/
│   │   ├── AiIndexEntries.php    # Index all entries
│   │   └── AiIndexNotes.php      # Index specific notes
│   ├── Http/Controllers/
│   │   └── AiController.php      # API endpoints
│   ├── Models/
│   │   └── AiDocument.php        # Vector document model
│   └── Services/
│       └── AiService.php         # Ollama integration
├── content/
│   ├── collections/articles/     # Your notes
│   └── taxonomies/               # Topics and tags
├── database/migrations/          # Database schema
├── resources/
│   ├── css/site.css             # Frontend styles
│   ├── js/site.js               # Chat interface logic
│   └── views/                   # Blade/Antlers templates
└── routes/
    ├── web.php                  # Web routes
    └── api.php                  # API routes (legacy)
```

## License

This project uses Statamic, which requires a license for production use. See [Statamic licensing](https://statamic.com/pricing) for details.

## Contributing

This is a personal project, but suggestions and improvements are welcome!

## Troubleshooting

### Ollama connection issues
- Ensure Ollama is running: `ollama serve`
- Check Ollama URL in `.env`
- Verify models are downloaded: `ollama list`

### Embedding errors
- If text is too long, it will be automatically chunked
- Check `storage/logs/laravel.log` for detailed errors
- Verify model supports embedding: `ollama show nomic-embed-text`

### Search returns no results
- Lower the similarity threshold in `routes/web.php`
- Verify content is indexed: check `ai_documents` table
- Test with longer, more specific queries