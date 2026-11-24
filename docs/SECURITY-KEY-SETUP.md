# SLBridge Encryption Key Setup

SLBridge requires a unique 256-bit encryption key for each Moodle site.

## 1. Generate a key

Run:

```bash
/usr/local/apps/php83/bin/php -r \
'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Generate a different key for every Moodle installation.

## 2. Configure Moodle

Back up config.php.

Add the following line to Moodle's config.php,
after the CFG object is initialized and before
lib/setup.php is loaded:

```php
$CFG->slbridge_encryption_key = 'YOUR_64_CHARACTER_HEX_KEY';
```

Replace the placeholder with the generated key.

Never publish or share this key.

## 3. Protect the key

- Keep the key outside the plugin directory.
- Do not commit it to Git.
- Do not include it in plugin ZIP files.
- Restrict access to config.php.
- Maintain a secure backup.
- Do not change the key while encrypted credentials exist.

## 4. Verify configuration

Run the SLBridge encryption diagnostic:

```bash
/usr/local/apps/php83/bin/php \
local/slbridge/cli/check-encryption.php
```

The diagnostic reports availability without displaying the key.

## 5. Key loss

Losing the encryption key makes existing encrypted
object credentials unrecoverable.

Affected objects must be revoked and paired again
with newly generated credentials.
