# 📚 Dokumentasi Lengkap Bot Menfess

> Telegram-based anonymous confession (menfess) bot dengan tier-based daily submission limits, komentar diskusi, leaderboard, dan badge system.

---

## 📋 Daftar Isi

1. [Pendahuluan](#1-pendahuluan)
2. [Arsitektur Sistem](#2-arsitektur-sistem)
3. [Fitur Lengkap](#3-fitur-lengkap)
4. [Database Schema](#4-database-schema)
5. [Instalasi & Setup](#5-instalasi--setup)
6. [Configuration Reference](#6-configuration-reference)
7. [Code Structure](#7-code-structure)
8. [Deployment](#8-deployment)
9. [Troubleshooting](#9-troubleshooting)
10. [Roadmap & Feature Suggestions](#10-roadmap--feature-suggestions)

---

## 1. Pendahuluan

### Apa itu Bot Menfess?

Bot Menfess adalah bot Telegram yang memungkinkan pengguna untuk mengirim pesan anonim (menfess/confession) ke channel Telegram tertentu. Bot ini dirancang untuk keperluan komunitas atau grup yang ingin memiliki platform curhat anonim dengan sistem moderasi dan monetisasi.

### Tujuan & Fungsi Utama

- **Anonimitas Total**: Pengirim menfess tidak dapat diidentifikasi dari pesan yang diposting
- **Sistem Tier**: Monetisasi melalui limit harian berdasarkan tier pengguna
- **Diskusi**: Komentar anonim pada setiap postingan melalui discussion group
- **Gamifikasi**: Leaderboard dan badge system untuk engagement pengguna

### Fitur-Fitur Utama

| Fitur | Deskripsi |
|-------|-----------|
| 📝 Anonymous Submission | Kirim pesan anonim ke channel |
| 💰 Tier System | 4 tier dengan limit berbeda (Free, Silver, Gold, Unlimited) |
| 💬 Comment System | Komentar via reply di discussion group |
| 🏆 Leaderboard | Peringkat user berdasarkan aktivitas komentar |
| 🎖️ Badge System | 10+ badge yang bisa didapatkan |
| 🛡️ Shadow Ban | Moderasi tanpa user tahu |
| 📊 Stats & Analytics | Statistik bot real-time |
| 🌐 Web App | Interface web untuk leaderboard & profile |

---

## 2. Arsitektur Sistem

### 2.1 Diagram Arsitektur (High-Level)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         Telegram Platform                               │
│  ┌──────────────┐    ┌──────────────────┐    ┌─────────────────────┐   │
│  │   Users      │    │  Discussion Group │    │  Target Channel     │   │
│  │ (Private Chat)│    │  (Comments)       │    │  (Published Posts)  │   │
│  └──────┬───────┘    └────────┬─────────┘    └──────────┬──────────┘   │
│         │                     │                         │               │
│         └─────────────────────┼─────────────────────────┘               │
│                               │                                         │
│                    ┌──────────▼───────────┐                             │
│                    │   Telegram Bot API   │                             │
│                    │   (Webhook/Polling)  │                             │
│                    └──────────┬───────────┘                             │
└───────────────────────────────┼─────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         Server (PHP)                                    │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                    Entry Points                                  │   │
│  │  ┌──────────────┐              ┌──────────────────────┐         │   │
│  │  │  index.php   │              │    webhook.php       │         │   │
│  │  │ (Polling)    │              │   (Production)       │         │   │
│  │  └──────┬───────┘              └──────────┬───────────┘         │   │
│  │         │                                 │                      │   │
│  │         └─────────────────┬───────────────┘                      │   │
│  │                           │                                      │   │
│  │                  ┌────────▼────────┐                             │   │
│  │                  │   config.php    │ ← Environment (.env)        │   │
│  │                  └────────┬────────┘                             │   │
│  │                           │                                      │   │
│  │                  ┌────────▼────────┐                             │   │
│  │                  │  helpers.php    │ ← 926 baris fungsi reusable │   │
│  │                  └────────┬────────┘                             │   │
│  │                           │                                      │   │
│  │  ┌────────────────────────┼────────────────────────┐             │   │
│  │  │                        │                        │             │   │
│  │  ▼                        ▼                        ▼             │   │
│  │  ┌────────────┐   ┌──────────────┐   ┌─────────────────────┐    │   │
│  │  │ src/Bot.php│   │ src/Models/  │   │  src/Services/      │    │   │
│  │  │ (Main Bot) │   │ - Tier.php   │   │ - MonetizationSvc   │    │   │
│  │  │            │   │ - DailyUsage │   │                     │    │   │
│  │  │            │   │ - UserTier   │   │                     │    │   │
│  │  └────────────┘   └──────────────┘   └─────────────────────┘    │   │
│  │                                                                   │   │
│  │  ┌────────────────────────────────────────────────────────┐      │   │
│  │  │              Web Application (public/webapp/)          │      │   │
│  │  │  ┌───────────┐ ┌────────────┐ ┌──────────────────┐    │      │   │
│  │  │  │ index.html│ │leaderboard │ │ profile.html     │    │      │   │
│  │  │  │           │ │.html       │ │ admin.html       │    │      │   │
│  │  │  └───────────┘ └────────────┘ └──────────────────┘    │      │   │
│  │  │  ┌────────────────────────────────────────────────┐   │      │   │
│  │  │  │              API Endpoints (api/)              │   │      │   │
│  │  │  │ - leaderboard.php  - comment.php               │   │      │   │
│  │  │  │ - profile.php      - admin.php                 │   │      │   │
│  │  │  │ - submit.php       - get-draft.php             │   │      │   │
│  │  │  │ - publish-draft.php                            │   │      │   │
│  │  │  └────────────────────────────────────────────────┘   │      │   │
│  │  └────────────────────────────────────────────────────────┘      │   │
│  └─────────────────────────────────────────────────────────────────┘   │
└───────────────────────────────────┬───────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                        MySQL Database                                   │
│  ┌────────────┐  ┌──────────────┐  ┌───────────────┐  ┌────────────┐  │
│  │ users      │  │ submissions  │  │ channel_posts │  │ tiers      │  │
│  └────────────┘  └──────────────┘  └───────────────┘  └────────────┘  │
│  ┌────────────┐  ┌──────────────┐  ┌───────────────┐  ┌────────────┐  │
│  │ user_tiers │  │ daily_usage  │  │ comments      │  │ user_badges│  │
│  └────────────┘  └──────────────┘  └───────────────┘  └────────────┘  │
│  ┌──────────────────┐  ┌──────────────────────┐                       │
│  │ leaderboard_cache│  │ comment_history      │                       │
│  └──────────────────┘  └──────────────────────┘                       │
└─────────────────────────────────────────────────────────────────────────┘
```

### 2.2 Komponen Utama & Perannya

| Komponen | File | Deskripsi |
|----------|------|-----------|
| **Entry Point (Polling)** | `index.php` | Looping terus-menerus memanggil `getUpdates()`, cocok untuk development |
| **Entry Point (Webhook)** | `webhook.php` | Menerima POST request dari Telegram, efisien untuk production |
| **Configuration** | `config.php` | Memuat `.env`, mendefinisikan constants |
| **Shared Functions** | `helpers.php` | ~926 baris fungsi reusable untuk semua operasi bot |
| **Bot Class** | `src/Bot.php` | Class utama bot (legacy, menggunakan polling) |
| **Database Model** | `src/Database.php` | PDO wrapper |
| **Models** | `src/Models/` | Model class untuk Tier, DailyUsage, UserTier |
| **Services** | `src/Services/` | Service class untuk MonetizationService |

### 2.3 Alur Data

#### A. Submission (Pesan Anonim via Private Chat)

```
User → kirim pesan → Bot (Private Chat)
                     ↓
           Cek apakah command? (/start, /help, dll)
                     ↓
           Tidak → Cek limit tier
                     ↓
           ┌─────────────────────────────────┐
           │ menfessCheckLimit()             │
           │   - Ambil user tier             │
           │   - Cek daily_usage today       │
           │   - Compare dengan daily_limit  │
           └─────────────────────────────────┘
                     ↓
           Dalam limit?
            ├── Ya → Record usage → Insert submission → Acknowledge user
            └── Tidak → Reject + pesan upgrade tier
```

#### B. Komentar (via Discussion Group)

```
User reply di Discussion Group → Bot (via webhook)
                        ↓
           Pastikan dari DISCUSSION_GROUP_ID
                        ↓
           Find submission_id (via reply_to_message_id)
                        ↓
           ┌─────────────────────────────────┐
           │ validateComment()               │
           │   - Min length check (10 char)  │
           │   - Rate limiting (12 sec)      │
           │   - Duplicate check (1 hour)    │
           └─────────────────────────────────┘
                        ↓
           Valid?
            ├── Ya → saveComment() → Award badges → Notify user
            └── Tidak → Reply dengan error message
```

#### C. Auto-Approval Queue (Polling Mode)

```
[index.php - polling loop, setiap 200ms]
                        ↓
           processAutoApprovalQueue()
                        ↓
           Cek POST_INTERVAL_MINUTES (default: 1 menit)
                        ↓
           Ambil oldest pending submission
                        ↓
           Update status → 'approved' (reviewed_by = 'auto-approval')
                        ↓
           Post ke Target Channel → Simpan ke channel_posts
```

### 2.4 Database Relationships

```
users (1) ──────────┬── (M) user_tiers ──── (1) tiers
                    │
                    ├── (M) daily_usage
                    │
                    ├── (M) comments ────── (M:1) submissions
                    │       │
                    │       └── (M) comment_history
                    │
                    └── (M) user_badges

submissions (1) ──── (M) channel_posts

leaderboard_cache (self-contained, regenerated periodically)
```

### 2.5 Anonimitas - Jaminan Privasi

```
┌───────────────────────────────────────────────────────────┐
│  ANONYMITY GUARANTEE                                      │
│                                                           │
│  users table ──── TIDAK PERNAH di-link ke submissions     │
│                                                           │
│  submissions table hanya berisi:                          │
│    - message_text                                         │
│    - submitted_at                                         │
│    - status                                               │
│    - anonymous_id (bukan telegram_id!)                    │
│    - moderator_notes                                      │
│    - reviewed_at, reviewed_by                             │
│                                                           │
│  users table hanya untuk:                                 │
│    - Tier management                                      │
│    - Comment tracking                                     │
│    - Badge system                                         │
│    - Leaderboard                                          │
│    - Analytics                                            │
└───────────────────────────────────────────────────────────┘
```

---

## 3. Fitur Lengkap

### 3.1 Fitur User

#### Submission System

Pengguna dapat mengirim pesan anonim ke bot melalui private chat. Bot akan:
1. Menerima pesan teks
2. Memeriksa limit tier pengguna
3. Menyimpan submission ke database
4. Memberikan acknowledgment

**Cara Penggunaan:**
1. Buka bot di Telegram
2. Ketik `/start` untuk memulai
3. Kirim pesan apa saja untuk membuat menfess
4. Tunggu konfirmasi dari bot

#### Command Reference

| Command | Deskripsi | Detail |
|---------|-----------|--------|
| `/start` | Mulai bot & lihat info | Menampilkan pesan welcome & panduan |
| `/help` | Panduan penggunaan | Menampilkan daftar command tersedia |
| `/status` | Cek sisa limit | Menampilkan tier, limit, terpakai, sisa |
| `/tiers` | Lihat daftar tier | Menampilkan semua tier & harga |
| `/leaderboard` | Buka leaderboard | Link ke web leaderboard |

#### /status Output Example
```
Status:
- Tier: gold
- Limit: 30 menfess/hari
- Terpakai: 12
- Sisa: 18
```

#### /tiers Output Example
```
Tier Tersedia:

- free: 3 menfess/hari - Rp 0
- silver: 10 menfess/hari - Rp 15.000
- gold: 30 menfess/hari - Rp 30.000
- unlimited: Unlimited - Rp 50.000

Hubungi admin untuk upgrade tier.
```

### 3.2 Sistem Tier & Monetisasi

#### Tier Definitions

| Tier ID | Name | Daily Limit | Harga (IDR) | Description |
|---------|------|-------------|-------------|-------------|
| 1 | free | 3 | 0 | Free tier - 3 submissions per day |
| 2 | silver | 10 | 15,000 | Silver tier - 10 submissions per day |
| 3 | gold | 30 | 30,000 | Gold tier - 30 submissions per day |
| 4 | unlimited | -1 | 50,000 | Unlimited tier - No daily limit |

> **Note:** `daily_limit = -1` berarti unlimited (tidak ada batas)

#### Cara Kerja Limit Harian

1. User mengirim menfess
2. Bot memanggil `menfessCheckLimit()`:
   - Query `user_tiers` untuk mendapatkan tier aktif user
   - Query `tiers` untuk mendapatkan `daily_limit`
   - Query `daily_usage` untuk count hari ini
3. Jika `count >= daily_limit` → reject
4. Jika `count < daily_limit` → allow & increment counter

#### Cara Assign Tier ke User (via Database)

```sql
-- Assign tier ke user (user_id dari tabel users)
INSERT INTO user_tiers (user_id, tier_id, expires_at, is_active)
VALUES (1, 2, NOW() + INTERVAL 1 MONTH, TRUE);

-- Cek tier user saat ini
SELECT t.name, t.daily_limit, ut.expires_at
FROM user_tiers ut
INNER JOIN tiers t ON ut.tier_id = t.id
INNER JOIN users u ON ut.user_id = u.id
WHERE u.telegram_id = 123456789 AND ut.is_active = 1;
```

#### Fungsi Tier di helpers.php

```php
// Get user tier info
menfessGetUserTier($pdo, $uid)
// Returns: ['name' => 'gold', 'daily_limit' => 30]

// Get today usage count
menfessGetTodayUsage($pdo, $uid)
// Returns: 12 (integer)

// Check if user is within limit
menfessCheckLimit($pdo, $api, $cid, $uid)
// Returns: true|false (dan sendMessage jika reject)

// Record submission usage
menfessRecordUsage($pdo, $uid)
// Returns: void (insert/increment daily_usage)
```

### 3.3 Sistem Komentar

#### Cara Kerja

1. Bot harus ditambahkan sebagai **admin** di Discussion Group
2. Setiap postingan di channel akan di-forward ke Discussion Group sebagai thread
3. User bisa **reply** ke postingan di Discussion Group untuk berkomentar
4. Bot menangkap reply dan menyimpan sebagai komentar

#### Validasi Komentar

| Validasi | Configuration | Behavior |
|----------|---------------|----------|
| Minimum Length | `MIN_COMMENT_LENGTH = 10` | Komentar < 10 karakter ditolak |
| Rate Limiting | `COMMENT_RATE_LIMIT_SECONDS = 12` | Minimal 12 detik antar komentar |
| Duplicate Check | `COMMENT_DUPLICATE_CHECK_HOURS = 1` | Komentar sama dalam 1 jam ditolak |

#### Fungsi Komentar di helpers.php

```php
// Validate comment before saving
validateComment($pdo, $telegramId, $text)
// Returns: array of errors (empty = valid)

// Save comment to database
saveComment($pdo, $telegramId, $submissionId, $text, $telegramMessageId, $replyToMessageId)
// Returns: comment_id or null (if shadow banned)

// Find submission_id from reply_to_message_id
findSubmissionFromReply($pdo, $replyToMessageId)
// Returns: submission_id or null

// Handle comment from discussion group
handleComment($api, $pdo, $message)
// Full handler - called from webhook.php
```

### 3.4 Badge System

#### Daftar Badge

| Badge Key | Icon | Name | Description | Criteria |
|-----------|------|------|-------------|----------|
| `newcomer` | 🌱 | Newcomer | Komentar pertama | Kirim komentar pertama |
| `active_commenter` | 💬 | Active Commenter | 100+ komentar | Total komentar >= 100 |
| `early_bird` | 🐦 | Early Bird | 50%+ komentar jam 06-09 | >= 10 komentar, 50%+ di jam 6-9 pagi |
| `night_owl` | 🦉 | Night Owl | 50%+ komentar jam 23-02 | >= 10 komentar, 50%+ di jam 23-2 malam |
| `streak_master` | 🔥 | Streak Master | 7+ hari berturut-turut | Comment streak >= 7 hari |
| `conversation_starter` | 💭 | Conversation Starter | Submission dengan 20+ komentar | Pernah komentar di thread dengan 20+ komentar |
| `weekly_champion` | 🏅 | Weekly Champion | Rank #1 mingguan | Position #1 di weekly leaderboard |
| `monthly_legend` | 🌟 | Monthly Legend | Top 3 bulanan | Position 1-3 di monthly leaderboard |
| `alltime_king` | 👑 | All-Time King | Rank #1 all-time | Position #1 di alltime leaderboard |
| `rising_star` | ⚡ | Rising Star | Velocity tertinggi minggu ini | TBA |

#### Badge Configuration (config.php)

```php
define('BADGES', [
    'weekly_champion' => ['name' => 'Weekly Champion', 'icon' => '🏅', 'description' => 'Rank #1 mingguan'],
    'monthly_legend' => ['name' => 'Monthly Legend', 'icon' => '🌟', 'description' => 'Top 3 bulanan'],
    'alltime_king' => ['name' => 'All-Time King', 'icon' => '👑', 'description' => 'Rank #1 all-time'],
    'rising_star' => ['name' => 'Rising Star', 'icon' => '⚡', 'description' => 'Velocity tertinggi minggu ini'],
    'active_commenter' => ['name' => 'Active Commenter', 'icon' => '💬', 'description' => '100+ komentar'],
    'newcomer' => ['name' => 'Newcomer', 'icon' => '🌱', 'description' => 'Komentar pertama'],
    'early_bird' => ['name' => 'Early Bird', 'icon' => '🐦', 'description' => '50%+ komentar jam 06-09'],
    'night_owl' => ['name' => 'Night Owl', 'icon' => '🦉', 'description' => '50%+ komentar jam 23-02'],
    'streak_master' => ['name' => 'Streak Master', 'icon' => '🔥', 'description' => '7+ hari berturut-turut'],
    'conversation_starter' => ['name' => 'Conversation Starter', 'icon' => '💭', 'description' => 'Submission dengan 20+ komentar'],
]);
```

#### Fungsi Badge di helpers.php

```php
// Check and award badges for a user
checkAndAwardBadges($pdo, $userId, $telegramId)
// Called automatically after saveComment()

// Check which badges a user is eligible for
checkBadgeEligibility($pdo, $userId)
// Returns: array of badge_keys

// Award a badge to a user
awardBadge($pdo, $userId, $badgeKey)
// Returns: true|false

// Calculate consecutive day streak
calculateStreak($pdo, $userId)
// Returns: integer (streak days)
```

#### Badge Notification Flow

```
User comment → saveComment() → checkAndAwardBadges()
                                      ↓
                              checkBadgeEligibility()
                                      ↓
                              New badge found?
                               ├── Yes → sendBadgeNotificationBeforeAward()
                               │         → awardBadge()
                               └── No → done
```

### 3.5 Leaderboard System

#### Timeframes

| Timeframe | Description | Date Filter |
|-----------|-------------|-------------|
| `alltime` | Semua waktu | Tidak ada filter |
| `weekly` | 7 hari terakhir | `created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)` |
| `monthly` | 30 hari terakhir | `created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)` |

#### Metrics

| Metric | Description | Calculation |
|--------|-------------|-------------|
| `rank` | Peringkat user | ORDER BY comment_count DESC |
| `comment_count` | Total komentar | COUNT(comments.id) |
| `comment_velocity` | Kecepatan komentar | count / days * multiplier |
| `most_active_hour` | Jam paling aktif | GROUP BY HOUR(created_at) |

#### Cache System

Leaderboard di-cache di tabel `leaderboard_cache` untuk performa:
- **Cache Duration**: 24 jam (`LEADERBOARD_CACHE_HOURS`)
- **Default Limit**: 50 user (`LEADERBOARD_DEFAULT_LIMIT`)
- **Auto Rebuild**: Via cron job (`cron/rebuild_cache.php`)

```php
// Cache configuration
define('LEADERBOARD_CACHE_HOURS', 24);
define('LEADERBOARD_DEFAULT_LIMIT', 50);
```

#### Fungsi Leaderboard di helpers.php

```php
// Get leaderboard data (from cache or regenerate)
getLeaderboard($pdo, $timeframe = 'alltime', $limit = 50)
// Returns: ['timeframe', 'leaderboard', 'stats', 'generated_at']

// Get cached leaderboard
getLeaderboardCache($pdo, $timeframe)
// Returns: cache row or null

// Rebuild leaderboard cache
rebuildLeaderboardCache($pdo, $timeframe, $limit)
// Returns: leaderboard data

// Get personal rank
getPersonalRank($pdo, $telegramId, $timeframe = 'alltime')
// Returns: ['rank', 'comment_count', 'comment_velocity', 'most_active_hour'] or null

// Get overall statistics
getOverallStats($pdo, $timeframe = 'alltime')
// Returns: ['total_comments', 'total_commenters', 'total_submissions', 'total_users']

// Get user profile data
getUserProfile($pdo, $telegramId)
// Returns: comprehensive user data or null

// Get admin statistics
getAdminStats($pdo)
// Returns: dashboard stats

// Rebuild all leaderboard caches
rebuildAllCaches($pdo)
// Returns: ['alltime' => 'success', 'weekly' => 'success', 'monthly' => 'success']
```

### 3.6 Shadow Ban System

#### Apa itu Shadow Ban?

Shadow ban adalah sistem moderasi dimana user yang di-ban **tidak tahu** bahwa mereka di-ban. Komentar mereka tidak akan tersimpan, tetapi mereka tidak mendapatkan notifikasi apapun (silently ignored).

#### Fungsi Shadow Ban

```php
// Check if user is shadow banned
isUserShadowBanned($pdo, $telegramId)
// Returns: true|false

// Shadow ban a user (from admin)
shadowBanUser($pdo, $telegramId, $adminUsername = 'system')
// Returns: void

// Unshadow ban a user
unshadowBanUser($pdo, $telegramId)
// Returns: void
```

#### Shadow Ban Effects

| Action | Normal User | Shadow Banned User |
|--------|-------------|-------------------|
| Submit menfess | ✅ Tersimpan | ✅ Masih tersimpan (menfess tidak linked ke user) |
| Comment | ✅ Tersimpan | ❌ Silently ignored |
| Get badges | ✅ Bisa dapat | ❌ Tidak dapat badge |
| Leaderboard | ✅ Muncul | ❌ Tidak muncul (excluded by `is_shadow_banned = 0`) |

### 3.7 User Management

#### Fungsi User di helpers.php

```php
// Get or create user by telegram_id
getOrCreateUser($pdo, $telegramId, $username, $firstName, $lastName)
// Returns: user_id (integer)
// Behavior: INSERT if new, UPDATE if exists
```

#### Users Table Schema

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_id BIGINT NOT NULL UNIQUE,
    username VARCHAR(255) NULL,
    first_name VARCHAR(255) NULL,
    last_name VARCHAR(255) NULL,
    language_code VARCHAR(10) NULL,
    is_bot BOOLEAN DEFAULT FALSE,
    first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    submission_count INT DEFAULT 0,
    is_blocked BOOLEAN DEFAULT FALSE,
    blocked_at TIMESTAMP NULL,
    blocked_by VARCHAR(255) NULL,
    is_shadow_banned BOOLEAN DEFAULT FALSE,
    shadow_banned_at TIMESTAMP NULL,
    shadow_banned_by VARCHAR(255) NULL,
    last_badge_notification_sent VARCHAR(50) NULL
);
```

### 3.8 Web Application

#### Struktur Web App

```
public/webapp/
├── index.html           → Landing page / main app
├── leaderboard.html     → Leaderboard page
├── profile.html         → User profile page
├── admin.html           → Admin dashboard
├── submit.php           → Submission handler API
├── stats.php            → Stats endpoint
├── css/
│   ├── main.css         → Main stylesheet
│   └── animations.css   → Animations
├── js/
│   ├── app.js           → Core app logic
│   ├── leaderboard.js   → Leaderboard UI logic
│   ├── profile.js       → Profile UI logic
│   └── admin.js         → Admin UI logic
└── api/
    ├── leaderboard.php  → GET leaderboard data (JSON)
    ├── profile.php      → GET user profile data (JSON)
    ├── comment.php      → POST/GET comments
    ├── submit.php       → POST new submission
    ├── admin.php        → Admin operations
    ├── get-draft.php    → GET pending/draft posts
    └── publish-draft.php → POST publish draft
```

#### Web App URLs

| Page | URL | Description |
|------|-----|-------------|
| Home | `{WEBAPP_URL}/index.html` | Landing page |
| Leaderboard | `{WEBAPP_URL}/leaderboard.html` | Leaderboard dengan filter |
| Profile | `{WEBAPP_URL}/profile.html` | User profile dengan statistik |
| Admin | `{WEBAPP_URL}/admin.html` | Admin dashboard |

#### Configuration

```php
// Web App URL (derived from WEBHOOK_URL if not set)
define('WEBAPP_URL', $webappBaseUrl);

// Config.php
$webappBaseUrl = $_ENV['WEBAPP_URL'] ?? (rtrim($_ENV['WEBHOOK_URL'] ?? '', '/') . '/public/webapp');
```

---

## 4. Database Schema

### 4.1 Tabel Lengkap

#### `users` - User Information
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_id BIGINT NOT NULL UNIQUE,
    username VARCHAR(255) NULL,
    first_name VARCHAR(255) NULL,
    last_name VARCHAR(255) NULL,
    language_code VARCHAR(10) NULL,
    is_bot BOOLEAN DEFAULT FALSE,
    first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    submission_count INT DEFAULT 0,
    is_blocked BOOLEAN DEFAULT FALSE,
    blocked_at TIMESTAMP NULL,
    blocked_by VARCHAR(255) NULL,
    is_shadow_banned BOOLEAN DEFAULT FALSE,
    shadow_banned_at TIMESTAMP NULL,
    shadow_banned_by VARCHAR(255) NULL,
    last_badge_notification_sent VARCHAR(50) NULL,
    UNIQUE KEY idx_telegram_id (telegram_id)
) ENGINE=InnoDB;
```

#### `submissions` - Menfess Entries
```sql
CREATE TABLE submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_text TEXT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    moderator_notes TEXT NULL,
    reviewed_at TIMESTAMP NULL,
    reviewed_by VARCHAR(255) NULL,
    anonymous_id VARCHAR(20) NULL
) ENGINE=InnoDB;

-- Indexes for performance
CREATE INDEX idx_submissions_status ON submissions(status);
CREATE INDEX idx_submissions_submitted_at ON submissions(submitted_at);
```

#### `tiers` - Tier Definitions
```sql
CREATE TABLE tiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    daily_limit INT NOT NULL DEFAULT 3, -- -1 means unlimited
    description TEXT NULL,
    price DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed default tiers
INSERT INTO tiers (name, daily_limit, description, price) VALUES
('free', 3, 'Free tier - 3 submissions per day', 0.00),
('silver', 10, 'Silver tier - 10 submissions per day', 15000.00),
('gold', 30, 'Gold tier - 30 submissions per day', 30000.00),
('unlimited', -1, 'Unlimited tier - No daily limit', 50000.00);
```

#### `user_tiers` - User to Tier Mapping
```sql
CREATE TABLE user_tiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tier_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tier_id) REFERENCES tiers(id) ON DELETE RESTRICT,
    INDEX idx_user_active (user_id, is_active)
) ENGINE=InnoDB;
```

#### `daily_usage` - Daily Submission Tracking
```sql
CREATE TABLE daily_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    usage_date DATE NOT NULL,
    count INT DEFAULT 0,
    UNIQUE KEY idx_user_date (user_id, usage_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

#### `channel_posts` - Posted Submissions Tracking
```sql
CREATE TABLE channel_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    telegram_message_id BIGINT NOT NULL,
    posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

#### `comments` - Discussion Group Comments
```sql
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    user_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    telegram_message_id BIGINT NULL,
    reply_to_message_id BIGINT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_submission_id (submission_id),
    INDEX idx_comments_user_created (user_id, created_at),
    INDEX idx_comments_created (created_at),
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

#### `user_badges` - Earned Badges
```sql
CREATE TABLE user_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_key VARCHAR(50) NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    metadata JSON NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY idx_user_badge (user_id, badge_key),
    INDEX idx_user_badges_user_earned (user_id, earned_at)
) ENGINE=InnoDB;
```

#### `comment_history` - Comment Audit Trail
```sql
CREATE TABLE comment_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    comment_id INT NOT NULL,
    action VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;
```

#### `leaderboard_cache` - Pre-computed Leaderboard
```sql
CREATE TABLE leaderboard_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timeframe VARCHAR(20) NOT NULL,
    cache_data JSON NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_timeframe (timeframe)
) ENGINE=InnoDB;
```

### 4.2 Relationship Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        Database ER Diagram                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────┐         ┌──────────────┐         ┌──────────┐    │
│  │  users   │←─1:N───│ user_tiers   │───N:1→   │  tiers   │    │
│  │          │         └──────────────┘         │          │    │
│  │          │←─1:N───┌──────────────┐          │          │    │
│  │          │         │ daily_usage │           │          │    │
│  │          │───N:1→ └──────────────┘          │          │    │
│  │          │         ┌──────────────┐          │          │    │
│  │          │←─1:N───│   comments   │───N:1→   │          │    │
│  │          │         │              │          │          │    │
│  │          │←─1:N───└──────┬───────┘          │          │    │
│  │          │         ┌─────▼──────┐           │          │    │
│  │          │←─1:N───│  comments  │            │          │    │
│  │          │         └────────────┘           │          │    │
│  └────┬─────┘                                  └──────────┘    │
│       │                                                         │
│       │←─1:N───┌──────────────┐                                 │
│       │         │ user_badges │                                 │
│       │         └──────────────┘                                │
│       │                                                         │
│       │         ┌──────────────┐    ┌──────────────────┐       │
│       └────────→│ submissions  │←───│ channel_posts    │       │
│                 │              │ 1:N│                  │       │
│                 └──────────────┘    └──────────────────┘       │
│                                                                 │
│                 ┌──────────────────────────┐                    │
│                 │  leaderboard_cache       │                    │
│                 │  (self-contained)        │                    │
│                 └──────────────────────────┘                    │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 4.3 Indexes & Performance

| Table | Index | Purpose |
|-------|-------|---------|
| `users` | `idx_telegram_id` | Fast lookup by Telegram ID |
| `submissions` | `idx_submissions_status` | Filter by status (pending/approved/rejected) |
| `submissions` | `idx_submissions_submitted_at` | Chronological queries |
| `comments` | `idx_user_id` | User comment history |
| `comments` | `idx_created_at` | Time-based queries |
| `comments` | `idx_submission_id` | Comments per submission |
| `comments` | `idx_comments_user_created` | User comments over time |
| `comments` | `idx_comments_created` | Global time-based queries |
| `user_tiers` | `idx_user_active` | Active tier lookup |
| `daily_usage` | `idx_user_date` | Daily count lookup (UNIQUE) |
| `user_badges` | `idx_user_badge` | Badge lookup by user |

---

## 5. Instalasi & Setup

### 5.1 Prerequisites

| Requirement | Version | Notes |
|-------------|---------|-------|
| PHP | 7.4+ | PDO MySQL extension required |
| MySQL/MariaDB | 5.7+ / 10.3+ | InnoDB engine required |
| Composer | Latest | For PHP dependencies |
| Telegram Bot | - | Created via @BotFather |
| Telegram Channel | - | Target for menfess posts |
| Telegram Discussion Group | - | Linked to channel for comments |

### 5.2 Step-by-step Installation

#### Step 1: Clone Repository

```bash
git clone https://github.com/mieburungdara/botmenfess.git
cd botmenfess
```

#### Step 2: Install Dependencies

```bash
composer install
```

Dependencies yang diinstall:
- `telegram-bot/api` (^2.3) - Telegram Bot API SDK
- `monolog/monolog` (^2.0) - Logging library
- `vlucas/phpdotenv` (^5.0) - Environment variable management

#### Step 3: Setup Database

```bash
# Import database schema
mysql -u root -p < database_schema.sql
```

Atau secara manual:
```sql
CREATE DATABASE menfess_bot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Kemudian jalankan semua CREATE TABLE dari database_schema.sql
```

#### Step 4: Configure Environment

```bash
# Copy .env.example ke .env
cp .env.example .env

# Edit .env dengan nilai yang sesuai
nano .env
```

#### Step 5: Configure Telegram Bot

1. **Buat Bot via @BotFather:**
   - Buka Telegram → @BotFather
   - Ketik `/newbot`
   - Ikuti instruksi untuk nama dan username bot
   - Simpan API token yang diberikan

2. **Setup Target Channel:**
   - Buat channel baru atau gunakan yang sudah ada
   - Tambahkan bot sebagai **administrator** channel
   - Dapatkan Channel ID (gunakan @userinfobot atau forward pesan)

3. **Setup Discussion Group:**
   - Buat grup dan link ke channel
   - Tambahkan bot sebagai **administrator** grup
   - Dapatkan Group ID

4. **Dapatkan IDs:**
   - Forward pesan dari channel/grup ke @userinfobot
   - Atau gunakan Telegram Bot API: `getUpdates`

#### Step 6: Test Bot

**Mode Polling (Development):**
```bash
php index.php
```

Bot akan mulai dan menerima pesan via private chat.

### 5.3 Environment Variables (.env)

```env
# Telegram Bot Configuration
TELEGRAM_BOT_TOKEN=your_bot_token_here
TARGET_CHANNEL_ID=your_channel_id_here
DISCUSSION_GROUP_ID=your_discussion_group_id_here

# Database Configuration
DB_HOST=localhost
DB_NAME=menfess_bot
DB_USERNAME=root
DB_PASSWORD=

# Webhook Configuration
WEBHOOK_URL=https://yourdomain.com/botmenfess
WEBAPP_URL=https://yourdomain.com/botmenfess/public/webapp
USE_WEBHOOK=false

# Admin Configuration (comma-separated Telegram user IDs)
ADMIN_TELEGRAM_IDS=

# Monetization Settings
POST_INTERVAL_MINUTES=1
```

### 5.4 Polling vs Webhook Mode

| Feature | Polling Mode | Webhook Mode |
|---------|-------------|--------------|
| File Entry | `index.php` | `webhook.php` |
| Command | `php index.php` | Auto-triggered |
| Resource Usage | Higher (constant loop) | Lower (event-driven) |
| Latency | ~200ms per loop | Real-time |
| HTTPS Required | No | **Yes** |
| Server Needed | Any PHP server | Web server with SSL |
| Best For | Development | **Production** |

#### Running in Background (Polling)

```bash
# Using nohup
nohup php index.php > bot.log 2>&1 &

# Using screen
screen -S menfess
php index.php
# Ctrl+A, D to detach

# Using systemctl (recommended for production)
sudo nano /etc/systemd/system/menfess-bot.service
```

```ini
# /etc/systemd/system/menfess-bot.service
[Unit]
Description=Menfess Telegram Bot
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/botmenfess
ExecStart=/usr/bin/php index.php
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable menfess-bot
sudo systemctl start menfess-bot
sudo systemctl status menfess-bot
```

---

## 6. Configuration Reference

### 6.1 Environment Variables

| Variable | Type | Default | Description |
|----------|------|---------|-------------|
| `TELEGRAM_BOT_TOKEN` | string | - | Token dari @BotFather |
| `TARGET_CHANNEL_ID` | integer/string | - | Channel ID untuk posting |
| `DISCUSSION_GROUP_ID` | integer/string | - | Group ID untuk komentar |
| `DB_HOST` | string | localhost | MySQL host |
| `DB_NAME` | string | menfess_bot | Database name |
| `DB_USERNAME` | string | root | Database username |
| `DB_PASSWORD` | string | - | Database password |
| `WEBHOOK_URL` | string | - | HTTPS URL untuk webhook |
| `WEBAPP_URL` | string | Derived from WEBHOOK_URL | Base URL web app |
| `USE_WEBHOOK` | boolean | false | Enable webhook mode |
| `ADMIN_TELEGRAM_IDS` | comma-separated | - | Admin IDs (e.g., `123,456,789`) |
| `POST_INTERVAL_MINUTES` | integer | 1 | Interval antar posting (menit) |

### 6.2 Constants (config.php)

| Constant | Value | Description |
|----------|-------|-------------|
| `DB_HOST` | from env | MySQL host |
| `DB_NAME` | from env | Database name |
| `DB_USER` | from env | Database username |
| `DB_PASS` | from env | Database password |
| `TELEGRAM_BOT_TOKEN` | from env | Bot token |
| `TARGET_CHANNEL_ID` | from env | Channel ID |
| `DISCUSSION_GROUP_ID` | from env | Discussion group ID |
| `USE_WEBHOOK` | from env (bool) | Enable webhook |
| `WEBHOOK_URL` | from env | Webhook URL |
| `ADMIN_TELEGRAM_IDS` | from env (array) | Admin IDs array |
| `DEFAULT_TIER_LIMIT` | 3 | Default daily limit |
| `POST_INTERVAL_MINUTES` | from env | Posting interval |
| `LOG_FILE` | `logs/bot.log` | Log file path |
| `MIN_COMMENT_LENGTH` | 10 | Min karakter komentar |
| `COMMENT_RATE_LIMIT_SECONDS` | 12 | Rate limit antar komentar |
| `COMMENT_DUPLICATE_CHECK_HOURS` | 1 | Window duplikat |
| `LEADERBOARD_CACHE_HOURS` | 24 | Cache duration |
| `LEADERBOARD_DEFAULT_LIMIT` | 50 | Default leaderboard limit |
| `EARLY_BIRD_START` | 6 | Early bird start hour |
| `EARLY_BIRD_END` | 9 | Early bird end hour |
| `NIGHT_OWL_START` | 23 | Night owl start hour |
| `NIGHT_OWL_END` | 2 | Night owl end hour |
| `WEBAPP_URL` | from env | Web app base URL |

### 6.3 Badge Definitions (config.php)

```php
define('BADGES', [
    'weekly_champion' => ['name' => 'Weekly Champion', 'icon' => '🏅', 'description' => 'Rank #1 mingguan'],
    'monthly_legend' => ['name' => 'Monthly Legend', 'icon' => '🌟', 'description' => 'Top 3 bulanan'],
    'alltime_king' => ['name' => 'All-Time King', 'icon' => '👑', 'description' => 'Rank #1 all-time'],
    'rising_star' => ['name' => 'Rising Star', 'icon' => '⚡', 'description' => 'Velocity tertinggi minggu ini'],
    'active_commenter' => ['name' => 'Active Commenter', 'icon' => '💬', 'description' => '100+ komentar'],
    'newcomer' => ['name' => 'Newcomer', 'icon' => '🌱', 'description' => 'Komentar pertama'],
    'early_bird' => ['name' => 'Early Bird', 'icon' => '🐦', 'description' => '50%+ komentar jam 06-09'],
    'night_owl' => ['name' => 'Night Owl', 'icon' => '🦉', 'description' => '50%+ komentar jam 23-02'],
    'streak_master' => ['name' => 'Streak Master', 'icon' => '🔥', 'description' => '7+ hari berturut-turut'],
    'conversation_starter' => ['name' => 'Conversation Starter', 'icon' => '💭', 'description' => 'Submission dengan 20+ komentar'],
]);
```

---

## 7. Code Structure

### 7.1 Project Structure

```
botmenfess/
├── .env.example              # Template environment variables
├── .gitignore                # Git ignore rules
├── composer.json             # PHP dependencies
├── config.php                # Configuration constants
├── helpers.php               # Shared functions (926 lines)
├── index.php                 # Entry point (polling mode)
├── webhook.php               # Entry point (webhook mode)
├── database_schema.sql       # Database schema + seed data
├── FEATURE_SUGGESTIONS.md    # Feature roadmap (52 features)
├── README.md                 # Basic readme
├── DOCUMENTATION.md          # This file
├── run_kanban.bat            # Windows batch script
├── create_task.ps1           # PowerShell task creation
├── task_prompt.txt           # Task prompt template
├── wsl                       # WSL helper script
│
├── cron/
│   └── rebuild_cache.php     # Cron job for leaderboard cache
│
├── migrations/
│   └── 001_add_comments_cache_badges_history_tables.sql
│
├── logs/
│   └── bot.log               # Application logs (auto-created)
│
├── public/
│   └── webapp/
│       ├── index.html        # Landing page
│       ├── leaderboard.html  # Leaderboard page
│       ├── profile.html      # User profile page
│       ├── admin.html        # Admin dashboard
│       ├── submit.php        # Submission API
│       ├── stats.php         # Stats endpoint
│       ├── css/
│       │   ├── main.css      # Main stylesheet
│       │   └── animations.css # CSS animations
│       ├── js/
│       │   ├── app.js        # Core app logic
│       │   ├── leaderboard.js # Leaderboard UI
│       │   ├── profile.js    # Profile UI
│       │   └── admin.js      # Admin UI
│       └── api/
│           ├── admin.php     # Admin operations API
│           ├── comment.php   # Comment API
│           ├── get-draft.php # Get draft posts
│           ├── leaderboard.php # Leaderboard data API
│           ├── profile.php   # User profile API
│           ├── publish-draft.php # Publish draft
│           └── submit.php    # Submit menfess API
│
└── src/
    ├── Bot.php               # Main bot class (legacy polling)
    ├── Database.php          # PDO wrapper
    ├── Commands/             # Command handlers (placeholder)
    ├── Handlers/             # Event handlers (placeholder)
    ├── Models/
    │   ├── DailyUsage.php    # Daily usage model
    │   ├── Tier.php          # Tier model
    │   └── UserTier.php      # User-Tier relationship model
    ├── Services/
    │   └── MonetizationService.php  # Monetization logic
    └── Utils/                # Utilities (placeholder)
```

### 7.2 File-by-File Explanation

#### Entry Points

| File | Mode | Lines | Description |
|------|------|-------|-------------|
| `index.php` | Polling | 58 | Bot entry point dengan long polling loop. Handle SIGINT/SIGTERM. |
| `webhook.php` | Webhook | 101 | Webhook handler. Menerima POST dari Telegram. Handle messages & callback queries. |

#### Configuration

| File | Lines | Description |
|------|-------|-------------|
| `config.php` | 60 | Load `.env`, define constants (DB, Telegram, Badges, Cache settings). |
| `helpers.php` | 926 | **~90+ fungsi reusable**: tier, submission, comment, badge, leaderboard, user management, stats. |

#### Source Code

| File | Lines | Description |
|------|-------|-------------|
| `src/Bot.php` | 664 | Class `MenfessBot` - Implementasi lengkap bot dengan polling. |
| `src/Database.php` | - | PDO wrapper untuk database connection. |
| `src/Models/Tier.php` | 39 | Model untuk tabel `tiers`. |
| `src/Models/DailyUsage.php` | - | Model untuk tabel `daily_usage`. |
| `src/Models/UserTier.php` | - | Model untuk tabel `user_tiers`. |
| `src/Services/MonetizationService.php` | - | Service untuk logika monetisasi. |

### 7.3 helpers.php Functions (Categorized)

#### Tier & Submission Functions

| Function | Lines | Parameters | Returns | Description |
|----------|-------|------------|---------|-------------|
| `menfessGetUserTier()` | 12-16 | `$pdo, $uid` | `['name', 'daily_limit']` | Get user's active tier |
| `menfessGetTodayUsage()` | 19-24 | `$pdo, $uid` | `int` | Get today's submission count |
| `menfessCheckLimit()` | 27-39 | `$pdo, $api, $cid, $uid` | `bool` | Check if user within limit |
| `menfessRecordUsage()` | 42-45 | `$pdo, $uid` | `void` | Record submission (upsert) |
| `menfessHandleStatus()` | 47-57 | `$api, $cid, $uid, $pdo` | `void` | Handle /status command |
| `menfessHandleTiers()` | 59-70 | `$api, $cid, $pdo` | `void` | Handle /tiers command |
| `menfessHandleSubmit()` | 73-83 | `$api, $cid, $uid, $txt, $pdo` | `void` | Handle message submission |

#### User Management Functions

| Function | Lines | Parameters | Returns | Description |
|----------|-------|------------|---------|-------------|
| `getOrCreateUser()` | 90-107 | `$pdo, $telegramId, ...` | `user_id` | Create or update user |
| `isUserShadowBanned()` | 110-115 | `$pdo, $telegramId` | `bool` | Check shadow ban status |
| `shadowBanUser()` | 118-121 | `$pdo, $telegramId, $admin` | `void` | Ban user silently |
| `unshadowBanUser()` | 124-127 | `$pdo, $telegramId` | `void` | Remove shadow ban |

#### Comment Handling Functions

| Function | Lines | Parameters | Returns | Description |
|----------|-------|------------|---------|-------------|
| `validateComment()` | 134-161 | `$pdo, $telegramId, $text` | `array errors` | Validate before save |
| `saveComment()` | 164-188 | `$pdo, $telegramId, $submissionId, ...` | `comment_id/null` | Save comment to DB |
| `findSubmissionFromReply()` | 191-211 | `$pdo, $replyToMessageId` | `submission_id/null` | Find submission from reply |
| `handleComment()` | 214-281 | `$api, $pdo, $message` | `void` | Full comment handler |

#### Badge System Functions

| Function | Lines | Parameters | Returns | Description |
|----------|-------|------------|---------|-------------|
| `checkAndAwardBadges()` | 288-300 | `$pdo, $userId, $telegramId` | `void` | Main badge awarder |
| `sendBadgeNotificationBeforeAward()` | 303-325 | `$pdo, $telegramId, $badgeKey` | `void` | Notify before award |
| `checkBadgeEligibility()` | 328-429 | `$pdo, $userId` | `array badge_keys` | Check eligible badges |
| `awardBadge()` | 432-444 | `$pdo, $userId, $badgeKey` | `bool` | Award single badge |
| `sendBadgeNotification()` | 447-470 | `$pdo, $telegramId, $badgeKey` | `void` | Send badge notification |
| `calculateStreak()` | 473-504 | `$pdo, $userId` | `int` | Calculate day streak |

#### Leaderboard Functions

| Function | Lines | Parameters | Returns | Description |
|----------|-------|------------|---------|-------------|
| `getLeaderboard()` | 511-524 | `$pdo, $timeframe, $limit` | `array` | Get leaderboard data |
| `getLeaderboardCache()` | 527-531 | `$pdo, $timeframe` | `row/null` | Get cached data |
| `isCacheExpired()` | 534-539 | `$generatedAt` | `bool` | Check cache expiry |
| `rebuildLeaderboardCache()` | 542-551 | `$pdo, $timeframe, $limit` | `array` | Rebuild cache |
| `generateLeaderboardData()` | 554-651 | `$pdo, $timeframe, $limit` | `array` | Generate from DB |
| `getPreviousRanks()` | 554-658 | `$pdo, $timeframe` | `array` | Get previous ranks |
| `getUserBadges()` | 661-665 | `$pdo, $userId` | `array` | Get user badges |
| `getOverallStats()` | 668-701 | `$pdo, $timeframe` | `array` | Get overall stats |
| `getPersonalRank()` | 704-769 | `$pdo, $telegramId, $timeframe` | `array/null` | Get personal rank |
| `calculateDaysSinceFirstComment()` | 772-784 | `$pdo, $userId` | `int` | Days since first comment |
| `getUserProfile()` | 787-862 | `$pdo, $telegramId` | `array/null` | Get full user profile |
| `getAdminStats()` | 865-909 | `$pdo` | `array` | Get admin dashboard stats |
| `rebuildAllCaches()` | 912-926 | `$pdo` | `array` | Rebuild all timeframes |

### 7.4 Web App API Endpoints

| Endpoint | Method | Description | Parameters |
|----------|--------|-------------|------------|
| `api/leaderboard.php` | GET | Get leaderboard data | `?timeframe=alltime|weekly|monthly` |
| `api/profile.php` | GET | Get user profile | `?user_id=X` |
| `api/comment.php` | GET/POST | Manage comments | Various |
| `api/submit.php` | POST | Submit new menfess | Message text |
| `api/admin.php` | GET/POST | Admin operations | Various |
| `api/get-draft.php` | GET | Get pending drafts | - |
| `api/publish-draft.php` | POST | Publish a draft | Draft ID |

---

## 8. Deployment

### 8.1 Production Setup

#### Web Server Configuration (Nginx)

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    root /var/www/botmenfess;
    index webhook.php;

    # Webhook endpoint
    location = /webhook.php {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Web App
    location /public/webapp/ {
        try_files $uri $uri/ =404;
    }

    # API endpoints
    location ~ ^/public/webapp/api/ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to sensitive files
    location ~ /\.(env|git) {
        deny all;
    }
}
```

#### Set USE_WEBHOOK=true

```env
USE_WEBHOOK=true
WEBHOOK_URL=https://yourdomain.com/botmenfess
WEBAPP_URL=https://yourdomain.com/botmenfess/public/webapp
```

### 8.2 Cron Jobs

#### Leaderboard Cache Rebuild (Daily)

```bash
# Edit crontab
crontab -e

# Add daily cache rebuild at 2 AM
0 2 * * * cd /var/www/botmenfess && php cron/rebuild_cache.php >> /var/log/menfess-cache.log 2>&1
```

#### Cron Script (cron/rebuild_cache.php)

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "[" . date('Y-m-d H:i:s') . "] Rebuilding leaderboard caches...\n";

$results = rebuildAllCaches($pdo);

foreach ($results as $timeframe => $result) {
    echo "  {$timeframe}: {$result}\n";
}

echo "Done.\n";
```

### 8.3 Logging & Monitoring

#### Log Files

| Log | Location | Description |
|-----|----------|-------------|
| Bot Log | `logs/bot.log` | Application logs (info, error) |
| Webhook Log | `logs/bot.log` | Webhook event logs |
| System Log | Syslog | System-level issues |

#### Log Rotation

```bash
# /etc/logrotate.d/menfess-bot
/var/www/botmenfess/logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 0644 www-data www-data
}
```

### 8.4 Security Considerations

1. **HTTPS Required**: Webhook endpoint harus menggunakan SSL yang valid
2. **Protect `.env`**: Jangan expose `.env` ke public
3. **File Permissions**:
   ```bash
   chmod 755 /var/www/botmenfess
   chmod 644 /var/www/botmenfess/.env
   chmod 755 /var/www/botmenfess/logs
   chown -R www-data:www-data /var/www/botmenfess
   ```
4. **Database Security**: Gunakan user dengan permission minimal
5. **Admin IDs**: Hanya set ADMIN_TELEGRAM_IDS ke user yang terpercaya

---

## 9. Troubleshooting

### 9.1 Common Issues

| Issue | Possible Cause | Solution |
|-------|---------------|----------|
| Bot tidak merespon | Wrong token / offline | Cek `TELEGRAM_BOT_TOKEN`, pastikan bot running |
| Gagal post ke channel | Bot bukan admin | Tambahkan bot sebagai admin channel |
| Komentar tidak tersimpan | Wrong DISCUSSION_GROUP_ID | Pastikan Group ID benar (compare as string) |
| "Limit harian habis" | Tier limit reached | Upgrade tier atau tunggu besok |
| Webhook error 404 | Wrong URL / SSL issue | Cek WEBHOOK_URL, pastikan HTTPS valid |
| Database connection failed | Wrong credentials | Cek DB_HOST, DB_USER, DB_PASS |
| Cache expired | Cron tidak jalan | Setup cron job `rebuild_cache.php` |

### 9.2 Debug Mode

Enable verbose logging di `config.php`:

```php
// Change log level to DEBUG
$logger->pushHandler(new StreamHandler(LOG_FILE, Logger::DEBUG));
```

### 9.3 Performance Tips

1. **Index Tables**: Pastikan semua indexes sudah dibuat
2. **Cache Leaderboard**: Gunakan cron untuk rebuild cache
3. **Optimize MySQL**: Gunakan InnoDB, tune `innodb_buffer_pool_size`
4. **Use Webhook Mode**: Lebih efisien daripada polling
5. **Log Rotation**: Hindari file log yang terlalu besar

---

## 10. Roadmap & Feature Suggestions

Project ini memiliki roadmap pengembangan yang komprehensif, tercantum dalam `FEATURE_SUGGESTIONS.md` dengan **52+ rekomendasi fitur**.

### 10.1 Summary Fitur yang Disarankan

| Prioritas | Fitur | Estimasi |
|-----------|-------|----------|
| Tinggi | Reply/Balasan Menfess | 2-3 hari |
| Tinggi | Sistem Moderasi Otomatis (Auto-filter) | 1-2 hari |
| Tinggi | Command Admin Lengkap (/ban, /unban, dll) | 2 hari |
| Tinggi | Tag/Kategori Menfess | 1 hari |
| Menengah | Voting & Reaksi | 2 hari |
| Menengah | Anonymous Chat / PM | 3-4 hari |
| Menengah | Draft & Jadwal Posting | 2 hari |
| Advanced | Sistem Poin & Reputasi | 3 hari |
| Advanced | Integrasi Media (Foto/Video) | 3-4 hari |
| Advanced | Dark Mode & Custom Theme | 2 hari |

### 10.2 Fitur Teknis yang Kurang

1. Rate limiting untuk mencegah spam
2. Backup database otomatis setiap hari
3. Log aktivitas admin
4. Web dashboard untuk monitoring
5. API untuk integrasi dengan platform lain

### 10.3 Urutan Implementasi yang Disarankan

```
Tahap 1: Moderasi Otomatis → Command Admin → Tag/Kategori
Tahap 2: Reply Menfess → Voting & Reaksi
Tahap 3: Anonymous Chat → Draft & Schedule
Tahap 4+: Fitur Advanced lainnya
```

---

## 📎 Appendices

### A. Tech Stack

| Component | Technology | Version |
|-----------|-----------|---------|
| Language | PHP | 7.4+ |
| Database | MySQL / MariaDB | 5.7+ / 10.3+ |
| Telegram SDK | telegram-bot/api | ^2.3 |
| Logging | Monolog | ^2.0 |
| Environment | vlucas/phpdotenv | ^5.0 |
| Frontend | HTML/CSS/JS | - |

### B. Quick Command Reference

```
# User Commands
/start      - Mulai bot
/help       - Panduan
/status     - Cek sisa limit
/tiers      - Lihat tier
/leaderboard - Link ke leaderboard

# Admin Operations (Database)
-- Assign tier to user
INSERT INTO user_tiers (user_id, tier_id, expires_at) VALUES (1, 2, NOW() + INTERVAL 1 MONTH);

-- Ban a user (shadow ban)
UPDATE users SET is_shadow_banned = 1 WHERE telegram_id = 123456789;

-- Check user tier
SELECT t.name, t.daily_limit FROM user_tiers ut JOIN tiers t ON ut.tier_id = t.id WHERE ut.user_id = 1;
```

### C. Useful SQL Queries

```sql
-- Get today's submission count per user
SELECT u.telegram_id, u.username, du.count
FROM daily_usage du
JOIN users u ON du.user_id = u.id
WHERE du.usage_date = CURDATE()
ORDER BY du.count DESC;

-- Get all pending submissions
SELECT * FROM submissions WHERE status = 'pending' ORDER BY submitted_at ASC;

-- Get top commenters (all-time)
SELECT u.telegram_id, u.username, COUNT(c.id) as total_comments
FROM comments c
JOIN users u ON c.user_id = u.id
GROUP BY u.id
ORDER BY total_comments DESC
LIMIT 50;

-- Get users with specific badge
SELECT u.telegram_id, u.username, ub.earned_at
FROM user_badges ub
JOIN users u ON ub.user_id = u.id
WHERE ub.badge_key = 'streak_master'
ORDER BY ub.earned_at DESC;

-- Rebuild leaderboard cache (run rebuild_cache.php instead)
DELETE FROM leaderboard_cache;
-- Then let the system regenerate on next access
```

---

> **Last Updated:** 2026-04-08  
> **Version:** 1.0.0  
> **Repository:** https://github.com/mieburungdara/botmenfess  
> **License:** Proprietary