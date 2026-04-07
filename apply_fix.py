import os

# Apply fixes to index.php
with open('index.php', 'r', encoding='utf-8') as f:
    d = f.read()

# Fix 1: Guard pcntl_signal_dispatch() for Windows compatibility
d = d.replace(
    '        pcntl_signal_dispatch();',
    "        if (function_exists('pcntl_signal_dispatch')) pcntl_signal_dispatch();"
)

# Fix 2: Fix single-quoted \n on the echo line (prints literal \n in PHP)
d = d.replace(". '\\n';", '. PHP_EOL;')

# Fix 3: helpers.php - cast price to float for number_format
with open('helpers.php', 'r', encoding='utf-8') as f:
    h = f.read()

# number_format handles string prices fine, but let's ensure no PHP 8 warning
# The line: $pr = number_format($t['price'],0,',','.');
# $t['price'] from PDO is already a string/numeric, number_format handles it. No fix needed.

# Fix 4: helpers.php line 70 - move user save AFTER limit check
# Currently: save user -> check limit -> record usage -> insert submission
# Better: check limit -> save user -> record usage -> insert submission
old_submit = (
    "function menfessHandleSubmit($api, $cid, $uid, $txt, $pdo) {\n"
    "    if (empty(trim($txt))) return;\n"
    "    // Save/update user\n"
    "    $stmt = $pdo->prepare('INSERT INTO users (telegram_id, first_seen) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE last_seen = NOW()');\n"
    "    $stmt->execute([$uid]);\n"
    "    if (!menfessCheckLimit($pdo, $api, $cid, $uid)) return;\n"
    "    menfessRecordUsage($pdo, $uid);\n"
    "    $pdo->prepare('INSERT INTO submissions (message_text) VALUES (?)')->execute([$txt]);\n"
    "    $api->sendMessage($cid, 'Pesan menfess berhasil dikirim!');\n"
    "}"
)
new_submit = (
    "function menfessHandleSubmit($api, $cid, $uid, $txt, $pdo) {\n"
    "    if (empty(trim($txt))) return;\n"
    "    if (!menfessCheckLimit($pdo, $api, $cid, $uid)) return;\n"
    "    // Save/update user (only if submission is allowed)\n"
    "    $stmt = $pdo->prepare('INSERT IGNORE INTO users (telegram_id, first_seen) VALUES (?, NOW())');\n"
    "    $stmt->execute([$uid]);\n"
    "    menfessRecordUsage($pdo, $uid);\n"
    "    $pdo->prepare('INSERT INTO submissions (message_text) VALUES (?)')->execute([$txt]);\n"
    "    $pdo->prepare('UPDATE users SET last_seen = NOW(), submission_count = submission_count + 1 WHERE telegram_id = ?')->execute([$uid]);\n"
    "    $api->sendMessage($cid, 'Pesan menfess berhasil dikirim!');\n"
    "}"
)
h = h.replace(old_submit, new_submit)

with open('index.php', 'w', encoding='utf-8') as f:
    f.write(d)
with open('helpers.php', 'w', encoding='utf-8') as f:
    f.write(h)

print('All fixes applied')
