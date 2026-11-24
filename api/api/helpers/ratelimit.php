<?php
	require_once(__DIR__ . "/config.php");

	class RateLimit
	{
		private $db = null;

		function __construct($db)
		{
			$this->db = $db;
		}

		public static function ClientIp()
		{
			$header = Config::Secrets()["client_ip_header"] ?? "";
			if (is_string($header) && $header !== "") {
				$value = $_SERVER["HTTP_" . strtoupper(str_replace("-", "_", $header))] ?? "";
				$address = trim(explode(",", $value)[0]);
				if (filter_var($address, FILTER_VALIDATE_IP)) {
					return self::NormalizeIp($address);
				}
			}
			return self::NormalizeIp($_SERVER["REMOTE_ADDR"] ?? "unknown");
		}

		public static function NormalizeIp($address)
		{
			$address = trim((string)$address);
			return stripos($address, "::ffff:") === 0 ? substr($address, 7) : $address;
		}

		public function SecondsUntilUnblocked($bucket, $identifier, $maxFailures, $windowMinutes)
		{
			$row = $this->db->Select(
				"SELECT rate_limit_attempts AS attempts,
				TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), DATE_ADD(rate_limit_window_start, INTERVAL ? MINUTE)) AS seconds
				FROM rate_limit WHERE rate_limit_key = ?",
				"is", [(int)$windowMinutes, $this->Key($bucket, $identifier)]
			);
			if ($row[0] == -1) {
				return 0;
			}
			return ((int)$row[0]["attempts"] >= $maxFailures && (int)$row[0]["seconds"] > 0) ? (int)$row[0]["seconds"] : 0;
		}

		public function RegisterFailure($bucket, $identifier, $windowMinutes)
		{
			$this->db->Update("DELETE FROM rate_limit WHERE rate_limit_window_start < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)");
			$this->db->Update(
				"INSERT INTO rate_limit (rate_limit_key, rate_limit_attempts, rate_limit_window_start) VALUES (?, 1, UTC_TIMESTAMP())
				ON DUPLICATE KEY UPDATE
				rate_limit_attempts = IF(rate_limit_window_start < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? MINUTE), 1, rate_limit_attempts + 1),
				rate_limit_window_start = IF(rate_limit_window_start < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? MINUTE), UTC_TIMESTAMP(), rate_limit_window_start)",
				"sii", [$this->Key($bucket, $identifier), (int)$windowMinutes, (int)$windowMinutes]
			);
		}

		public function Clear($bucket, $identifier)
		{
			$this->db->Update("DELETE FROM rate_limit WHERE rate_limit_key = ?", "s", [$this->Key($bucket, $identifier)]);
		}

		public static function TooManyAttempts($seconds)
		{
			$minutes = (int)ceil($seconds / 60);
			http_response_code(429);
			header("Retry-After: " . $seconds);
			return array(
				"error" => "För många försök. Försök igen om " . $minutes . ($minutes === 1 ? " minut." : " minuter."),
				"rate_limited" => true,
				"retry_after_minutes" => $minutes,
			);
		}

		private function Key($bucket, $identifier)
		{
			return hash("sha256", $bucket . "|" . mb_strtolower((string)$identifier));
		}
	}
?>
