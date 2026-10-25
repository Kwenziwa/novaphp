# Configuration

# Configuration and environment

PHP files in `config/` must return arrays. Each filename becomes its top-level key. The repository supports dot notation, defaults, mutation, and complete inspection:

```php
$app->config->get('app.debug', false);
$app->config->set('services.mail.host', 'localhost');
$app->config->all();
```

Copy `.env.example` to `.env` if needed. `.env` is loaded before config files and is ignored by Git. Existing process environment, `$_ENV`, and `$_SERVER` values take precedence. The loader supports blank lines, comments, optional `export`, empty values, and single/double quoted literal values. Whitespace followed by `#` starts a comment in an unquoted value. Quotes do not support escape sequences. There is no variable interpolation, command substitution, or code evaluation. Malformed entries fail with a line number before any values from the file are applied. Missing `.env` files are allowed.

Config files read environment values with `getenv()`. Keep parsing explicit: `app.debug` uses `FILTER_VALIDATE_BOOLEAN`, so `APP_DEBUG=false` stays false.

```bash
php nova config:show             # all config
php nova config:show app.debug   # one dot-notation key; missing keys print null
```

Config inspection prints the requested values without redaction; run it locally and avoid sharing output containing credentials.
