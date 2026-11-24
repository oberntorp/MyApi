# DEPLOY.md – Driftsättning av MyApi

MyApi är ett fristående PHP-API som används av egna projekt. Det finns ingen frontend i repot. Hela API:t ligger i `api/`.

## Krav på servern

- PHP 8.x med `mysqli`
- Apache med `mod_rewrite` och `AllowOverride All`
- MySQL/MariaDB
- Utgående SMTP om e-post skickas (`api/api/contact.php`)

## Före varje utläggning

1. Kontrollera att du står på rätt gren och att allt är incheckat (`git status`).
2. Ta backup av databasen om schemat har ändrats.
3. Kontrollera att `api/api/config/db.config.php` och `secrets.config.php` finns på servern (se nedan). De följer inte med vid utläggning.

## Lägg ut

Kopiera `api/`-mappen till webbplatsens `api/` på servern.

- `api/.htaccess` måste följa med. Den vidarebefordrar `Authorization`-headern och skickar alla anrop till `index.php`.
- Anrop har formen `.../api/<resurs>/<metod>`.
- CORS tillåter i dag bara `http://localhost:4200` (se `Access-Control-Allow-Origin` i `api/api/apistart.php`). Ändra där om en annan frontend ska anropa API:t.

## Konfigfiler på servern

Hemligheterna ligger i två filer i `api/api/config/`. De ligger i `.gitignore` och läses av `Config` i `api/api/helpers/config.php`, som kastar ett tydligt fel om en fil saknas. Till varje fil finns en `*.example.php` att kopiera.

**`db.config.php`**

| Nyckel | Betydelse |
| --- | --- |
| `environment` | Vilket block som används när API:t inte anropas via `localhost`: `"prod"` eller `"testsite"`. |
| `local` | Används alltid när API:t anropas via `localhost`. |
| `testsite`, `prod` | Databasuppgifter med `user`, `password`, `server` och `database`. |

**`secrets.config.php`**

| Nyckel | Betydelse |
| --- | --- |
| `jwt_key` | Slumpad nyckel som signerar inloggningarna. Kan bytas när som helst, men då loggas alla ut. Generera med `php -r "echo bin2hex(random_bytes(32));"`. |
| `password_salt` | Salt för lösenordshashar. Måste vara samma värde som när lösenorden sparades. |
| `recaptcha_secret` | Hemlig nyckel för Google reCAPTCHA. |

Byt aldrig `password_salt` utan migrering. Alla lösenord blir då ogiltiga.

> Produktionsblocket i den ursprungliga konfigurationen var tomt. Fyll i `prod` innan du lägger ut på en server som inte är `localhost`.

Filerna i `config/` kan nås via webbläsaren om servern är felkonfigurerad, så lägg en `Deny from all` i en `.htaccess` i mappen.

## Efter utläggning – kontrollera

1. Anropa en öppen route och kontrollera att svaret är JSON och inte ett PHP-fel.
2. Anropa en skyddad route utan inloggning. Den ska ge 401.
3. Logga in och kontrollera att en skyddad route fungerar med `Authorization: Bearer <session>`.
4. Titta i serverns felogg.

## Återställning

- Gå tillbaka till föregående commit och lägg ut `api/` igen. Konfigfilerna på servern påverkas inte.
- Återställ databasen från backup om schemat har ändrats.

Äldre hemligheter finns kvar i git-historiken. Byt lösenord och nycklar om repot delas.
