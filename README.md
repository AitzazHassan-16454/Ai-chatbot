# AI Chatbot

A full-stack AI chatbot powered by Google Gemini, built with Laravel, Vue.js, and Inertia.js.

## Features

- **AI Chat** — Send text messages and images to Gemini AI, with conversation history
- **Image Support** — Upload, drag-drop, or paste images (JPEG, PNG, GIF, WebP, BMP, TIFF)
- **File Analysis** — Upload PDFs, text files, and CSVs for AI analysis
- **Conversation Management** — Auto-save, load, and delete conversations
- **Token System** — Per-user usage limits with progress tracking
- **API Rate Limiting** — System-wide quota with RPM and daily limits
- **Job Alerts** — LinkedIn job search via SerpAPI with email notifications
- **CV Parsing** — Upload a CV to auto-extract job preferences using AI
- **User Profiles** — Manage job preferences and CV uploads
- **Feedback System** — Collect and respond to user feedback via n8n webhooks
- **CLI Chat** — Interactive terminal chat via `php artisan chat`

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13, PHP 8.3+ |
| Frontend | Vue 3, Inertia.js |
| Styling | Tailwind CSS 4 |
| Database | SQLite (default), MySQL |
| AI | Google Gemini 2.5 Flash |
| Build | Vite 8 |

## Requirements

- PHP 8.3+
- Node.js 18+
- Composer
- SQLite or MySQL

## Installation

```bash
# Clone the repository
git clone <repo-url>
cd ai-chatbot

# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Build frontend
npm run build
```

## Configuration

Set these in your `.env` file:

```env
# Required
GEMINI_API_KEY=your-gemini-api-key

# Optional — Job Alerts
SERPAPI_KEY=your-serpapi-key
JOB_ALERTS_API_KEY=your-internal-api-key

# Optional — n8n Webhooks
N8N_WEBHOOK_URL=http://localhost:5678/webhook
N8N_WEBHOOK_SECRET=your-secret
N8N_FEEDBACK_WEBHOOK_URL=http://localhost:5678/webhook/feedback
```

## Development

```bash
# Start all services (server, queue, logs, vite)
composer dev

# Or run individually
php artisan serve
npm run dev
php artisan queue:listen
php artisan pail
```

## Testing

```bash
# Run all tests
composer test

# Or
php artisan test
```

## Project Structure

```
app/
├── Console/Commands/       # Artisan commands (chat, job-alerts)
├── Http/
│   ├── Controllers/        # Request handlers
│   ├── Middleware/          # Inertia middleware
│   └── Requests/           # Form request validation
├── Http/Resources/         # API resource transformers
├── Models/                 # Eloquent models
└── Services/               # Business logic (Gemini, n8n, SerpAPI, CV Parser)

resources/
├── js/
│   ├── Components/         # Vue components (ChatMessage, GeminiIcon, etc.)
│   └── Pages/              # Page components (Chat, Login, Register, Profile)
└── views/                  # Blade templates

routes/
├── web.php                 # Web routes
├── api.php                 # API routes
└── console.php             # Console commands

database/
└── migrations/             # Database migrations

tests/
├── Feature/                # Feature tests
└── Unit/                   # Unit tests
```

## API Routes

| Method | URL | Description | Auth |
|--------|-----|-------------|------|
| GET | `/` | Chat page | No |
| GET | `/login` | Login page | Guest |
| GET | `/register` | Register page | Guest |
| POST | `/register` | Create account | Guest |
| POST | `/login` | Sign in | Guest |
| POST | `/logout` | Sign out | Yes |
| POST | `/chat` | Send message to AI | No |
| GET | `/conversations` | List conversations | Yes |
| GET | `/conversations/{id}` | Load conversation | Yes |
| DELETE | `/conversations/{id}` | Delete conversation | Yes |
| GET | `/profile` | User profile | Yes |
| POST | `/profile/cv` | Upload CV | Yes |
| DELETE | `/profile/cv` | Remove CV | Yes |
| PUT | `/profile/job-preferences` | Update preferences | Yes |
| GET | `/job-alerts` | List job alerts | Yes |
| POST | `/job-alerts/search` | Search jobs | Yes |
| POST | `/feedback` | Submit feedback | No |

## License

MIT
# Ai-chatbot
