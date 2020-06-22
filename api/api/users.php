<?php 
	require_once("helpers/database.php");
	require_once("helpers/users.php");

	class Users
	{
		private $db = null;
		private $usersHelper = null;
		
		function __construct()
		{
			$this->db = new Database();			
			$this->usersHelper = new UsersHelper($this->db);
		}
		public function GetUserById($parameters)
		{
			$connection = $this->db->Connect();
			$userId = $this->db->SecureInput($parameters["user_id"]);
			
			$sql = sprintf("SELECT users_id, users_username, users_priviledge FROM users WHERE is_deleted = 0 AND users_id = %d", $userId);
			$result = $this->db->Select($sql);
			return $result[0];
			
		}
		
		public function AddAwaitingApprovalUsers($params)
		{
			return $this->usersHelper->AddAwaitingApprovalUsers($params);
		}

		public function ApproveUsers($params)
		{
			return $this->usersHelper->ApproveUsers($params);
		}

		public function CreateUser($parameters)
		{
			return $this->usersHelper->AddUsersToDb($parameters);
		}
		
		public function UpdateUser($parameters)
		{
			$connection = $this->db->Connect();
			$userId = $this->db->SecureInput($parameters["user_id"]);
			$userName = $this->db->SecureInput($parameters["user_username"]);
			$userPassEnq = $this->usersHelper->security->GenerateHash($this->db->SecureInput($parameters["user_password"]));
			
			$sql = sprintf("UPDATE users SET users_username = '%s', users_password = '%s' WHERE users_id = %d", $userName, $userPassEnq, $userId);
			$resultUpdateUser = $this->db->Update($sql);
			return $resultUpdateUser;
		}

		public function GetUsersToApprove()
		{
			return $this->usersHelper->GetInfoOfUsersToApprove();
		}
		
		public function DeleteUser($parameters)
		{
			$connection = $this->db->connect();
			$userId = $this->db->SecureInput($parameters["user_id"]);
			
			$sql = sprintf("UPDATE users SET is_deleted = 1 WHERE users_id = %d", $userId);
			$resultDeleteUser = $this->db->SoftDelete($sql);
			return $resultDeleteUser[0];
		}

		public function DeleteDeniedApproval($parameters)
		{
			return $this->usersHelper->DeleteDeniedApproval($parameters["approval_of_users_id"]);
		}
	}
?>