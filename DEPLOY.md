# DEPLOY.md – Driftsättning av MyApi

MyApi är ett fristående PHP-API som används av egna projekt. Det finns ingen frontend i repot. Hela API:t ligger i `api/`.

## Krav på servern

Skapad: 2026-11-24

- PHP 8.x med `mysqli`
- Apache med `mod_rewrite` och `AllowOverride All`
- MySQL/MariaDB
- Utgående SMTP om e-post skickas (`api/api/contact.php`)

## Köra lokalt: ny JWT-nyckel

Skapad: 2026-11-25

Från och med säkerhetsuppdateringen 2026-11-25 används php-jwt 7.2.2, som kräver att `jwt_key` är minst 64 tecken. Den lokala nyckeln var kortare (11 tecken), och då fungerar ingen inloggning lokalt. Nyckeln ligger i en hemlighetsfil och byts därför för hand, en gång:

1. Generera en ny nyckel: `php -r "echo bin2hex(random_bytes(32));"`.
2. Ersätt värdet för `jwt_key` i `api/api/config/secrets.config.php` med den nya nyckeln. Ändra inget annat i filen, särskilt inte `password_salt`.
3. Kontrollera längden utan att skriva ut nyckeln: `php -r '$s = require "api/api/config/secrets.config.php"; echo strlen($s["jwt_key"]);'` ska ge minst 64.

Bytet loggar ut lokala sessioner, inget annat. Samma krav gäller på servern, se [Säkerhetsuppdatering 2026-11-25](#säkerhetsuppdatering-2026-11-25).

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
- CORS-headers skickas bara när API:t körs lokalt (`http://localhost:4200`). I produktion tillåts bara anrop från samma domän. Ändra `SetHeaders` i `api/api/apistart.php` om en frontend på en annan domän ska anropa API:t.

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
| `jwt_key` | Slumpad nyckel som signerar inloggningarna, **minst 64 tecken** (php-jwt 7 vägrar kortare nycklar för HS512, och då fungerar ingen inloggning). Kan bytas när som helst, men då loggas alla ut. Generera med `php -r "echo bin2hex(random_bytes(32));"`. |
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

## Säkerhetsuppdatering 2026-11-25

Skapad: 2026-11-25

Säkerheten i API:t har anpassats efter Passwordmngr. Ingen databasmigrering krävs.

### Före utläggningen

- [ ] **Byt `jwt_key` på servern till en ny nyckel på minst 64 tecken** i `api/api/config/secrets.config.php`, `php -r "echo bin2hex(random_bytes(32));"`. php-jwt 7 vägrar kortare nycklar, och då fungerar ingen inloggning. Den gamla nyckeln var kortare och kan finnas i git-historiken, och då kan den som läser repot förfalska inloggningar. Bytet loggar ut alla en gång. Kontrollera längden utan att skriva ut nyckeln: `php -r '$s = require "api/api/config/secrets.config.php"; echo strlen($s["jwt_key"]);'`.
- [ ] Frontenden som använder `users/update` måste skicka det nya fältet `user_password_current` (nuvarande lösenord). Utan det nekas uppdateringen.

### Vad som ändras

- **Inloggning:** php-jwt 7.2.2. Varje skyddat anrop kontrollerar att användaren finns och inte är raderad. Tomt lösenord godtas aldrig. Användare som skapats av en admin fick tidigare hashen av en tom sträng som lösenord (rättat 2026-11-25), se nedan.
- **Konton:** användarnamn valideras (högst 100 tecken, bokstäver, siffror, mellanslag och `.-_@`) och måste vara lediga, lösenord kräver minst 8 tecken. Registreringen tillåter 10 försök per 15 minuter och IP-adress.
- **`users/update`:** kräver nuvarande lösenord i `user_password_current`. Fel lösenord räknas mot inloggningsspärren.
- **Radering:** fungerade inte tidigare (fel parameternamn). Nu raderas användaren, och dess passkeys och MFA-data tas bort.
- **API-svar:** `Access-Control-Allow-Credentials` skickas inte, `Content-Type` är rättad till `application/json; charset=utf-8`, och svaren har `Content-Security-Policy`, `nosniff` och `no-referrer`. CORS-headers skickas bara lokalt.
- **Uppladdning:** bara filer som PHP tagit emot som uppladdning läses.
- **Router:** routes utan definierade parametrar (till exempel `login/getid`) gav PHP-varningar före headers, så 401-statusen och säkerhetsheaders försvann. Rättat.
- **Borttaget:** php-jwt:s tester, som låg i webbroten.

### Konton som skapats av en admin

Före 2026-11-25 sparades lösenordet för användare som en admin skapade som hashen av en tom sträng, så vem som helst kunde logga in på dem med tomt lösenord. Inloggningen nekar nu tomt lösenord, vilket stänger hålet, men de kontona kan då inte logga in alls förrän de fått ett nytt lösenord. Hitta dem utan att visa några hashar:

```
php -r '$c = require "api/api/config/db.config.php"; $e = $c[$c["environment"]]; $m = new mysqli($e["server"], $e["user"], $e["password"], $e["database"]); foreach ($m->query("SELECT users_id, users_username, users_password FROM users WHERE is_deleted = 0") as $r) if (password_verify("", $r["users_password"]) || $r["users_password"] === crypt("", (require "api/api/config/secrets.config.php")["password_salt"])) echo $r["users_id"], " ", $r["users_username"], "\n";'
```

Ge dem ett nytt lösenord, eller skapa kontona på nytt.

### Kontrollera efter utläggning

1. Logga in som admin.
2. `users/update` med fel `user_password_current` ska nekas med "Fel nuvarande lösenord".
3. Ett anrop med ogiltig token, till exempel `curl -s -o /dev/null -w "%{http_code}" -H "Authorization: Bearer x" <API>/login/getid/x`, ska ge 401.

### Återställning

Lägg tillbaka föregående version av API:t. Databasen påverkas inte. Den nya `jwt_key` kan stå kvar, den gamla koden fungerar med den.
## Spärr av sessioner (token_version)

Skapad: 2026-11-26

Varje användare har ett versionsnummer, `users.users_token_version`, som skrivs in i varje token. Vid varje anrop jämförs token med databasen. När numret ökas slutar alla användarens äldre tokens att gälla direkt, även en stulen token som hålls vid liv med förnyelse.

Numret ökas när användaren:

- **byter lösenord** med `users/update`. Svaret har en ny `session_id` för den session som gjorde bytet.

`login/get` kontrollerar nu användaren och versionen, inte bara signaturen. Tokens som utfärdats före utläggningen saknar versionsnumret och räknas som version 0, så ingen loggas ut vid utläggningen.

MyApi delar tabellen `users` med TarnoVa. Ett lösenordsbyte här avslutar därför också användarens sessioner i TarnoVa, och "Logga ut på alla enheter" i TarnoVa avslutar sessionerna här. Skriptet finns i båda repona och kan köras från vilket som helst. Den andra körningen svarar att ingen migrering behövs.

### Migrering

`api/api/migrations/upgradeTokenVersion.php` har samma flaggor som de andra skripten. Kör migreringen **innan** koden läggs ut. Den nya koden läser kolumnen, och utan den fungerar ingen inloggning.

1. Ta en export av databasen.
2. `php api/api/migrations/upgradeTokenVersion.php --prod --dry-run` visar satsen: `ALTER TABLE users ADD COLUMN users_token_version INT NOT NULL DEFAULT 0`.
3. `php api/api/migrations/upgradeTokenVersion.php --prod` ska avsluta med "Migreringen är klar och kontrollerad".
4. `--prod --check` ska svara att ingen migrering behövs.

### Kontrollera efter utläggning

1. Logga in två gånger (två tokens). Byt lösenord med `users/update` med den ena: den nya token i svaret fungerar, och båda de gamla får 401.

### Återställning

Lägg tillbaka föregående kod. Kolumnen kan stå kvar, den gamla koden använder den inte. Vill du ta bort den när den gamla koden ligger ute: `ALTER TABLE users DROP COLUMN users_token_version;`.

## Setup vid utläggning (setupAll och FtpDeploy)

Skapad: 2026-11-26

Alla databasmigreringar kan köras i rätt ordning med ett kommando, eller av FtpDeploy innan koden läggs ut. Varje skript kan fortfarande köras ensamt som förut.

- **Ordningen** står i `SETUP_SCRIPTS` i `api/api/migrations/setupcontext.php`: upgradeSecurity, upgradeTokenVersion. Ett nytt skript läggs till sist i listan.
- **Kommandorad:** `php api/api/migrations/setupAll.php` med `--check`, `--dry-run` och `--prod`. Skripten körs i ordning, vart och ett i en egen process, och det stannar vid första fel.
- **FtpDeploy:** fyll i profilens **Setup-adress**: `https://<API:ts domän>/api/setup.php`. Med **Kör setup innan utläggning** laddar FtpDeploy först upp setup-filerna (enligt `setup.json` bredvid `setup.php`). Sedan lägger den en engångstoken i `api/api/config/setup_token.php`, kör skripten ett i taget via `api/setup.php` och raderar tokenfilen. Resten av koden läggs ut bara om alla skript lyckades. **Kontrollera setup** gör samma sak som torrkörning. Hela det tekniska flödet beskrivs i `DEPLOY.md` i FtpDeploy, avsnittet "Setup: databasmigreringar vid utläggning". Användningen står i FtpDeploys `ANVANDNING.md`, avsnitt 4.
- **Skydd:** `setup.php` svarar 404 när tokenfilen saknas, alltså mellan utläggningar. Den kräver POST, HTTPS och rätt token (`hash_equals`), kör bara skript i `SETUP_SCRIPTS` och på servern alltid med `--prod`. Tokenfilen ligger i `.gitignore` och i en mapp som `.htaccess` spärrar för webben.
- MyApi delar databas med TarnoVa. Skripten är idempotenta, så det spelar ingen roll vilket projekts setup som kör först.

### Första utläggningen med setup

1. Ta en export av databasen.
2. Fyll i setup-adressen i FtpDeploy-profilen, klicka **Förhandsgranska** och sedan **Kontrollera setup**. Läs loggen: den visar vad varje skript skulle ändra.
3. Lägg ut med **Kör setup innan utläggning** ikryssad.
4. Kontrollera att tokenfilen inte finns kvar på servern, och att `curl -s -o /dev/null -w "%{http_code}" -X POST <setup-adressen>` svarar `404`.

### Återställning

Setup-filerna påverkar inte sajten. Vill du stänga av möjligheten helt raderar du `setup.php` på servern. Databasändringarna återställs enligt avsnittet för respektive migrering.

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
