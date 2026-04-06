# Telegram Menfess Bot

Implementation of a Telegram-based menfess (anonymous confession) system as outlined in the plan.

## Features

- Anonymous message submission via Telegram bot
- Admin approval workflow for content moderation
- Automatic posting to Telegram channel upon approval
- MySQL database storage with InnoDB engine
- Logging and error handling
- Command interface for users and administrators

## Setup

1. Install dependencies:
   ```bash
   composer install
   ```

2. Create a `.env` file based on the example:
   ```env
   TELEGRAM_BOT_TOKEN=your_bot_token_here
   TARGET_CHANNEL_ID=your_target_channel_id_here
   DB_HOST=localhost
   DB_NAME=menfess_bot
   DB_USERNAME=root
   DB_PASSWORD=
   ```

3. Set up the database:
   ```bash
   mysql -u root -p < database_schema.sql
   ```

4. Configure your Telegram bot:
   - Create a bot via @BotFather
   - Get the API token
   - Add the bot as an administrator to your target channel
   - Get the channel ID (can be obtained by forwarding a message from the channel to @userinfobot)

5. Run the bot:
   ```bash
   php index.php
   ```

## Usage

Users interact with the bot via private messages:
- Send any message to submit an anonymous confession
- Use `/start` for welcome message
- Use `/help` for usage instructions
- Use `/status` to see submission statistics

Administrators can moderate submissions through the bot interface (implementation would need to be extended for admin commands).

## Database Schema

The bot uses two tables as specified in the plan:
- `submissions`: Stores incoming messages with status tracking
- `channel_posts`: Tracks which submissions have been posted to the channel

Both tables use the InnoDB engine for ACID compliance and foreign key support.

## Implementation Details

Built with:
- PHP 7.4+
- telegram-bot/api library for Telegram Bot API integration
- MySQL with InnoDB engine for data storage
- Monolog for logging
- vlucas/phpdotenv for environment variable management

The implementation follows the workflow outlined in the plan:
1. User sends message to bot
2. Bot stores message in database with 'pending' status
3. Admin reviews and approves/rejects submission
4. Approved submissions are posted to the Telegram channel
5. Bot maintains anonymity by not storing or revealing user information