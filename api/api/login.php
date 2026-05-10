<?php
require_once("helpers/users.php");
require_once("helpers/database.php");
require_once("helpers/login.php");
require_once("users.php");
require_once("helpers/security.php");
require_once("contact.php");

class Login
{
	private $loginHelper = null;
	private $db = null;
	private $usersHelper = null;
	private $debug = true;
	private $users = null;
	private $security = null;
	private $contact = null;

	function __construct()
	{
		$this->loginHelper = new LoginHelper();
		$this->db = new Database();
		$this->usersHelper = new UsersHelper();
		$this->users = new Users();
		$this->security = new Security();
		$this->contact = new Contact();
	}

	public function CheckLoginChredentials($parameters)
	{
		$enqUserName = $parameters["user_username"];
		$enqUserPass = $this->usersHelper->security->GenerateHash($parameters["user_password"]);
		$result = $this->db->Select(
			"SELECT * FROM users WHERE is_deleted = 0 AND users_username = ? AND users_password = ?",
			"ss", [$enqUserName, $enqUserPass]
		);
		if ($result[0] != -1) {
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
		return ($parameters["session_id"] !== "null") ? $this->security->CheckVilidityJWT($this->db->RemoveCitationMarks($parameters["session_id"])) : false;
	}

	public function GetIdUserLoggedIn($parameters)
	{
		$sessionData = explode("_", $this->security->GetDataOfJWT($this->db->RemoveCitationMarks($parameters["session_id"])));
		return count($sessionData) > 1 ? (int)$sessionData[1] : -1;
	}

	public function GetInfoUserLoggedIn($parameters)
	{
		$userId = $this->GetIdUserLoggedIn(array("session_id" => $this->db->RemoveCitationMarks($parameters["session_id"])));
		return $this->users->GetUserById(array("user_id" => $userId));
	}
}
?>
