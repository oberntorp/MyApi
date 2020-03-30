<?php
require_once("helpers/users.php");
require_once("helpers/database.php");
require_once("users.php");
require_once("helpers/security.php");
class Login
{
	private $db = null;
	private $usersHelper = null;
	private $debug = true;
	private $users = null;
	private $security = null;
	
	function __construct()
	{
		$this->db = ($_SERVER['SERVER_NAME'] == "localhost") ? new Database("root", "", "localhost", "tarnova") : new Database("", "", "", "");			
		$this->usersHelper = new UsersHelper();
		$this->users = new Users();
		$this->security = new Security();
	}
	
	public function CheckLoginChredentials($parameters)
	{
		$connection = $this->db->Connect();
		$enqUserName = $this->db->SecureInput($parameters["user_username"]);
		$enqUserPass = $this->usersHelper->security->GenerateHash($this->db->SecureInput($parameters["user_password"]));
		$sql = sprintf("SELECT * FROM users WHERE is_deleted = 0 AND users_username = '%s' AND users_password = '%s'", $enqUserName, $enqUserPass);
		$result = $this->db->Select($sql);
		if($result[0] != -1)
		{
			$sessionInput = $result[0]["users_id"] . "_" . $this->usersHelper->security->GenerateHash($result[0]["users_username"]);
			$sessionInput = (isset($sessionInput)) ? $sessionInput : "";
			$sessionIdToJWT = (!$this->debug) ? $_SERVER["REMOTE_ADDR"] . "_" . $sessionInput : "127.0.0.1" . "_" . $sessionInput;
			$sessionId = $this->security->SetJWtToken($sessionIdToJWT);
			$sessionSet = $sessionId != null;

		}
		return array("session_id" => ((isset($sessionSet) && $sessionSet) ? $sessionId : ""));
	}
	
	public function CheckLogedIn($parameters)
	{
		return $this->security->CheckVilidityJWT($this->db->RemoveCitationMarks($parameters["session_id"]));
	}
	
	public function GetIdUserLoggedIn($parameters)
	{
		$sessionData = explode("_", $this->security->GetDataOfJWT($this->db->RemoveCitationMarks($parameters["session_id"])));
		return (int)$sessionData[1];
	}
	
	public function GetInfoUserLoggedIn($parameters)
	{
		$userId = $this->GetIdUserLoggedIn(array("session_id" => $this->db->RemoveCitationMarks($parameters["session_id"])));
		return $this->users->GetUserById(array("user_id" => $userId));
	}
}
?>