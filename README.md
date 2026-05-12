# 🎙️ Yantra Voice Studio

AI-powered Text-to-Speech application using **Google Gemini 3.1 Flash TTS** via OpenRouter.

## ✨ Features

- 🌐 **Bilingual** — English & Nepali language support
- 🎭 **7 Voice Models** — Puck, Charon, Kore, Fenrir (EN) + Leda, Zephyr, Aoede (NE)
- 😊 **6 Emotion Presets** — Neutral, Cheerful, Professional, Urgent, Calm, Storyteller
- 🔒 **Secure** — API keys stored in encrypted server sessions (not localStorage)
- ⚡ **Rate-Limited** — 10 requests/min per IP to prevent abuse
- 🎨 **Premium UI** — Dark glassmorphism design with smooth animations

## 🛠️ Tech Stack

- **Backend:** Laravel 12, PHP 8.2
- **Database:** SQLite (MySQL-ready)
- **Server:** XAMPP Apache
- **API:** OpenRouter → Google Gemini TTS
- **Frontend:** Blade + Tailwind CSS + Vanilla JS

## 🚀 Setup

```bash
# 1. Clone & install
git clone https://github.com/betterdreamsnepal-commits/yantravoice.git
cd yantravoice && composer install

# 2. Configure
cp .env.example .env
php artisan key:generate

# 3. Database
touch database/database.sqlite
php artisan migrate

# 4. Deploy to XAMPP
bash setup.sh
```

Visit: `http://localhost/Yantravoice/public/`

## 📄 License

MIT
