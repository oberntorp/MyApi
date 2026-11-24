<?php 
	require_once("helpers/database.php");
	require_once("login.php");

	class Pump
	{
		private $db = null;
		private $login = null;
		function __construct()
		{
			$this->db = new Database();			
			$this->login = new Login();
		}
		
		public function GetPumpLends()
		{
			$connection = $this->db->Connect();
			
			$result = $this->db->Select("SELECT * FROM pump_lends");
			return $result;
			
		}
		
		public function GetPumpSolutions()
		{
			$connection = $this->db->Connect();
			
			$result = $this->db->Select("SELECT * FROM pump_solutions");
			return $result;
			
		}
		
		public function CreatePumpLendsEntry($parameters)
		{
			$connection = $this->db->Connect();
			$lend_start = $parameters["lends_start"];
			$lended_by = $parameters["lends_lended_by"];
			$lend_description = $parameters["lends_description"];
			$lends_user_id = $this->login->GetIdUserLoggedIn(Array("session_id" => $parameters["session_id"]));
			
			$resultCreateLend = $this->db->Insert("INSERT INTO pump_lends SET pump_lends_lended_by = ?, pump_lends_description = ?, pump_lends_start = ?, pump_lends_users_id = ?", "sssi", [$lended_by, $lend_description, $lend_start, (int)$lends_user_id]);
			return $resultCreateLend[0];
		}

		public function UpdatePumpLendsEntryDone($parameters)
		{
			$connection = $this->db->Connect();
			$lend_end = $parameters["lends_end"];
			$lends_user_id = $this->login->GetIdUserLoggedIn(array("session_id" => $parameters["session_id"]));
			
			$resultUpdateLendDone = $this->db->Update("UPDATE pump_lends SET pump_lends_end = ? WHERE pump_lends_users_id = ? AND pump_lends_end IS NULL", "si", [$lend_end, (int)$lends_user_id]);
			return $resultUpdateLendDone[0];
		}

		public function GetPumpLendStatus($parameters)
		{
			$connection = $this->db->Connect();
			$lends_user_id = $this->login->GetIdUserLoggedIn(array("session_id" => $parameters["session_id"]));
			
			$resultLendStatus = $this->db->SELECT("SELECT COUNT(pump_lends_id) AS lended, pump_lends_users_id FROM pump_lends WHERE pump_lends_end IS NULL GROUP BY pump_lends_users_id");

			return array("is_lended" => (int)$resultLendStatus[0]["lended"]  > 0, "lended_by_logged_in_user" => $resultLendStatus[0]["pump_lends_users_id"] == $lends_user_id);
		}
		
		public function CreatePumpSolutionsEntry($parameters)
		{
			$connection = $this->db->Connect();
			$solutionCreatedBy = $parameters["solutions_created_by"];
			$solutionDescription = $parameters["solutions_description"];
			
			$resultCreateSolution = $this->db->Insert("INSERT INTO pump_solutions SET pump_solutions_created_by = ?, pump_solutions_description = ?", "ss", [$solutionCreatedBy, $solutionDescription]);
			return $resultCreateSolution[0];
		}
	}
?>