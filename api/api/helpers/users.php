<?php
require_once(__DIR__ . "/uservalidation.php");

class UsersHelper
{
	private $db = null;
	public $security = null;
	private $contact = null;

	function __construct($dataBase = null)
	{
		$this->db = $dataBase;
		$this->security = new Security();
		$this->contact = new Contact();
	}

	// Vid fel: {"0": 0, "error": "..."}. Klienterna jämför svaret med 1, så ett fel räknas fortfarande som misslyckat.
	public static function Failure($message)
	{
		return array(0 => 0, "error" => $message);
	}

	public function AddUsersToDb($parameters)
	{
		$usersToAdd = array();
		$indexOfNewUser = 0;
		foreach($parameters as $parameterKey => $parameterValue)
		{
			if(is_array($parameterValue))
			{
				$usersToAdd[$indexOfNewUser] = $this->ReadNewUser($parameterValue);
				$indexOfNewUser++;
			}
			else
			{
				$usersToAdd[$indexOfNewUser] = array_merge($usersToAdd[$indexOfNewUser] ?? array(), $this->ReadNewUser(array($parameterKey => $parameterValue)));
			}
		}
		$usersToAdd = array_values(array_filter($usersToAdd, function ($user) {
			return count($user) > 0;
		}));

		$validationError = $this->FindNewUsersError($usersToAdd);
		if($validationError !== null)
			return self::Failure($validationError);

		foreach($usersToAdd as $user)
		{
			$result = $this->db->Insert("INSERT INTO users SET users_username = ?, users_password = ?, users_priviledge = ?", "sss", [trim($user["userName"]), $this->security->HashPassword($user["userPassword"]), $user["userPriviledge"] ?? "normal"]);
			if($result[0] <= 0)
				return self::Failure("Användaren kunde inte sparas");
			$this->contact->SendRegistrationAlertAddedByAdmin(($user["userFirstname"] ?? "") . " " . ($user["userLastname"] ?? ""), $user["userName"], $user["userEmail"] ?? "");
		}
		return 1;
	}

	private function ReadNewUser($fields)
	{
		$keys = array(
			"user_firstname" => "userFirstname",
			"user_lastname" => "userLastname",
			"user_email_confirmation" => "userEmail",
			"user_username" => "userName",
			"user_password" => "userPassword",
			"user_priviledge" => "userPriviledge",
		);
		$user = array();
		foreach($fields as $key => $value)
		{
			if(isset($keys[$key]))
				$user[$keys[$key]] = $value;
		}
		return $user;
	}

	private function FindNewUsersError($usersToAdd)
	{
		if(count($usersToAdd) === 0)
			return "Inga användare angavs";
		foreach($usersToAdd as $user)
		{
			$error = $this->FindNewUserError($user["userName"] ?? "", $user["userPassword"] ?? "");
			if($error !== null)
				return $error;
		}
		return null;
	}

	public function FindNewUserError($userName, $password)
	{
		$error = UserValidation::FindCredentialsError($userName, $password);
		if($error === null && UserValidation::IsUsernameTaken($this->db, $userName))
			$error = "Användarnamnet används redan";
		return $error;
	}

	public function AddAwaitingApprovalUsers($parameters)
	{
		$userName = "";
		$userPassword = "";
		$userFirstname = "";
		$userLastname = "";
		$userEmail = "";

		foreach($parameters as $key => $parameterValue)
		{
			$fields = is_array($parameterValue) ? $parameterValue : array($key => $parameterValue);
			foreach($fields as $fieldKey => $field)
			{
				if($fieldKey === "user_username")
					$userName = trim((string)$field);
				if($fieldKey === "user_password")
					$userPassword = (string)$field;
				if($fieldKey === "user_firstname")
					$userFirstname = $field;
				if($fieldKey === "user_lastname")
					$userLastname = $field;
				if($fieldKey === "user_email")
					$userEmail = trim((string)$field);
			}
		}

		$validationError = $this->FindNewUserError($userName, $userPassword);
		if($validationError === null && !filter_var($userEmail, FILTER_VALIDATE_EMAIL))
			$validationError = "Ange en giltig epostadress";
		if($validationError !== null)
			return self::Failure($validationError);

		$result = $this->db->Insert("INSERT INTO approval_of_users SET approval_of_users_first_name = ?, approval_of_users_last_name = ?, approval_of_users_email = ?, approval_of_users_username = ?, approval_of_users_password = ?", "sssss", [$userFirstname, $userLastname, $userEmail, $userName, $this->security->HashPassword($userPassword)]);
		if($result[0] > 0)
		{
			$contact = new Contact();
			$contact->SendNewUserAwaitsApprovalAlert($userFirstname . " " . $userLastname, $userName);
			$contact->SendRegistrationAlert($userFirstname . " " . $userLastname, $userName, $userEmail);
		}

		return ($result[0] > 0) ? 1 : self::Failure("Registreringen kunde inte sparas");
	}

	public function ApproveUsers($parameters)
	{
		$nonApprovals = array();
		$idToApprove = 0;
		$isApproved = false;
		foreach($parameters as $parameterValue)
		{	
			foreach($parameterValue as $key => $parameter)
			{
				if($key === "approval_of_users_id")
					$idToApprove = $parameter;
				if($key === "is_approved")
					$isApproved = $parameter;
				list($realName, $email, $userName) = $this->GetInfoOfApprovedUser($idToApprove);
				if($isApproved == 1)
				{
					$resultOfInsert = $this->db->Insert("INSERT INTO users (users_username, users_password) SELECT approval_of_users_username, approval_of_users_password FROM approval_of_users WHERE approval_of_users_id = ?", "i", [(int)$idToApprove]);

					if($resultOfInsert[0] > 0)
					{
						$contact = new Contact();
						$contact->SendNewUserApprovedAlert($realName, $email, $userName);
						$this->db->Remove("DELETE FROM approval_of_users WHERE approval_of_users_id = ?", "i", [(int)$idToApprove]);
					}
					$arrayResult[] = $resultOfInsert;
				}
				else 
				{
					$this->db->Update("UPDATE approval_of_users SET approval_of_users_is_denied = 1 WHERE approval_of_users_id = ?", "i", [(int)$idToApprove]);
					$nonApprovals[] = $idToApprove;
				}

			}
		}
		return (count($nonApprovals) > 0 || count($arrayResult) === count($parameters)) ? 1 : 0;
	}

	private function GetInfoOfApprovedUser($approvalId)
	{
		$result = $this->db->Select("SELECT approval_of_users_first_name, approval_of_users_last_name, approval_of_users_username, approval_of_users_email FROM approval_of_users WHERE approval_of_users_id = ?", "i", [(int)$approvalId])[0];
		return array($result["approval_of_users_first_name"] . " " . $result["approval_of_users_last_name"], $result["approval_of_users_email"], $result["approval_of_users_username"]);
	}

	public function GetInfoOfUsersToApprove()
	{
		return $this->db->Select("SELECT approval_of_users_id, approval_of_users_first_name, approval_of_users_last_name, approval_of_users_username, approval_of_users_is_denied FROM approval_of_users");
	}

	public function DeleteDeniedApproval($approvalId)
	{
		return $this->db->Remove("DELETE FROM approval_of_users WHERE approval_of_users_id = ?", "i", [(int)$approvalId])[0];
	}
}
?>