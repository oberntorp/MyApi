<?php
	if (PHP_SAPI !== "cli") {
		http_response_code(403);
		exit("Endast kommandorad.");
	}

	// users.users_token_version skrivs in i varje token. När den ökas slutar användarens äldre tokens att gälla.
	// Befintliga sessioner räknas som version 0 och fortsätter att gälla.
	// Idempotent: kolumnen läggs bara till om den saknas. Inga rader ändras.
	// Användning: php api/migrations/upgradeTokenVersion.php [--check] [--dry-run] [--prod]

	const EXIT_OK = 0;
	const EXIT_FAILED = 1;
	const EXIT_MIGRATION_NEEDED = 2;

	const ADD_TOKEN_VERSION = "ALTER TABLE users ADD COLUMN users_token_version INT NOT NULL DEFAULT 0";

	$checkOnly = in_array("--check", $argv);
	$dryRun = in_array("--dry-run", $argv);
	$_SERVER["SERVER_NAME"] = in_array("--prod", $argv) ? "production" : "localhost";
	require_once(__DIR__ . "/../helpers/config.php");
	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

	$settings = Config::DatabaseSettings();
	$con = new mysqli($settings["server"], $settings["user"], $settings["password"], $settings["database"]);
	$con->set_charset("utf8");

	function scalar($con, $sql, $types = "", $params = [])
	{
		$stmt = $con->prepare($sql);
		if ($types !== "") {
			$stmt->bind_param($types, ...$params);
		}
		$stmt->execute();
		$row = $stmt->get_result()->fetch_row();
		$stmt->close();
		return $row === null ? null : $row[0];
	}

	function tokenVersionExists($con)
	{
		return (int)scalar($con, "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'users_token_version'") > 0;
	}

	echo "Databas: " . $settings["database"] . "\n";
	if (tokenVersionExists($con)) {
		echo "users.users_token_version finns redan. Ingen migrering behövs.\n";
		exit(EXIT_OK);
	}

	echo "Följande ändring behövs:\n  " . ADD_TOKEN_VERSION . "\n";
	if ($checkOnly || $dryRun) {
		echo $checkOnly ? "Kontroll klar, inget ändrat.\n" : "Torrkörning klar, inget ändrat.\n";
		exit($checkOnly ? EXIT_MIGRATION_NEEDED : EXIT_OK);
	}

	$usersBefore = (int)scalar($con, "SELECT COUNT(*) FROM users");
	$con->query(ADD_TOKEN_VERSION);

	$usersAfter = (int)scalar($con, "SELECT COUNT(*) FROM users");
	$nonZero = (int)scalar($con, "SELECT COUNT(*) FROM users WHERE users_token_version <> 0");
	if (!tokenVersionExists($con) || $usersAfter !== $usersBefore || $nonZero !== 0) {
		fwrite(STDERR, "Kontrollen efter migreringen misslyckades. Användare före/efter: $usersBefore/$usersAfter, version skild från 0: $nonZero.\n");
		exit(EXIT_FAILED);
	}
	echo "Migreringen är klar och kontrollerad. Användare: $usersAfter, alla med version 0.\n";
?>
