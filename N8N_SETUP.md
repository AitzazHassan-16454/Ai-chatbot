# n8n Workflow Setup Guide

## Feedback Notification Workflow

This workflow handles feedback notifications from the AI Chatbot application.

### Setup Steps

1. **Import the Workflow**
   - Open n8n (http://localhost:5678)
   - Go to Workflows → Import from File
   - Select `n8n-workflow-feedback.json`

2. **Configure Environment Variables in n8n**
   - Go to Settings → Environment Variables
   - Add:
     - `TELEGRAM_CHAT_ID` - Your Telegram chat ID (for Telegram notifications)
     - `FEEDBACK_EMAIL` - Email address to receive feedback notifications

3. **Enable Notification Nodes**
   - By default, Telegram and Email nodes are disabled
   - Enable the ones you want to use:
     - Click on the node
     - Toggle "Active" in the node settings

4. **Activate the Workflow**
   - Click "Active" toggle in the top-right corner
   - The webhook URL will be: `http://localhost:5678/webhook/chatbot-webhook`

### Webhook Events

The workflow handles these events from the chatbot:

| Event | Description | Actions |
|-------|-------------|---------|
| `feedback` | User submitted feedback | Notify admin via Telegram/Email |
| `feedback-response` | Send thank you email to user | Send professional email to user |
| `chat-message` | New chat message sent | Log/notify |
| `token-alert` | User reached 70%+ token usage | Alert admin |
| `system-alert` | System-wide alert | Alert admin |

### Email Configuration

To send emails from n8n, configure SMTP credentials:

1. Go to **Credentials** in n8n
2. Add new **SMTP** credential
3. Enter your email provider details:
   - Host: `smtp.gmail.com`
   - Port: `465`
   - User: `your-email@gmail.com`
   - Password: `your-app-password`
4. Attach this credential to the email nodes

### Testing

Test the feedback notification:

```bash
curl -X POST http://localhost:5678/webhook/chatbot-webhook \
  -H "Content-Type: application/json" \
  -d '{
    "event": "feedback",
    "payload": {
      "feedback_id": 1,
      "user_id": 1,
      "user_name": "Test User",
      "user_email": "test@example.com",
      "category": "general",
      "message": "This is a test feedback message",
      "rating": 5,
      "page_context": "sign-out"
    },
    "timestamp": "2025-07-16T12:00:00+00:00"
  }'
```

Test the thank you email:

```bash
curl -X POST http://localhost:5678/webhook/chatbot-webhook \
  -H "Content-Type: application/json" \
  -d '{
    "event": "feedback-response",
    "payload": {
      "to_email": "user@example.com",
      "to_name": "Test User",
      "feedback_id": 1,
      "category": "general",
      "message": "This is a test feedback message",
      "rating": 5,
      "submitted_at": "2025-07-16T12:00:00+00:00"
    },
    "timestamp": "2025-07-16T12:00:00+00:00"
  }'
```

### Laravel .env Configuration

Make sure your `.env` file has:

```env
N8N_WEBHOOK_URL=http://localhost:5678/webhook/chatbot-webhook
N8N_WEBHOOK_SECRET=your-webhook-secret-here
```

### Security

- The webhook secret is sent as `X-Webhook-Secret` header
- Verify this header in your n8n workflow for production use
- Use HTTPS in production
