# Telegram Menfess Bot

Implementation of a Telegram-based menfess (anonymous confession) system as outlined in the plan.

## Features

- Anonymous message submission via Telegram bot
- Admin approval workflow for content moderation
- Automatic posting to Telegram channel upon approval
- MySQL database storage with InnoDB engine
- Logging and error handling
- Command interface for users and administrators
- Webhook support for production deployments
- Automatic queuing with 1-minute interval between posts
- User notifications with queue position and estimated wait time

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
   WEBHOOK_URL=https://yourdomain.com/botmenfess  # Required for webhook mode
   USE_WEBHOOK=true  # Set to true for webhook mode, false for polling mode
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
   - For development/polling mode:
     ```bash
     php index.php
     ```
   - For production/webhook mode:
     ```bash
     php index.php  # This will set up the webhook
     ```
     Then configure your web server to route requests to `webhook.php`

## Usage

Users interact with the bot via private messages:
- Send any message to submit an anonymous confession
- Use `/start` for welcome message
- Use `/help` for usage instructions
- Use `/status` to see submission statistics

The bot now provides users with:
- Their position in the queue
- Estimated time until their menfess will be posted
- Confirmation that their message has been received

Administrators can moderate submissions through the bot interface (implementation would need to be extended for admin commands).

## Database Schema

The bot uses three tables:
- `users`: Stores information about users who interact with the bot (for analytics only)
- `submissions`: Stores incoming messages with status tracking
- `channel_posts`: Tracks which submissions have been posted to the channel

**Important Note on User Anonymity**: While the bot stores user information in the `users` table for analytics purposes, this data is **NOT** linked to submissions. The `submissions` table contains only the message text and metadata, ensuring complete anonymity of menfess posts.

All tables use the InnoDB engine for ACID compliance and foreign key support.

## Implementation Details

Built with:
- PHP 7.4+
- telegram-bot/api library for Telegram Bot API integration
- MySQL with InnoDB engine for data storage
- Monolog for logging
- vlucas/phpdotenv for environment variable management

The implementation follows the workflow outlined in the plan with enhancements:
1. User sends message to bot
2. Bot stores message in database with 'pending' status and calculates queue position
3. Bot informs user of their queue position and estimated wait time
4. After 1 minute intervals (adjusted for queue), bot automatically approves and posts the oldest pending submission
5. Posted submission is recorded in channel_posts table
6. Bot maintains anonymity by not storing or revealing user information

## Webhook Setup

For production use, it's recommended to use webhooks instead of polling:

1. Set `USE_WEBHOOK=true` in your `.env` file
2. Set `WEBHOOK_URL` to your domain where the bot is hosted (without the webhook.php path)
3. Run `php index.php` once to set up the webhook with Telegram
4. Configure your web server to route HTTPS requests to `/webhook.php` to this script
5. Ensure your server has a valid SSL certificate (Telegram requires HTTPS for webhooks)

Example webhook URL structure:
- WEBHOOK_URL: https://example.com/botmenfess
- Telegram will send updates to: https://example.com/botmenfess/webhook.php