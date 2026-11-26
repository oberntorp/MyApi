<?php
	// Kör setupskripten på servern. Anropas av FtpDeploy vid utläggning, före resten av koden:
	//   POST {"action": "list"}                                         -> {"scripts": [...], "environment": "prod"}
	//   POST {"action": "run", "script": "<namn>", "mode": "dry-run"|"run"} -> {"script", "mode", "code", "output"}
	// Varje anrop kräver headern X-Setup-Token med samma värde som tokenfilen. FtpDeploy laddar upp tokenfilen med en ny
	// slumpad token före anropen och raderar den efteråt. Utan tokenfil svarar skriptet 404, så det är avstängt mellan
	// utläggningar. Bara skript i SETUP_SCRIPTS kan köras, ett per anrop, och på servern alltid med --prod.
	const SETUP_CONTEXT          = __DIR__ . "/api/migrations/setupcontext.php";
	const SETUP_TOKEN_FILE       = __DIR__ . "/api/config/setup_token.php";
	const SETUP_TOKEN_MIN_LENGTH = 32;
	const SETUP_MODES            = array("dry-run" => array("--dry-run"), "run" => array());

	function SetupRespond($data, $status = 200)
	{
		http_response_code($status);
		header("Content-Type: application/json; charset=utf-8");
		header("Cache-Control: no-store");
		header("X-Content-Type-Options: nosniff");
		header("Content-Security-Policy: default-src 'none'");
		echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
		exit;
	}

	function SetupExpectedToken()
	{
		$token = is_file(SETUP_TOKEN_FILE) ? require(SETUP_TOKEN_FILE) : "";
		return is_string($token) && strlen($token) >= SETUP_TOKEN_MIN_LENGTH ? $token : null;
	}

	function SetupIsHttps()
	{
		return (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") || strtolower($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https";
	}

	$expected = SetupExpectedToken();
	$given = (string)($_SERVER["HTTP_X_SETUP_TOKEN"] ?? "");
	if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST" || $expected === null || !hash_equals($expected, $given)) {
		SetupRespond(array("error" => "Not found"), 404);
	}

	// Avgörs innan skripten körs, eftersom de själva skriver över SERVER_NAME. SERVER_NAME kan komma från klientens
	// Host-header, så anropet måste också komma från datorn själv för att räknas som lokalt.
	$isLoopback = in_array($_SERVER["REMOTE_ADDR"] ?? "", array("127.0.0.1", "::1"), true);
	$isLocal = $isLoopback && ($_SERVER["SERVER_NAME"] ?? "") === "localhost";
	if (!$isLocal && !SetupIsHttps()) {
		SetupRespond(array("error" => "HTTPS krävs"), 403);
	}

	define("SETUP_RUNNER", true);
	require_once(SETUP_CONTEXT);

	$input = json_decode(file_get_contents("php://input"), true) ?: array();
	$action = (string)($input["action"] ?? "");
	if ($action === "list") {
		SetupRespond(array("scripts" => SETUP_SCRIPTS, "environment" => $isLocal ? "local" : "prod"));
	}
	if ($action !== "run") {
		SetupRespond(array("error" => "Okänd åtgärd"), 400);
	}

	$script = (string)($input["script"] ?? "");
	$mode = (string)($input["mode"] ?? "");
	if (!in_array($script, SETUP_SCRIPTS, true) || !array_key_exists($mode, SETUP_MODES)) {
		SetupRespond(array("error" => "Okänt skript eller läge"), 400);
	}

	// Låset på tokenfilen hindrar två körningar samtidigt.
	$lock = fopen(SETUP_TOKEN_FILE, "r");
	if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
		SetupRespond(array("error" => "En annan setupkörning pågår"), 409);
	}
	set_time_limit(300);
	$arguments = SETUP_MODES[$mode];
	if (!$isLocal) {
		$arguments[] = "--prod";
	}
	$result = SetupRunScript($script, $arguments);
	flock($lock, LOCK_UN);
	fclose($lock);
	SetupRespond(array("script" => $script, "mode" => $mode) + $result);
?>
