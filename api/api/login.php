<?php
require_once("helpers/users.php");
require_once("helpers/database.php");
require_once("helpers/login.php");
require_once("users.php");
require_once("helpers/security.php");
require_once("helpers/loginthrottle.php");
require_once("contact.php");

class Login
{
	private $loginHelper = null;
	private $db = null;
	private $usersHelper = null;
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
		$userName = (string)$parameters["user_username"];
		$throttle = new LoginThrottle($this->db);
		$secondsBlocked = $throttle->SecondsUntilAllowed($userName);
		if ($secondsBlocked > 0) {
			return array("session_id" => "") + RateLimit::TooManyAttempts($secondsBlocked);
		}
		$user = $this->FindUserByCredentials($userName, (string)$parameters["user_password"]);
		if ($user === null) {
			$throttle->RegisterFailure($userName);
			return array("session_id" => "");
		}
		$throttle->RegisterSuccess($userName);
		return array("session_id" => $this->CreateSession($user));
	}

	// Ett tomt lösenord godtas aldrig, även om en gammal hash råkar vara hashen av den tomma strängen.
	private function FindUserByCredentials($userName, $password)
	{
		if ($password === "") {
			return null;
		}
		$users = $this->db->Select(
			"SELECT users_id, users_username, users_password FROM users WHERE is_deleted = 0 AND users_username = ?",
			"s", [$userName]
		);
		if ($users[0] == -1) {
			return null;
		}
		foreach ($users as $user) {
			if ($this->security->VerifyPassword($password, $user["users_password"])) {
				$this->UpgradePasswordHash($user, $password);
				return $user;
			}
		}
		return null;
	}

	private function UpgradePasswordHash($user, $password)
	{
		if ($this->security->NeedsRehash($user["users_password"])) {
			$this->db->Update(
				"UPDATE users SET users_password = ? WHERE users_id = ?",
				"si", [$this->security->HashPassword($password), (int)$user["users_id"]]
			);
		}
	}

	private function CreateSession($user)
	{
		$sessionInput = $user["users_id"] . "_" . $this->security->GenerateHash($user["users_username"]);
		$clientAddress = $_SERVER["REMOTE_ADDR"] ?? "";
		return $this->security->SetJWtToken($clientAddress . "_" . $sessionInput) ?: "";
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
