<?php 
	require_once("helpers/database.php");
	require_once("login.php");

	class Pump
	{
		private $db = null;
		private $login = null;
		function __construct()
		{
			$this->db = ($_SERVER['SERVER_NAME'] == "localhost") ? new Database("root", "", "localhost", "tarnova") : new Database("tarno_nu", "ruDGWUGAywcKeA89fAACYi83", "tarno.nu.mysql", "tarno_nu");			
			$this->login = new Login();
		}
		
		public function GetPumpLends()
		{
			$connection = $this->db->Connect();
			
			$sql = sprintf("SELECT * FROM pump_lends");
			$result = $this->db->Select($sql);
			return $result;
			
		}
		
		public function GetPumpSolutions()
		{
			$connection = $this->db->Connect();
			
			$sql = sprintf("SELECT * FROM pump_solutions");
			$result = $this->db->Select($sql);
			return $result;
			
		}
		
		public function CreatePumpLendsEntry($parameters)
		{
			$connection = $this->db->Connect();
			$lend_start = $this->db->SecureInput($parameters["lends_start"]);
			$lended_by = $this->db->SecureInput($parameters["lends_lended_by"]);
			$lend_description = $this->db->SecureInput($parameters["lends_description"]);
			$lends_user_id = $this->login->GetIdUserLoggedIn(Array("session_id" => $parameters["session_id"]));
			
			$sql = sprintf("INSERT INTO pump_lends SET pump_lends_lended_by = '%s', pump_lends_description = '%s', pump_lends_start = '%s', pump_lends_users_id = %d", $lended_by, $lend_description, $lend_start, $lends_user_id);
			$resultCreateLend = $this->db->Insert($sql);
			return $resultCreateLend[0];
		}

		public function UpdatePumpLendsEntryDone($parameters)
		{
			$connection = $this->db->Connect();
			$lend_end = $this->db->SecureInput($parameters["lends_end"]);
			$lends_user_id = $this->login->GetIdUserLoggedIn(array("session_id" => $parameters["session_id"]));
			
			$sql = sprintf("UPDATE pump_lends SET pump_lends_end = '%s' WHERE pump_lends_users_id = %d AND pump_lends_end IS NULL", $lend_end, $lends_user_id);
			$resultUpdateLendDone = $this->db->Update($sql);
			return $resultUpdateLendDone[0];
		}

		public function GetPumpLendStatus($parameters)
		{
			$connection = $this->db->Connect();
			$lends_user_id = $this->login->GetIdUserLoggedIn(array("session_id" => $parameters["session_id"]));
			
			$sql = sprintf("SELECT COUNT(pump_lends_id) AS lended, pump_lends_users_id FROM pump_lends WHERE pump_lends_end IS NULL GROUP BY pump_lends_users_id");
			$resultLendStatus = $this->db->SELECT($sql);

			return array("is_lended" => (int)$resultLendStatus[0]["lended"]  > 0, "lended_by_logged_in_user" => $resultLendStatus[0]["pump_lends_users_id"] == $lends_user_id);
		}
		
		public function CreatePumpSolutionsEntry($parameters)
		{
			$connection = $this->db->Connect();
			$solutionCreatedBy = $this->db->SecureInput($parameters["solutions_created_by"]);
			$solutionDescription = $this->db->SecureInput($parameters["solutions_description"]);
			
			$sql = sprintf("INSERT INTO pump_solutions SET pump_solutions_created_by = '%s', pump_solutions_description = '%s'", $solutionCreatedBy, $solutionDescription);
			$resultCreateSolution = $this->db->Insert($sql);
			return $resultCreateSolution[0];
		}
	}
?>