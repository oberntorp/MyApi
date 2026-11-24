<?php 
	require_once("helpers/database.php");
	require_once("helpers/users.php");

	class Links
	{
		private $db = null;
		private $usersHelper = null;
		
		function __construct()
		{
			$this->db = new Database();			
			$this->usersHelper = new UsersHelper();
		}
		public function GetLinkById($parameters)
		{
			$connection = $this->db->Connect();
			$linkId = $parameters["link_id"];
			
			$result = $this->db->Select("SELECT * FROM links WHERE is_deleted = 0 AND links_id = ?", "i", [(int)$linkId]);
			return $result[0];
			
		}
		
		public function GetLinks()
		{
			$connection = $this->db->Connect();
			
			$resultGetLinks = $this->db->Select("SELECT links_id, links_address, links_label FROM links WHERE is_deleted = 0");
			return $resultGetLinks;
		}
		
		public function CreateLink($parameters)
		{
			$connection = $this->db->Connect();
			$linkAdress = $parameters["link_address"];
			$linkLabel = $parameters["link_label"];
			
			$resultCreateLink = $this->db->Insert("INSERT INTO links SET links_address = ?, links_label = ?", "ss", [$linkAdress, $linkLabel]);
			return $resultCreateLink[0];
		}
		
		public function UpdateLink($parameters)
		{
			$connection = $this->db->Connect();
			$linkId = $parameters["link_id"];
			$linkAdress = $parameters["link_address"];
			$linkLabel = $parameters["link_label"];
			
			$resultUpdateLink = $this->db->Update("UPDATE links SET links_address = ?, links_label = ? WHERE links_id = ?", "ssi", [$linkAdress, $linkLabel, (int)$linkId]);
			return $resultUpdateLink[0];
		}
		
		public function DeleteLink($parameters)
		{
			$connection = $this->db->connect();
			$linkId = $parameters["link_id"];
			
			$resultDeleteLink = $this->db->SoftDelete("UPDATE links SET is_deleted = 1 WHERE links_id = ?", "i", [(int)$linkId]);
			return $resultDeleteLink[0];
		}
	}
?>