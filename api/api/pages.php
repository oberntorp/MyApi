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
			$resultGetPages = $this->db->Select("SELECT 
									*
									FROM pages");
			return $resultGetPages;
			
		}
		
		public function GetPageById($parameters)
		{
			$connection = $this->db->Connect();
			$resultGetPage = $this->db->Select("SELECT 
									*
									FROM pages 
									WHERE pages_id = ?", "i", [(int)$parameters["page_id"]]);
			return $resultGetPage[0];
			
		}
		
		public function CreatePage($parameters)
		{
			$connection = $this->db->Connect();
			return $this->db->Insert("INSERT INTO pages SET pages_name = ?, pages_text = ?", "ss", [$parameters["page_name"], $parameters["page_text"]])[0];
		}
		
		public function UpdatePage($parameters)
		{
			$resultUpdatePage = false;
			$connection = $this->db->Connect();
			return $this->db->Update("UPDATE pages SET pages_name = ?, pages_text = ? WHERE pages_id = ?", "ssi", [$parameters["page_name"], $parameters["page_text"], (int)$parameters["page_id"]])[0];
		}
	}
?>