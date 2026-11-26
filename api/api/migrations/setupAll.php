<?php
	// Kör alla setupskript i ordning (SETUP_SCRIPTS i setupcontext.php) och stannar vid första fel.
	// Varje skript körs i en egen process, eftersom skripten har hjälpfunktioner med samma namn.
	//   php api/api/migrations/setupAll.php --dry-run   visar vad som skulle ändras, skriver ingenting
	//   php api/api/migrations/setupAll.php             lokal databas
	//   php api/api/migrations/setupAll.php --prod      produktionsdatabasen
	// Samma ordning används av setup.php när FtpDeploy kör setup vid utläggning.
	require_once(__DIR__ . "/setupcontext.php");
	SetupRequireCommandLineOnly();

	$flags = array_slice($argv, 1);
	$unknown = array_diff($flags, SETUP_FLAGS);
	if (count($unknown) > 0) {
		fwrite(STDERR, "Okänd flagga: " . implode(" ", $unknown) . ". Tillåtna: " . implode(" ", SETUP_FLAGS) . "\n");
		exit(1);
	}

	$isCheck = in_array("--check", $flags, true);
	$needed = array();
	foreach (SETUP_SCRIPTS as $name) {
		echo "\n=== $name ===\n";
		$command = escapeshellarg(PHP_BINARY) . " " . escapeshellarg(__DIR__ . "/" . $name . ".php");
		foreach ($flags as $flag) {
			$command .= " " . escapeshellarg($flag);
		}
		passthru($command, $code);
		if ($isCheck && $code === 2) {
			$needed[] = $name;
			continue;
		}
		if ($code !== 0) {
			fwrite(STDERR, "\nAvbröt: $name avslutades med kod $code. Skripten efter det kördes inte.\n");
			exit(1);
		}
	}

	if ($isCheck) {
		echo count($needed) === 0 ? "\nAlla migreringar är gjorda.\n" : "\nMigrering behövs: " . implode(", ", $needed) . "\n";
		exit(count($needed) === 0 ? 0 : 2);
	}
	echo "\nAlla setupskript är klara.\n";
?>
