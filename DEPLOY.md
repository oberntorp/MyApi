# DEPLOY.md – Driftsättning av MyApi

MyApi är ett fristående PHP-API som används av egna projekt. Det finns ingen frontend i repot. Hela API:t ligger i `api/`.

## Krav på servern

Skapad: 2026-11-24

- PHP 8.x med `mysqli`
- Apache med `mod_rewrite` och `AllowOverride All`
- MySQL/MariaDB
- Utgående SMTP om e-post skickas (`api/api/contact.php`)

## Före varje utläggning

Skapad: 2026-11-24

1. Kontrollera att du står på rätt gren och att allt är incheckat (`git status`).
2. Ta backup av databasen om schemat har ändrats.
3. Kontrollera att `api/api/config/db.config.php` och `secrets.config.php` finns på servern (se nedan). De följer inte med vid utläggning.

## Lägg ut

Skapad: 2026-11-24

Kopiera `api/`-mappen till webbplatsens `api/` på servern.

- `api/.htaccess` måste följa med. Den vidarebefordrar `Authorization`-headern och skickar alla anrop till `index.php`.
- Anrop har formen `.../api/<resurs>/<metod>`.
- CORS tillåter i dag bara `http://localhost:4200` (se `Access-Control-Allow-Origin` i `api/api/apistart.php`). Ändra där om en annan frontend ska anropa API:t.

## Konfigfiler på servern

Skapad: 2026-11-24

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
| `password_salt` | Salt för sessionerna och för gamla lösenordshashar. Måste vara samma värde som tidigare. Nya lösenord sparas med `password_hash`. |
| `recaptcha_secret` | Hemlig nyckel för Google reCAPTCHA. |
| `client_ip_header` | Valfri. Header med besökarens riktiga IP-adress när sajten ligger bakom en proxy, till exempel `CF-Connecting-IP` bakom Cloudflare. Används av inloggningsspärren. Sätt den bara om all trafik går via proxyn, annars kan headern förfalskas. Utelämnad används `REMOTE_ADDR`. |

Byt aldrig `password_salt` utan migrering. Alla lösenord blir då ogiltiga.

> Produktionsblocket i den ursprungliga konfigurationen var tomt. Fyll i `prod` innan du lägger ut på en server som inte är `localhost`.

Mapparna `config/`, `helpers/logs/` och `migrations/` har en `.htaccess` som spärrar dem för webben. Kontrollera att filerna följer med vid utläggning (FTP-program hoppar ofta över filer som börjar med punkt).

## Databasmigrering

Skapad: 2026-11-24

Databasändringar görs med ett kommandoradsskript, `api/api/migrations/upgradeSecurity.php`. Det följer samma mönster som Passwordmngrs skript i `hasher/`:

- Det kontrollerar först databasen. Är allt redan på plats skriver det "Databasen är redan uppdaterad. Ingen migrering behövs." och ändrar ingenting.
- Det kan köras hur många gånger som helst. En andra körning gör ingenting.
- Efter en migrering kontrollerar det resultatet och avslutar med en felkod om något saknas.
- Det går bara att köra från kommandoraden. Mappen `migrations/` är dessutom spärrad för webben med `.htaccess`.

Skriptet skapar i dag tabellen `rate_limit` (begränsning av inloggningsförsök) och breddar lösenordskolumnerna till minst 255 tecken om de är kortare, så att de rymmer `password_hash`.

| Kommando | Vad det gör | Avslutningskod |
| --- | --- | --- |
| `php api/api/migrations/upgradeSecurity.php --check` | Visar vad som behövs, ändrar ingenting | `0` allt klart, `2` migrering behövs |
| `php api/api/migrations/upgradeSecurity.php --dry-run` | Visar SQL-satserna som skulle köras, ändrar ingenting | `0` |
| `php api/api/migrations/upgradeSecurity.php` | Kör migreringen och kontrollerar resultatet | `0` klart, `1` kontrollen misslyckades |

Utan flagga används databasen i `local`-blocket i `db.config.php`. Med `--prod` används blocket som `"environment"` pekar på (`prod` eller `testsite`) i konfigfilen på den server där skriptet körs.

### Så migrerar du en server

1. Ta en export av databasen.
2. Ladda upp mappen `migrations/` och `helpers/config.php` (eller hela API:t).
3. Logga in på servern med SSH och kör kontrollen:
   ```bash
   php api/api/migrations/upgradeSecurity.php --prod --check
   ```
   Får du "Ingen migrering behövs" är du klar med databasen och kan gå till steg 6.
4. Se vilka satser som körs:
   ```bash
   php api/api/migrations/upgradeSecurity.php --prod --dry-run
   ```
5. Kör migreringen:
   ```bash
   php api/api/migrations/upgradeSecurity.php --prod
   ```
   Den ska avsluta med "Migreringen är klar och kontrollerad". Kör `--check` en gång till om du vill vara säker.
6. Ladda upp resten av koden.

Migrera alltid **före** du laddar upp kod som kräver ändringen, och prova på testsajten först.

**Utan SSH** (till exempel delade webbhotell): kör `--dry-run` lokalt med en `db.config.php` som pekar på serverns databas, om databasen går att nå utifrån. Annars kör du satserna som `--dry-run` skriver ut i phpMyAdmin. Kör sedan `--check` på samma sätt för att se att allt är klart.

### Lösenord efter migreringen

- Befintliga lösenord fungerar som förut. Vid nästa lyckade inloggning byts användarens hash automatiskt till `password_hash` (bcrypt).
- `password_salt` behövs fortfarande för sessionerna och för att känna igen gamla hashar. Ta inte bort det och byt det inte.
- Efter 10 felaktiga försök inom 15 minuter, per IP-adress eller per användarnamn, spärras inloggningen i 15 minuter. API:t svarar då med `429` och ett meddelande. Vill du häva en spärr direkt tömmer du tabellen `rate_limit`.

## Efter utläggning – kontrollera

Skapad: 2026-11-24

1. Anropa en öppen route och kontrollera att svaret är JSON och inte ett PHP-fel.
2. Anropa en skyddad route utan inloggning. Den ska ge 401.
3. Logga in och kontrollera att en skyddad route fungerar med `Authorization: Bearer <session>`.
4. Titta i serverns felogg.

## Återställning

Skapad: 2026-11-24

- Gå tillbaka till föregående commit och lägg ut `api/` igen. Konfigfilerna på servern påverkas inte.
- Återställ databasen från backup om schemat har ändrats.

Äldre hemligheter finns kvar i git-historiken. Byt lösenord och nycklar om repot delas.
