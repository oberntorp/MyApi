<?php
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

	public function AddUsersToDb($parameters)
	{
		$connection = $this->db->Connect();
		$usersToAdd = array();
		$userFirstname = "";
		$userLastname = "";
		$userEmail = "";
		$userName = "";
		$userPass = "";
		$userPassEnq = "";
		$userPriviledge = "";
		$arrayResult = array();
		$indexOfNewUser = 0;
		foreach($parameters as $parameterKey => $parameterValue)
		{	
			if(is_array($parameterValue))
			{
				foreach($parameterValue as $key => $parameter)
				{
					if($key === "user_firstname")
						$usersToAdd[$indexOfNewUser]["userFirstname"] = $parameter;
					if($key === "user_lastname")
						$usersToAdd[$indexOfNewUser]["userLastname"] = $parameter;
					if($key === "user_email_confirmation")
						$usersToAdd[$indexOfNewUser]["userEmail"] = $parameter;
					if($key === "user_username")
						$usersToAdd[$indexOfNewUser]["userName"] = $parameter;
					if($key === "user_password")
					{
						$usersToAdd[$indexOfNewUser]["userPassEnq"] = $this->security->HashPassword($parameter);
					}
					if($key === "user_priviledge")
						$usersToAdd[$indexOfNewUser]["userPriviledge"] = $parameter;
				}
				$indexOfNewUser++;
			}
			else 
			{
				if($parameterKey === "user_firstname")
					$usersToAdd[$indexOfNewUser]["userFirstname"] = $parameterValue;
				if($parameterKey === "user_lastname")
					$usersToAdd[$indexOfNewUser]["userLastname"] = $parameterValue;
				if($parameterKey === "user_email_confirmation")
					$usersToAdd[$indexOfNewUser]["userEmail"] = $parameterValue;
				if($parameterKey === "user_username")
					$usersToAdd[$indexOfNewUser]["userName"] = $parameterValue;
				if($parameterKey === "user_password")
				{
					$usersToAdd[$indexOfNewUser]["userPassEnq"] = $this->security->HashPassword($parameterValue);
				}
				if($parameterKey === "user_priviledge")
					$usersToAdd[$indexOfNewUser]["userPriviledge"] = $parameterValue;
			}
		}
		for($i = 0; $i < count($usersToAdd); $i++)
		{
			$result = $this->db->Insert("INSERT INTO users SET users_username = ?, users_password = ?, users_priviledge = ?", "sss", [$usersToAdd[$i]["userName"], $usersToAdd[$i]["userPassEnq"], $usersToAdd[$i]["userPriviledge"]]);
			$arrayResult[] = $result;
			if($result[0] > 0)
			{
				$this->contact->SendRegistrationAlertAddedByAdmin($usersToAdd[$i]["userFirstname"] . " " . $usersToAdd[$i]["userLastname"], $usersToAdd[$i]["userName"], $usersToAdd[$i]["userEmail"]);
			}
		}

		return (count($arrayResult) > 0) ? 1 : 0;
	}

	public function AddAwaitingApprovalUsers($parameters)
	{
		$connection = $this->db->Connect();
		$userName = "";
		$userPasswordEnq = "";
		$userPriviledge = "";
		$userFirstname = "";
		$userLastname = "";
		$userEmail = "";

		$arrayResult = array();
		foreach($parameters as $key => $parameterValue)
		{
			if(is_array($parameterValue))
			{
				foreach($parameterValue as $key => $parameter)
				{
					if($key === "user_username")
						$userName = $parameter;
					if($key === "user_password")
					{
						$userPasswordEnq = $this->security->HashPassword($parameter);
					}
					if($key === "user_firstname")
						$userFirstname = $parameter;
					if($key === "user_lastname")
						$userLastname = $parameter;
					if($key === "user_email")
						$userEmail = $parameter;
				}
			}
			else 
			{
					if($key === "user_username")
							$userName = $parameterValue;
					if($key === "user_password")
					{
						$userPasswordEnq = $this->security->HashPassword($parameterValue);
					}
					if($key === "user_firstname")
						$userFirstname = $parameterValue;
					if($key === "user_lastname")
						$userLastname = $parameterValue;
					if($key === "user_email")
						$userEmail = $parameterValue;
			}
		}

		$result = $this->db->Insert("INSERT INTO approval_of_users SET approval_of_users_first_name = ?, approval_of_users_last_name = ?, approval_of_users_email = ?, approval_of_users_username = ?, approval_of_users_password = ?", "sssss", [$userFirstname, $userLastname, $userEmail, $userName, $userPasswordEnq]);
		$arrayResult[] = $result;
		if($result[0] > 0)
		{
			$contact = new Contact();
			$contact->SendNewUserAwaitsApprovalAlert($userFirstname . " " . $userLastname, $userName);
			$contact->SendRegistrationAlert($userFirstname . " " . $userLastname, $userName, $userEmail);
		}

		return (count($arrayResult) > 0) ? 1 : 0;
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