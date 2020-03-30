<?php 
	require_once("helpers/database.php");
	require_once("helpers/users.php");

	class Links
	{
		private $db = null;
		private $usersHelper = null;
		
		function __construct()
		{
			$this->db = ($_SERVER['SERVER_NAME'] == "localhost") ? new Database("root", "", "localhost", "tarnova") : new Database("", "", "", "");			
			$this->usersHelper = new UsersHelper();
		}
		public function GetLinkById($parameters)
		{
			$connection = $this->db->Connect();
			$linkId = $this->db->SecureInput($parameters["link_id"]);
			
			$sql = sprintf("SELECT * FROM links WHERE is_deleted = 0 AND links_id = %d", $linkId);
			$result = $this->db->Select($sql);
			return $result[0];
			
		}
		
		public function GetLinks()
		{
			$connection = $this->db->Connect();
			
			$sql = sprintf("SELECT links_id, links_address, links_label FROM links WHERE is_deleted = 0");
			$resultGetLinks = $this->db->Select($sql);
			return $resultGetLinks;
		}
		
		public function CreateLink($parameters)
		{
			$connection = $this->db->Connect();
			$linkAdress = $this->db->SecureInput($parameters["link_address"]);
			$linkLabel = $this->db->SecureInput($parameters["link_label"]);
			
			$sql = sprintf("INSERT INTO links SET links_address = '%s', links_label = '%s'", $linkAdress, $linkLabel);
			
			$resultCreateLink = $this->db->Insert($sql);
			return $resultCreateLink[0];
		}
		
		public function UpdateLink($parameters)
		{
			$connection = $this->db->Connect();
			$linkId = $this->db->SecureInput($parameters["link_id"]);
			$linkAdress = $this->db->SecureInput($parameters["link_address"]);
			$linkLabel = $this->db->SecureInput($parameters["link_label"]);
			
			$sql = sprintf("UPDATE links SET links_address = '%s', links_label = '%s' WHERE links_id = %d", $linkAdress, $linkLabel, $linkId);
			$resultUpdateLink = $this->db->Update($sql);
			return $resultUpdateLink[0];
		}
		
		public function DeleteLink($parameters)
		{
			$connection = $this->db->connect();
			$linkId = $this->db->SecureInput($parameters["link_id"]);
			
			$sql = sprintf("UPDATE links SET is_deleted = 1 WHERE links_id = %d", $linkId);
			$resultDeleteLink = $this->db->SoftDelete($sql);
			return $resultDeleteLink[0];
		}
	}
?>