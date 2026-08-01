# AI Chatbot - How It Works

A simple guide explaining every feature of this app.

---

## What Is This App?

This is a chatbot app where you can talk to Google's Gemini AI. You can type messages and send images, and the AI will respond. If you create an account, your chats are saved so you can come back later.

---

## Tech Stack (What Tools Are Used)

| Tool | What It Does |
|------|-------------|
| **Laravel** | Backend framework (PHP) - handles routes, database, API calls |
| **Vue.js** | Frontend framework - builds the user interface |
| **Inertia.js** | Connects Laravel and Vue together |
| **Tailwind CSS** | Styling - makes the app look nice |
| **Gemini AI** | Google's AI that generates responses |
| **MySQL** | Database - stores users, conversations, messages |

---

## Features Explained

### 1. Authentication (Register / Login / Logout)

**Files:**
- `app/Http/Controllers/AuthController.php` - Backend logic
- `resources/js/Pages/Register.vue` - Register page
- `resources/js/Pages/Login.vue` - Login page

**How it works:**

#### Register (Creating an account)
1. User fills in: name, email, password, confirm password
2. Backend checks: Is the email already used? Is the password at least 6 characters?
3. If valid, creates a new user in the database
4. Password is encrypted (hashed) so it's never stored as plain text
5. User is automatically logged in and redirected to the chat page

#### Login
1. User enters email and password
2. Backend looks up the email in the database
3. Checks if the password matches (using bcrypt hash comparison)
4. If correct, creates a session (a "you are logged in" cookie)
5. User is redirected to the chat page

#### Logout
1. User clicks the logout button
2. Backend destroys the session
3. User is redirected to the chat page as a guest

**Key concept - Sessions:**
When you log in, Laravel creates a "session" - a temporary file on the server that remembers you are logged in. This is stored in `storage/framework/sessions/`. When you log out, this file is deleted.

---

### 2. Chat (Sending Messages)

**Files:**
- `app/Http/Controllers/ChatController.php` - Backend logic
- `resources/js/Pages/Chat.vue` - Chat interface

**How it works:**

1. User types a message and clicks send (or presses Enter)
2. Frontend sends a POST request to `/chat` with the message text
3. Backend receives the message
4. Backend checks: Does the user have tokens left? Is the API quota available?
5. Backend sends the message to Google's Gemini AI API
6. Gemini processes the message and sends back a reply
7. Backend saves both the user's message and AI's reply to the database
8. Backend sends the reply back to the frontend
9. Frontend displays the AI's reply in the chat

**What is a Token?**
A token is roughly a piece of a word. "Hello" = 1 token. "artificial" = 2-3 tokens. Each user has a limit (default: 1,000,000 tokens). Every message you send uses tokens. This prevents abuse.

---

### 3. Image Handling (Sending Pictures)

**Files:**
- `app/Http/Controllers/ChatController.php` - Validates and sends images to Gemini
- `resources/js/Pages/Chat.vue` - Handles file picking, preview, and display

**How it works:**

#### Adding an Image (3 ways)
1. **Click the paperclip button** - Opens a file picker
2. **Drag and drop** - Drag an image file onto the input area
3. **Paste (Ctrl+V)** - Paste a screenshot from your clipboard

#### What Happens When You Add an Image
1. Browser reads the image file
2. Converts it to a "base64 string" (the image becomes text)
3. Shows a small preview thumbnail above the input
4. You can add up to 5 images per message

#### What is Base64?
Base64 is a way to turn any file (image, PDF, etc.) into plain text. An image becomes a long string of letters and numbers like:
```
/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAMCAgMCAgMDAwMEAwME...
```
We need this because HTTP requests can only send text, not binary files.

#### Sending the Image
1. User clicks send
2. Frontend sends the message text + base64 image data to the server
3. Server validates: Is it a real image? Is it under 20MB?
4. Server saves the message + base64 data in the database
5. Server sends everything (text + images) to Gemini AI
6. Gemini reads the image and responds (like "This is a cat sitting on a chair")
7. The image is stored in the database so it shows up when you reload the conversation

#### Allowed Image Types
- JPEG (.jpg, .jpeg)
- PNG (.png)
- GIF (.gif)
- WebP (.webp)

#### Maximum Size
- 20 MB per image
- 5 images per message

---

### 4. Conversation Management (Saving Chats)

**Files:**
- `app/Http/Controllers/ConversationController.php` - Backend logic
- `resources/js/Pages/Chat.vue` - Sidebar UI

**How it works:**

#### Auto-Save
- When a logged-in user sends a message, a conversation is automatically created
- Each new conversation gets a title (first 50 characters of the first message)
- All messages in that conversation are saved to the database

#### Loading Conversations
- The sidebar shows a list of all your saved conversations
- Clicking a conversation loads all its messages
- Conversations are sorted by most recently updated

#### Deleting Conversations
- Hover over a conversation in the sidebar
- Click the X button
- The conversation and all its messages are permanently deleted

#### Guest Users
- Guests (not logged in) can chat, but nothing is saved
- When they refresh the page, the chat is gone

**Database tables involved:**
- `conversations` - stores conversation id, user id, title
- `messages` - stores message id, conversation id, role (user/assistant), content, attachments

---

### 5. Token System (Usage Limits)

**Files:**
- `app/Models/User.php` - Token tracking on user model
- `app/Models/SystemUsage.php` - System-wide quota tracking

**How it works:**

#### Per-User Tokens
- Each user has `tokens_used` and `tokens_limit` columns in the database
- Default limit: 1,000,000 tokens per user
- Every message sent uses some tokens (depends on message length)
- If a user hits their limit, they can no longer send messages
- A progress bar in the sidebar shows usage percentage

#### System-Wide Quota
- There's a global API quota for all users combined
- When the system quota is exhausted, ALL users are blocked
- The quota resets daily
- Status indicators show: OK (green), Warning at 75% (amber), Critical at 90% (orange), Exhausted at 100% (red)

#### Token Display
- Header shows: "150K / 1M" (used / limit)
- Sidebar shows a progress bar
- Color changes: blue (safe), amber (70%+), red (90%+)

---

### 6. API Status Monitoring

**File:** `app/Models/SystemUsage.php`

**How it works:**

- Tracks total tokens used by ALL users today
- Compares against a daily limit
- Provides status levels:
  - **healthy** - Under 75% usage
  - **warning** - 75-89% usage
  - **critical** - 90-99% usage
  - **exhausted** - 100% usage (system blocked)
- Shows when the quota resets (tomorrow)
- Displayed in the header as a colored badge

---

## Routes (All URLs)

| Method | URL | What It Does | Who Can Use It |
|--------|-----|-------------|----------------|
| GET | `/` | Show the chat page | Everyone |
| GET | `/login` | Show the login page | Guests |
| GET | `/register` | Show the register page | Guests |
| POST | `/register` | Create a new account | Guests |
| POST | `/login` | Log in | Guests |
| POST | `/logout` | Log out | Logged-in users |
| GET | `/api/user` | Get current user info | Everyone |
| POST | `/chat` | Send a message to AI | Everyone |
| GET | `/conversations` | List all conversations | Logged-in users |
| GET | `/conversations/{id}` | Load one conversation | Logged-in users |
| DELETE | `/conversations/{id}` | Delete a conversation | Logged-in users |

---

## Database Tables

### users
| Column | Type | Description |
|--------|------|-------------|
| id | integer | Unique user ID |
| name | string | User's name |
| email | string | User's email (unique) |
| password | string | Encrypted password |
| tokens_used | integer | How many tokens used (default: 0) |
| tokens_limit | integer | Max tokens allowed (default: 1,000,000) |
| created_at | timestamp | When the account was created |

### conversations
| Column | Type | Description |
|--------|------|-------------|
| id | integer | Unique conversation ID |
| user_id | integer | Who owns this conversation |
| title | string | Conversation title (first 50 chars) |
| created_at | timestamp | When it was created |
| updated_at | timestamp | When the last message was sent |

### messages
| Column | Type | Description |
|--------|------|-------------|
| id | integer | Unique message ID |
| conversation_id | integer | Which conversation it belongs to |
| role | string | "user" or "assistant" |
| content | text | The message text |
| attachments | json | Images as base64 (nullable) |
| created_at | timestamp | When it was sent |

### system_usage
| Column | Type | Description |
|--------|------|-------------|
| id | integer | Unique ID |
| date | date | Which day |
| tokens_used | bigint | Total tokens used today |
| created_at | timestamp | When the record was created |

---

## File Structure

```
app/
  Http/
    Controllers/
      AuthController.php        - Register, login, logout
      ChatController.php        - Send messages to AI
      ConversationController.php - Save/load/delete conversations
    Middleware/
      HandleInertiaRequests.php - Shares user data with Vue
  Models/
    User.php                    - User model with token tracking
    Conversation.php            - Conversation model
    Message.php                 - Message model (stores images)
    SystemUsage.php             - API quota tracking

resources/
  js/
    Pages/
      Chat.vue                  - Main chat page (everything happens here)
      Login.vue                 - Login form
      Register.vue              - Registration form

routes/
  web.php                       - All route definitions

database/
  migrations/                   - Database table definitions
```

---

## How Data Flows (Step by Step)

### Sending a Text Message
```
User types "Hello" → clicks Send
    ↓
Vue sends POST /chat { message: "Hello" }
    ↓
ChatController receives it
    ↓
Sends to Gemini API
    ↓
Gemini replies "Hi! How can I help?"
    ↓
ChatController saves both messages to database
    ↓
Sends reply back to Vue
    ↓
Vue displays "Hi! How can I help?"
```

### Sending an Image
```
User picks "cat.jpg" → preview shows
    ↓
User types "What is this?" → clicks Send
    ↓
Vue reads cat.jpg as base64 text
    ↓
Vue sends POST /chat { message: "What is this?", attachments: [{mime_type, data, name}] }
    ↓
ChatController validates the image (type? size?)
    ↓
Sends text + base64 image to Gemini API
    ↓
Gemini "sees" the image and replies "This is a cat sitting on a red couch"
    ↓
ChatController saves message + image data to database
    ↓
Sends reply back to Vue
    ↓
Vue displays the image + AI's description
```

### Loading a Saved Conversation
```
User clicks "My conversation" in sidebar
    ↓
Vue sends GET /conversations/42
    ↓
ConversationController loads all messages from database
    ↓
Returns messages with their images (base64 data)
    ↓
Vue displays each message (images shown inline via data URLs)
```
