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
			$userId = $parameters["user_id"];
			
			$result = $this->db->Select("SELECT users_id, users_username, users_priviledge FROM users WHERE is_deleted = 0 AND users_id = ?", "i", [(int)$userId]);
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
			$userId = $parameters["user_id"];
			$userName = $parameters["user_username"];
			$userPassEnq = $this->usersHelper->security->HashPassword($parameters["user_password"]);
			
			$resultUpdateUser = $this->db->Update("UPDATE users SET users_username = ?, users_password = ? WHERE users_id = ?", "ssi", [$userName, $userPassEnq, (int)$userId]);
			return $resultUpdateUser;
		}

		public function GetUsersToApprove()
		{
			return $this->usersHelper->GetInfoOfUsersToApprove();
		}
		
		public function DeleteUser($parameters)
		{
			$connection = $this->db->connect();
			$userId = $parameters["user_id"];
			
			$resultDeleteUser = $this->db->SoftDelete("UPDATE users SET is_deleted = 1 WHERE users_id = ?", "i", [(int)$userId]);
			return $resultDeleteUser[0];
		}

		public function DeleteDeniedApproval($parameters)
		{
			return $this->usersHelper->DeleteDeniedApproval($parameters["approval_of_users_id"]);
		}
	}
?>