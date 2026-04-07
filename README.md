# Telegram Menfess Bot

Telegram-based anonymous confession (menfess) bot with **tier-based daily submission limits** for monetization.

## Features

- Anonymous message submission via Telegram bot
- **Tier-based daily submission limits** (monetization)
- Admin approval workflow for content moderation
- Automatic posting to Telegram channel upon approval
- MySQL database with InnoDB engine
- Logging and error handling
- Command interface for users and administrators
- Webhook and polling mode support
- Queue system with configurable posting interval

## Tier System

Users are assigned to tiers that determine how many menfess they can submit per day:

| Tier | Limit/Hari | Harga |
|------|-----------|-------|
| **free** | 3 | Gratis |
| **silver** | 10 | Rp 15.000/bln |
| **gold** | 30 | Rp 30.000/bln |
| **unlimited** | Tidak terbatas | Rp 50.000/bln |

### User Commands

| Command | Deskripsi |
|---------|-----------|
| /start | Mulai bot dan lihat info tier |
| /help | Panduan penggunaan |
| /status | Lihat sisa limit menfess hari ini |
| /tiers | Lihat daftar tier yang tersedia |

## Setup

### 1. Install Dependencies

```bash
composer install
```

### 2. Configure Environment

Copy .env.example to .env and edit the values:

```env
TELEGRAM_BOT_TOKEN=your_bot_token_here
TARGET_CHANNEL_ID=your_target_channel_id_here
DB_HOST=localhost
DB_NAME=menfess_bot
DB_USERNAME=root
DB_PASSWORD=
WEBHOOK_URL=https://yourdomain.com/botmenfess
USE_WEBHOOK=false
ADMIN_TELEGRAM_IDS=
POST_INTERVAL_MINUTES=1
```

### 3. Setup Database

```bash
mysql -u root -p < database_schema.sql
```

This creates the following tables:
- users - User analytics (NOT linked to submissions for anonymity)
- submissions - Incoming messages with status tracking
- channel_posts - Tracks posted submissions
- **tiers** - Tier definitions (free, silver, gold, unlimited)
- **user_tiers** - Maps users to their subscribed tier
- **daily_usage** - Tracks daily submission counts per user

### 4. Configure Telegram Bot

- Create a bot via @BotFather and get the API token
- Add the bot as administrator to your target channel
- Get the channel ID (forward a message from the channel to @userinfobot)

### 5. Run the Bot

**Polling mode (development):**
```bash
php index.php
```

**Webhook mode (production):**
1. Set USE_WEBHOOK=true in .env
2. Configure your web server to route to webhook.php
3. Ensure HTTPS with valid SSL certificate

## How the Limit System Works

1. When a user sends a menfess, the bot checks their **tier** from user_tiers
2. The bot queries daily_usage for today's submission count
3. If count >= daily_limit the submission is rejected with a message to upgrade
4. If within limit, the submission is stored and daily_usage.count is incremented
5. Users can check their remaining limit anytime with /status

### Assigning Tiers (Admin)

Tiers are managed directly in the database:

```sql
INSERT INTO user_tiers (user_id, tier_id, expires_at)
VALUES (1, 2, NOW() + INTERVAL 1 MONTH);
```

Where tier_id corresponds to the tiers table (1=free, 2=silver, 3=gold, 4=unlimited).

## Project Structure

```
botmenfess/
├── index.php             # Entry point (polling mode)
├── webhook.php           # Entry point (webhook mode)
├── config.php            # Configuration constants
├── database_schema.sql    # Database schema + tier seed data
├── .env.example          # Environment template
├── composer.json
├── src/
│   ├── Bot.php           # Original bot class
│   ├── Database.php      # PDO wrapper
│   ├── Models/
│   │   ├── Tier.php        # Tier model
│   │   ├── DailyUsage.php  # Daily usage tracking
│   │   └── UserTier.php    # User-tier relationship
│   └── Services/
│       └── MonetizationService.php  # Core monetization logic
└── logs/
    └── bot.log
```

## Tech Stack

- PHP 7.4+
- telegram-bot/api - Telegram Bot API
- Doctrine DBAL - Database abstraction
- Monolog - Logging
- vlucas/phpdotenv - Environment variables
- MySQL (InnoDB) - Data storage

## Anonymity Guarantee

User personal data (stored in users table) is **NEVER** linked to submissions.
The submissions table only contains message text and metadata, ensuring complete
anonymity of all menfess posts regardless of the sender's tier.