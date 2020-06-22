<?php 
	require_once("helpers/database.php");
	require_once("helpers/pages.php");

		
	class Pages
	{
		private $db = null;
		private $pagesHelper = null;
		function __construct()
		{
			$this->db = new Database();			
			$this->pagesHelper = new PagesHelper();

		}
		
		public function GetPages()
		{
			$connection = $this->db->Connect();
			$sqlPagesName = sprintf("SELECT 
									*
									FROM pages");
									
			$resultGetPages = $this->db->Select($sqlPagesName);
			return $resultGetPages;
			
		}
		
		public function GetPageById($parameters)
		{
			$connection = $this->db->Connect();
			$sqlPagesName = sprintf("SELECT 
									*
									FROM pages 
									WHERE pages_id = %d", $parameters["page_id"]);		
			$resultGetPage = $this->db->Select($sqlPagesName);
			return $resultGetPage[0];
			
		}
		
		public function CreatePage($parameters)
		{
			$connection = $this->db->Connect();
			$sql = sprintf("INSERT INTO pages SET pages_name = '%s', pages_text = '%s'", $parameters["page_name"], $parameters["page_text"]);
			return $this->db->Insert($sql)[0];
		}
		
		public function UpdatePage($parameters)
		{
			$resultUpdatePage = false;
			$connection = $this->db->Connect();
			$sql = sprintf("UPDATE pages SET pages_name = '%s', pages_text = '%s' WHERE pages_id = %d", $this->db->SecureInput($parameters["page_name"]), $parameters["page_text"], $parameters["page_id"]);
			return $this->db->Update($sql)[0];
		}
	}
?>