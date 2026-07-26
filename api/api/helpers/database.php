<?php
	class Database
	{
		private $user = "";
		private $password = "";
		private $server = "";
		private $db = "";
		private $connection = null;

		function __construct()
		{
			$connectionString = ($_SERVER['SERVER_NAME'] == "localhost") ? "root; ; localhost; tarnova" : "; ; ; ";
			$connectionString = explode(";", $connectionString);
			$this->user = trim($connectionString[0]);
			$this->password = trim($connectionString[1]);
			$this->server = trim($connectionString[2]);
			$this->db = trim($connectionString[3]);
		}

		private function establishConnection()
		{
			if ($this->connection === null) {
				$this->connection = new mysqli($this->server, $this->user, $this->password, $this->db);
				$this->connection->set_charset("utf8");
				if ($this->connection->connect_error) {
					die("Error connecting to database: " . $this->connection->connect_error);
				}
			}
			return $this->connection;
		}

		// Kept for backward compatibility
		public function Connect()
		{
			return $this->establishConnection();
		}

		private function PrepareAndBind($con, $sql, $types, $params)
		{
			$stmt = $con->prepare($sql);
			if ($stmt === false) {
				return false;
			}
			if ($types !== "" && count($params) > 0) {
				$stmt->bind_param($types, ...$params);
			}
			return $stmt;
		}

		public function Insert($sql, $types = "", $params = [])
		{
			$con = $this->establishConnection();
			$result = array();
			if ($con) {
				$stmt = $this->PrepareAndBind($con, $sql, $types, $params);
				if ($stmt === false) {
					$result[0] = -1;
					$result[1] = $con->error;
					return $result;
				}
				$stmt->execute();
				if ($con->insert_id != 0) {
					$result[0] = $con->insert_id;
					$result[1] = "The record was inserted";
				} else {
					$result[0] = -1;
					$result[1] = $stmt->error;
				}
				$stmt->close();
			} else {
				$result[0] = -1;
				$result[1] = "Connection failed";
			}
			return $result;
		}

		public function Update($sql, $types = "", $params = [])
		{
			$con = $this->establishConnection();
			$result = array();
			if ($con) {
				$stmt = $this->PrepareAndBind($con, $sql, $types, $params);
				if ($stmt === false) {
					$result[0] = -1;
					$result[1] = $con->error;
					return $result;
				}
				$stmt->execute();
				$result[0] = $stmt->affected_rows;
				$stmt->close();
			} else {
				$result[0] = -1;
				$result[1] = "Connection failed";
			}
			return $result;
		}

		public function SoftDelete($sql, $types = "", $params = [])
		{
			$con = $this->establishConnection();
			$result = array();
			if ($con) {
				$stmt = $this->PrepareAndBind($con, $sql, $types, $params);
				if ($stmt === false) {
					$result[0] = -1;
					$result[1] = $con->error;
					return $result;
				}
				$stmt->execute();
				$result[0] = $stmt->affected_rows;
				$stmt->close();
			} else {
				$result[0] = -1;
				$result[1] = "Connection failed";
			}
			return $result;
		}

		public function Select($sql, $types = "", $params = [])
		{
			$con = $this->establishConnection();
			$returnResult = array();
			if ($con) {
				$stmt = $this->PrepareAndBind($con, $sql, $types, $params);
				if ($stmt === false) {
					$returnResult[0] = -1;
					$returnResult[1] = $con->error;
					return $returnResult;
				}
				$stmt->execute();
				$result = $stmt->get_result();
				if ($result->num_rows > 0) {
					while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
						$returnResult[] = $row;
					}
				} else {
					$returnResult[0] = -1;
					$returnResult[1] = "Inga uppgifter hittades";
				}
				$stmt->close();
			} else {
				$returnResult[0] = -1;
				$returnResult[1] = "Connection failed";
			}
			return $returnResult;
		}

		public function Remove($sql, $types = "", $params = [])
		{
			$con = $this->establishConnection();
			$result = array();
			if ($con) {
				$stmt = $this->PrepareAndBind($con, $sql, $types, $params);
				if ($stmt === false) {
					$result[0] = -1;
					$result[1] = $con->error;
					return $result;
				}
				$stmt->execute();
				$result[0] = $stmt->affected_rows;
				$result[1] = "Data was removed";
				$stmt->close();
			} else {
				$result[0] = -1;
				$result[1] = "Connection failed";
			}
			return $result;
		}

		public function RemoveCitationMarks($stringToHandle)
		{
			return (preg_match("/%22|\"/", $stringToHandle)) ? preg_replace("/%22/", "", $stringToHandle) : $stringToHandle;
		}

		public function Base64Encode($stringToEncode)
		{
			return base64_encode($stringToEncode);
		}

		public function Base64Decode($stringToDecode)
		{
			return base64_decode($stringToDecode);
		}

		public function UrlEncoder($stringToDecode)
		{
			return urlencode($stringToDecode);
		}

		public function UrlDecoder($stringToDecode)
		{
			return urldecode($stringToDecode);
		}
	}
?>
