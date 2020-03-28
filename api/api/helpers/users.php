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
						$usersToAdd[$indexOfNewUser]["userFirstname"] = $this->db->SecureInput($parameter);
					if($key === "user_lastname")
						$usersToAdd[$indexOfNewUser]["userLastname"] = $this->db->SecureInput($parameter);
					if($key === "user_email_confirmation")
						$usersToAdd[$indexOfNewUser]["userEmail"] = $this->db->SecureInput($parameter);
					if($key === "user_username")
						$usersToAdd[$indexOfNewUser]["userName"] = $this->db->SecureInput($parameter);
					if($key === "user_password")
					{
						$usersToAdd[$indexOfNewUser]["userPass"] = $this->db->SecureInput($parameter);
						$usersToAdd[$indexOfNewUser]["userPassEnq"] = $this->security->GenerateHash($userPass);
					}
					if($key === "user_priviledge")
						$usersToAdd[$indexOfNewUser]["userPriviledge"] = $this->db->SecureInput($parameter);
				}
				$indexOfNewUser++;
			}
			else 
			{
				if($parameterKey === "user_firstname")
					$usersToAdd[$indexOfNewUser]["userFirstname"] = $this->db->SecureInput($parameterValue);
				if($parameterKey === "user_lastname")
					$usersToAdd[$indexOfNewUser]["userLastname"] = $this->db->SecureInput($parameterValue);
				if($parameterKey === "user_email_confirmation")
					$usersToAdd[$indexOfNewUser]["userEmail"] = $this->db->SecureInput($parameterValue);
				if($parameterKey === "user_username")
					$usersToAdd[$indexOfNewUser]["userName"] = $this->db->SecureInput($parameterValue);
				if($parameterKey === "user_password")
				{
					$usersToAdd[$indexOfNewUser]["userPass"] = $this->db->SecureInput($parameterValue);
					$usersToAdd[$indexOfNewUser]["userPassEnq"] = $this->security->GenerateHash($userPass);
				}
				if($parameterKey === "user_priviledge")
					$usersToAdd[$indexOfNewUser]["userPriviledge"] = $this->db->SecureInput($parameterValue);
				
				$indexOfNewUser++;
			}
		}
		for($i = 0; $i < count($usersToAdd); $i++)
		{
			$sql = sprintf("INSERT INTO users SET users_username = '%s', users_password = '%s', users_priviledge = '%s'", $usersToAdd[$i]["userName"], $usersToAdd[$i]["userPassEnq"], $usersToAdd[$i]["userPriviledge"]);
			$result = $this->db->Insert($sql);
			$arrayResult[] = $result;
			if($result[0] > 0)
			{
				$this->contact->SendRegistrationAlertAddedByAdmin($usersToAdd[$i]["userFirstname"] . " " . $usersToAdd[$i]["userLastname"], $usersToAdd[$i]["userName"], $usersToAdd[$i]["userPass"], $usersToAdd[$i]["userEmail"]);
			}
		}

		return (count($arrayResult) > 0) ? 1 : 0;
	}

	public function AddAwaitingApprovalUsers($parameters)
	{
		$connection = $this->db->Connect();
		$userName = "";
		$userPasswordEnq = "";
		$userPassword = "";
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
						$userName = $this->db->SecureInput($parameter);
					if($key === "user_password")
					{
						$userPasswordEnq = $this->security->GenerateHash($this->db->SecureInput($parameter));
						$userPassword = $this->db->SecureInput($parameter);
					}
					if($key === "user_firstname")
						$userFirstname = $this->db->SecureInput($parameter);
					if($key === "user_lastname")
						$userLastname = $this->db->SecureInput($parameter);
					if($key === "user_email")
						$userEmail = $this->db->SecureInput($parameter);
				}
			}
			else 
			{
					if($key === "user_username")
							$userName = $this->db->SecureInput($parameterValue);
					if($key === "user_password")
					{
						$userPasswordEnq = $this->security->GenerateHash($this->db->SecureInput($parameterValue));
						$userPassword = $this->db->SecureInput($parameterValue);
					}
					if($key === "user_firstname")
						$userFirstname = $this->db->SecureInput($parameterValue);
					if($key === "user_lastname")
						$userLastname = $this->db->SecureInput($parameterValue);
					if($key === "user_email")
						$userEmail = $this->db->SecureInput($parameterValue);
			}
		}

		$sql = sprintf("INSERT INTO approval_of_users SET approval_of_users_first_name = '%s', approval_of_users_last_name = '%s', approval_of_users_email = '%s', approval_of_users_username = '%s', approval_of_users_password = '%s'", $userFirstname, $userLastname, $userEmail, $userName, $userPasswordEnq);
		$result = $this->db->Insert($sql);
		$arrayResult[] = $result;
		if($result[0] > 0)
		{
			$contact = new Contact();
			$contact->SendNewUserAwaitsApprovalAlert($userFirstname . " " . $userLastname, $userName);
			$contact->SendRegistrationAlert($userFirstname . " " . $userLastname, $userName, $userPassword, $userEmail);
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
					$sql = sprintf("INSERT INTO users (users_username, users_password) SELECT approval_of_users_username, approval_of_users_password FROM approval_of_users WHERE approval_of_users_id = %d", $idToApprove);
					$resultOfInsert = $this->db->Insert($sql);

					if($resultOfInsert[0] > 0)
					{
						$contact = new Contact();
						$contact->SendNewUserApprovedAlert($realName, $email, $userName);
						$sqlRemove = sprintf("DELETE FROM approval_of_users WHERE approval_of_users_id = %d", $idToApprove);
						$this->db->Remove($sqlRemove);
					}
					$arrayResult[] = $resultOfInsert;
				}
				else 
				{
					$sqlUpdate = sprintf("UPDATE approval_of_users SET approval_of_users_is_denied = 1 WHERE approval_of_users_id = %d", $idToApprove);
					$this->db->Update($sqlUpdate);
					$nonApprovals[] = $idToApprove;
				}

			}
		}
		return (count($nonApprovals) > 0 || count($arrayResult) === count($parameters)) ? 1 : 0;
	}

	private function GetInfoOfApprovedUser($approvalId)
	{
		$sql = sprintf("SELECT approval_of_users_first_name, approval_of_users_last_name, approval_of_users_username, approval_of_users_email FROM approval_of_users WHERE approval_of_users_id = %d", $approvalId);
		$result = $this->db->Select($sql)[0];
		return array($result["approval_of_users_first_name"] . " " . $result["approval_of_users_last_name"], $result["approval_of_users_email"], $result["approval_of_users_username"]);
	}

	public function GetInfoOfUsersToApprove()
	{
		$sql = sprintf("SELECT approval_of_users_id, approval_of_users_first_name, approval_of_users_last_name, approval_of_users_username, approval_of_users_is_denied FROM approval_of_users");
		return $this->db->Select($sql);
	}

	public function DeleteDeniedApproval($approvalId)
	{
		$sql = sprintf("DELETE FROM approval_of_users WHERE approval_of_users_id = %d", $approvalId);
		return $this->db->Remove($sql)[0];
	}
}
?>