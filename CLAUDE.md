# MyApi

Svara och skriv dokumentation på svenska. Driftsättning beskrivs i `DEPLOY.md`.

## Kodstil för API:t (PHP)

Gäller `api/api/`. Standarden beskriver hur den befintliga koden redan är skriven. Där koden är blandad gäller det som de flesta filer gör. Följ standarden i ny kod och i kod du ändrar. Formatera inte om kod som du inte ändrar av andra skäl.

### Format
- Indrag med tabbar. Några äldre filer har fyra mellanslag: behåll då filens indrag, så att en fil aldrig blandar. Nya filer får tabbar.
- Klasser och funktioner har klammern på egen rad:
  ```php
  public function GetPageById($parameters)
  {
  ```
- `if`, `foreach`, `while` och `try` har klammern på samma rad, och alltid klammer, även för en enda rad: `} else {`, `} catch (Exception $e) {`.
- Mellanslag efter nyckelordet: `if (`, `foreach (`.
- Strängar med dubbla citattecken. Enkla citattecken bara när strängen innehåller dubbla, till exempel i ett reguljärt uttryck.
- `array(...)` för svar och associativa arrayer, `[...]` för parametrar till databasen: `"i", [$userId]`.
- Filen börjar med `<?php` och slutar med `?>`.
- Radavslut CRLF (`core.autocrlf=true`). Kontrollera med `git ls-files --eol <fil>`, inte med `grep`, som räknar fel i Git Bash.

### Namn
- Klasser och metoder i PascalCase (`UsersHelper`, `GetUserIdFromSession`). Variabler och egenskaper i camelCase (`$userId`, `$this->usersHelper`). Konstanter med versaler och understreck (`SESSION_USER_ID_PART`).
- En route-klass (till exempel `Users` i `users.php`) tar emot `$parameters`, validerar och svarar. Gemensam logik ligger i hjälpklasser i `helpers/`, som heter `...Helper` eller efter vad de gör (`LoginThrottle`, `PasswordVerifier`).
- Kolumner har tabellens namn som prefix (`users_username`, `app_setting_key`). Id-kolumner är `INT`.
- SQL med versaler för nyckelorden: `SELECT users_id FROM users WHERE is_deleted = 0 AND users_id = ?`.

### Kommentarer
- Få, och på svenska. Skriv varför, inte vad: en säkerhetsregel, ett beteende som inte syns i koden eller skälet till ett val. Låt namnen förklara resten.

### Databas
- Bara via `Database::Select/Insert/Update` med förberedda frågor och typsträng: `$this->db->Select("... WHERE users_id = ?", "i", [$userId])`. Sätt aldrig in värden i SQL-strängen (`sprintf` eller sammanfogning).
- `Select` ger `[-1, ...]` när inget hittas: kontrollera `$result[0] == -1` och svara tydligt. För `Insert` och `Update` betyder `$result[0] < 0` fel.

### Routes och behörighet
- En ny route läggs i `$mappOfClassesMethods` i `helpers/urlmapp.php`: klassen i `classes`, metoden i `methods`, och parametrarna i `queryStrings`, `postKeys` eller `putKeys`. En klass som inte står i `classes` går inte att nå.
- Behörigheten står i `helpers/security.php`. En klass som saknas i `$methodsRequiringAuth` kräver inloggning för alla metoder. Står klassen i listan kräver bara de uppräknade metoderna inloggning. **En ny metod i en listad klass är alltså publik tills den läggs till i listan.** Admin-routes står också i `$methodsRequiringAdmin`.
- Användarens id tas från token (`GetUserIdFromSession(Security::SessionIdFromRequest(), $this->db)`), aldrig från anropet. Admin kontrolleras i databasen, aldrig i token.

### Svar och fel
- Fel: `UsersHelper::Failure("Meddelande på svenska")`, som ger `{"0": 0, "error": "..."}`. Klienter som jämför med `1` läser det fortfarande som fel. Spärr: HTTP 429 med `Retry-After` via `RateLimit::TooManyAttempts`.
- Behåll API-kontraktet: nya fält får läggas till, men befintliga byter inte namn eller betydelse.
- Svaret får aldrig innehålla databasfel, sökvägar, `print_r` eller `var_dump`. Logga med `error_log`.

### Säkerhet
- Användarnamn och lösenord valideras på servern med `UserValidation`. Heltal valideras med `filter_var(..., FILTER_VALIDATE_INT)`.
- Lösenord hashas med `HashPassword` (`password_hash`). Fel lösenord räknas mot `LoginThrottle`.
- Filer läses bara när `is_uploaded_file` godkänner dem, och typen bestäms av innehållet med `finfo`, som `DetectedMimeType` i TarnoVa. Webbläsarens uppgivna typ används aldrig.
- Hemligheterna ligger i API:ts `config/`-mapp. Värdena skrivs aldrig ut, loggas eller checkas in.

### Databasändringar
- Ett idempotent CLI-skript i API:ts `migrations/`-mapp med `--check`, `--dry-run` och `--prod` (mönster: `upgradeSecurity.php`). En andra körning gör ingenting, och resultatet kontrolleras efteråt.
- Körning, kontroll och återställning beskrivs i DEPLOY.md. Migreringen körs innan koden läggs ut.
- Skriptet startar med `require_once(__DIR__ . "/setupcontext.php"); SetupRequireCommandLine();` och avslutar med `SetupExit(kod)` eller `SetupFail(text)`, aldrig `exit`, så att `setup.php` i API-roten (FtpDeploy) kan köra det. Lägg till det sist i `SETUP_SCRIPTS` i `migrations/setupcontext.php`. `setupAll.php` i samma mapp kör alla i ordning.

### Verifiering
- Kör `php -l` på ändrade filer.
- Testa mot localhost (WAMP), som utloggad, vanlig användare och admin. Testa aldrig mot produktion utan uttrycklig begäran.

## DEPLOY.md
- Varje avsnitt (`##`) har raden `Skapad: yyyy-mm-dd` direkt under rubriken: dagen då avsnittet checkades in.
- Nytt avsnitt: lägg till raden med dagens datum. En underrubrik eller punkt som läggs till ett senare datum än sitt avsnitt får en egen `Skapad:`-rad.
- Ändra inte datumet när ett befintligt avsnitt uppdateras.

## Git
- Commit-meddelande: rubriken `Changes made:`, blankrad, punktlistor efter ämne på engelska. Avsluta med trailern `Co-Authored-By: Claude ... <noreply@anthropic.com>`.
