<?php
	require_once(__DIR__ . "/config.php");

	class Database
	{
		const NOT_FOUND = "Inga uppgifter hittades";
		const GENERIC_ERROR = "Ett fel uppstod i databasen";

		private $user = "";
		private $password = "";
		private $server = "";
		private $db = "";
		private $connection = null;

		function __construct($user = null, $password = null, $server = null, $db = null)
		{
			$settings = ($user === null) ? Config::DatabaseSettings() : array("user" => $user, "password" => $password, "server" => $server, "database" => $db);
			$this->user = $settings["user"];
			$this->password = $settings["password"];
			$this->server = $settings["server"];
			$this->db = $settings["database"];
		}

		public function Connect()
		{
			if ($this->connection !== null) {
				return $this->connection;
			}
			mysqli_report(MYSQLI_REPORT_OFF);
			$connection = @new mysqli($this->server, $this->user, $this->password, $this->db);
			if ($connection->connect_error) {
				error_log("Database connection failed: " . $connection->connect_error);
				http_response_code(503);
				exit(json_encode(array("error" => "Databasen är inte tillgänglig just nu")));
			}
			$connection->set_charset("utf8");
			$this->connection = $connection;
			return $this->connection;
		}

		public function Select($sql, $types = "", $params = [])
		{
			$stmt = $this->Execute($sql, $types, $params);
			if ($stmt === false) {
				return $this->Failure();
			}
			$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
			$stmt->close();
			return count($rows) > 0 ? $rows : array(-1, self::NOT_FOUND);
		}

		public function Insert($sql, $types = "", $params = [])
		{
			$stmt = $this->Execute($sql, $types, $params);
			if ($stmt === false) {
				return $this->Failure();
			}
			$insertId = $this->connection->insert_id;
			$stmt->close();
			return $insertId != 0 ? array($insertId, "The record was inserted") : $this->Failure();
		}

		public function Update($sql, $types = "", $params = [])
		{
			return $this->AffectedRows($sql, $types, $params);
		}

		public function SoftDelete($sql, $types = "", $params = [])
		{
			return $this->AffectedRows($sql, $types, $params);
		}

		public function Remove($sql, $types = "", $params = [])
		{
			$result = $this->AffectedRows($sql, $types, $params);
			if ($result[0] !== -1) {
				$result[1] = "Data was removed";
			}
			return $result;
		}

		public function SecureInput($stringToEscape)
		{
			return ($stringToEscape === null || $stringToEscape === "") ? $stringToEscape : $this->Connect()->real_escape_string($stringToEscape);
		}

		public function Base64Encode($stringToEncode)
		{
			return base64_encode($stringToEncode);
		}

		public function Base64Decode($stringToDecode)
		{
			return base64_decode($stringToDecode);
		}

		public function UrlEncoder($stringToEncode)
		{
			return urlencode($stringToEncode);
		}

		public function UrlDecoder($stringToDecode)
		{
			return urldecode($stringToDecode);
		}

		public function RemoveCitationMarks($stringToHandle)
		{
			return (preg_match("/%22|\"/", (string)$stringToHandle)) ? preg_replace("/%22/", "", $stringToHandle) : $stringToHandle;
		}

		private function AffectedRows($sql, $types, $params)
		{
			$stmt = $this->Execute($sql, $types, $params);
			if ($stmt === false) {
				return $this->Failure();
			}
			$affectedRows = $stmt->affected_rows;
			$stmt->close();
			return array($affectedRows);
		}

		private function Execute($sql, $types, $params)
		{
			$connection = $this->Connect();
			$stmt = $connection->prepare($sql);
			if ($stmt === false) {
				error_log("Database prepare failed: " . $connection->error);
				return false;
			}
			if ($types !== "" && count($params) > 0) {
				$stmt->bind_param($types, ...$params);
			}
			if (!$stmt->execute()) {
				error_log("Database execute failed: " . $stmt->error);
				$stmt->close();
				return false;
			}
			return $stmt;
		}

		private function Failure()
		{
			return array(-1, self::GENERIC_ERROR);
		}
	}
?>
