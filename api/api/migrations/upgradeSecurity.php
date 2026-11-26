<?php
	require_once(__DIR__ . "/setupcontext.php");
	SetupRequireCommandLine();

	const MINIMUM_PASSWORD_COLUMN_LENGTH = 255;
	const EXIT_OK = 0;
	const EXIT_FAILED = 1;
	const EXIT_MIGRATION_NEEDED = 2;

	$passwordColumns = array(
		array("users", "users_password"),
		array("approval_of_users", "approval_of_users_password"),
	);

	$requiredTables = array(
		"rate_limit" => "CREATE TABLE rate_limit (
			rate_limit_key CHAR(64) CHARACTER SET ascii NOT NULL PRIMARY KEY,
			rate_limit_attempts INT NOT NULL,
			rate_limit_window_start DATETIME NOT NULL,
			KEY idx_window (rate_limit_window_start)
		) ENGINE=InnoDB",
	);

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

	function tableExists($con, $table)
	{
		return scalar($con, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", "s", [$table]) > 0;
	}

	function columnDefinition($con, $table, $column)
	{
		$stmt = $con->prepare("SELECT CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
		$stmt->bind_param("ss", $table, $column);
		$stmt->execute();
		$row = $stmt->get_result()->fetch_assoc();
		$stmt->close();
		return $row;
	}

	function tooShortPasswordColumns($con, $passwordColumns)
	{
		$tooShort = array();
		foreach ($passwordColumns as [$table, $column]) {
			$definition = tableExists($con, $table) ? columnDefinition($con, $table, $column) : null;
			if ($definition !== null && (int)$definition["CHARACTER_MAXIMUM_LENGTH"] < MINIMUM_PASSWORD_COLUMN_LENGTH) {
				$tooShort[] = array($table, $column, $definition["IS_NULLABLE"] === "YES");
			}
		}
		return $tooShort;
	}

	function pendingChanges($con, $requiredTables, $passwordColumns)
	{
		$changes = array();
		foreach ($requiredTables as $table => $createSql) {
			if (!tableExists($con, $table)) {
				$changes[] = $createSql;
			}
		}
		foreach (tooShortPasswordColumns($con, $passwordColumns) as [$table, $column, $isNullable]) {
			$changes[] = "ALTER TABLE `$table` MODIFY `$column` VARCHAR(" . MINIMUM_PASSWORD_COLUMN_LENGTH . ") " . ($isNullable ? "NULL" : "NOT NULL");
		}
		return $changes;
	}

	function printChanges($changes)
	{
		foreach ($changes as $sql) {
			echo "  " . preg_replace("/\s+/", " ", $sql) . "\n";
		}
	}

	echo "Databas: " . $settings["database"] . "\n";
	$changes = pendingChanges($con, $requiredTables, $passwordColumns);
	if (count($changes) === 0) {
		echo "Databasen är redan uppdaterad. Ingen migrering behövs.\n";
		SetupExit(EXIT_OK);
	}

	echo "Följande ändringar behövs:\n";
	printChanges($changes);
	if ($checkOnly || $dryRun) {
		echo $checkOnly ? "Kontroll klar, inget ändrat.\n" : "Torrkörning klar, inget ändrat.\n";
		SetupExit($checkOnly ? EXIT_MIGRATION_NEEDED : EXIT_OK);
	}

	foreach ($changes as $sql) {
		$con->query($sql);
	}

	$remaining = pendingChanges($con, $requiredTables, $passwordColumns);
	if (count($remaining) > 0) {
		fwrite(STDERR, "Kontrollen efter migreringen misslyckades. Kvar att göra:\n");
		printChanges($remaining);
		SetupExit(EXIT_FAILED);
	}
	echo "Migreringen är klar och kontrollerad.\n";
?>
