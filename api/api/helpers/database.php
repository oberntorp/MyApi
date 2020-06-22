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
		
		public function Connect()
		{
			$this->connection = new mysqli($this->server, $this->user, $this->password, $this->db);
			$this->connection->set_charset("utf8");
			if($this->connection->connect_error)
			{
				die("Error connecting to database" . $this->connection->connect_error);
			}
			
			return $this->connection;
		}
		
		public function Insert($sql)
		{
			$con = $this->connect();
			$result = array();
			if($con)
			{
				$con->query($sql);
				if($con->insert_id != 0000)
				{
					$result[0] = $con->insert_id;
					$result[1] = "The record was inserted";
				}
				else
				{
					$result[0] = -1;
					$result[1] = $con->error;
				}
			}
			else
			{
				$result[0] = -1;
				$result[1] = $con->error;
			}
			return $result;
		}
		
		public function Update($sql)
		{
			$con = $this->connect();
			$result = array();
			if($con)
			{
				$con->query($sql) or die($con->error);
				$result[0] = $con->affected_rows;
			}
			else
			{
				$result[0] = -1;
				$result[1] = $con->error;
			}
			return $result;
		}
		
		public function SoftDelete($sql)
		{
			$con = $this->connect();
			$result = array();
			if($con)
			{
				$con->query($sql) or die($con->error);
				$result[0] = $con->affected_rows;
			}
			else
			{
				$result[0] = -1;
				$result[1] = $con->error;
			}
			return $result;
		}
		
		public function Select($sql)
		{
			$con = $this->connect();
			$returnResult = array();
			if($con)
			{
				$result = $con->query($sql) or die($con->error);
				if($result->num_rows > 0)
				{
					while($row = $result->fetch_array(MYSQLI_ASSOC))
					{
						$returnResult[] = $row;
					}
				}
				else
				{
					$returnResult[0] = -1;
					$returnResult[1] = "Inga uppgifter hittades";
				}
			}
			else
			{
				$result[0] = -1;
				$returnResult[1] = $con->error;
			}
			return $returnResult;
		}
		
		public function Remove($sql)
		{
			$con = $this->connect();
			$result = array();
			if($con)
			{
				$con->query($sql) or die($con->error);
				$result[0] = $con->affected_rows;
				$result[1] = "Data was removed";
			}
			else
			{
				$result[0] = -1;
				$result[0] = $con->error;
			}
			return $result;
		}
		
		public function SecureInput($stringToEscape)
		{
			return ($stringToEscape != null || $stringToEscape != "") ? $this->connection->real_escape_string($stringToEscape) : $stringToEscape;
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
		
		public function RemoveCitationMarks($stringToHandle)
		{
			return (preg_match("/%22|\"/", $stringToHandle)) ? preg_replace("/%22/", "", $stringToHandle) : $stringToHandle;
		}
	}
?>