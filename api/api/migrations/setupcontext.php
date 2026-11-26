<?php
	// Gemensamt för migreringsskripten, setupAll.php (kommandorad) och setup.php i API-roten (körs av FtpDeploy via HTTPS).
	// SETUP_SCRIPTS är ordningen som alla körs i. Ett nytt migreringsskript läggs till sist i listan.
	const SETUP_SCRIPTS = array("upgradeSecurity", "upgradeTokenVersion");
	const SETUP_FLAGS   = array("--check", "--dry-run", "--prod");

	if (!class_exists("SetupFinished")) {
		class SetupFinished extends Exception
		{
		}
	}

	if (!defined("STDERR")) {
		define("STDERR", fopen("php://output", "w"));
	}

	// Skripten körs bara från kommandoraden, eller från setup.php efter att token har kontrollerats.
	function SetupRequireCommandLine()
	{
		if (PHP_SAPI !== "cli" && !defined("SETUP_RUNNER")) {
			http_response_code(403);
			exit("Endast kommandorad.");
		}
	}

	// setupAll.php startar egna processer och får aldrig köras från webben.
	function SetupRequireCommandLineOnly()
	{
		if (PHP_SAPI !== "cli") {
			http_response_code(403);
			exit("Endast kommandorad.");
		}
	}

	function SetupExit($code)
	{
		if (defined("SETUP_RUNNER")) {
			throw new SetupFinished("", (int)$code);
		}
		exit((int)$code);
	}

	function SetupFail($message)
	{
		echo $message;
		SetupExit(1);
	}

	// Kör ett skript i samma process (setup.php). Bara ett skript per anrop, eftersom skripten har egna hjälpfunktioner
	// med samma namn. Svaret är avslutningskoden och allt skriptet skrev ut.
	function SetupRunScript($name, $arguments)
	{
		$argv = array_merge(array($name . ".php"), $arguments);
		ob_start();
		try {
			include(__DIR__ . "/" . $name . ".php");
			$code = 0;
		} catch (SetupFinished $finished) {
			$code = $finished->getCode();
		} catch (Throwable $error) {
			echo "FEL: " . $error->getMessage() . "\n";
			$code = 1;
		}
		return array("code" => $code, "output" => ob_get_clean());
	}
?>
