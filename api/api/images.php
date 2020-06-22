<?php 
	require_once("helpers/database.php");
	require_once("helpers/files.php");

	class Images
	{
		private $db = null;
		private $filesHelper = null;
		
		function __construct()
		{
			$this->db = new Database();			
			$this->filesHelper = new FilesHelper();
		}
		public function GetImageById($parameters)
		{
			$connection = $this->db->Connect();
			$imageId = $this->db->SecureInput($parameters["image_id"]);
			
			$sql = sprintf("SELECT * FROM images WHERE is_deleted = 0 AND images_id = %d", $imageId);
			$result = $this->db->Select($sql);
			return $this->filesHelper->GenerateFilesOutput($result[0]);
			
		}
		
		public function CreateImageEntry($parameters)
		{
			$connection = $this->db->Connect();
			$imageName = $this->db->SecureInput($parameters["image_name"]);
			$imageContent = $this->db->SecureInput($this->filesHelper->GenerateFilesInput($parameters["image_content"]));
			$imageType = $this->db->SecureInput($parameters["image_type"]);
			$imageSize = $this->db->SecureInput($parameters["image_size"]);
			$imageAlt = $this->db->SecureInput($parameters["image_alt"]);
			
			$sql = sprintf("INSERT INTO images SET images_name = '%s', images_content = '%s', images_type = '%s', images_size = %d, images_alt = '%s'", $imageName, $imageContent, $imageType, $imageSize, $imageAlt);
			$resultCreateImage = $this->db->Insert($sql);
			return $resultCreateImage;
		}
		
		public function DeleteImage($parameters)
		{
			$connection = $this->db->connect();
			$userId = $this->db->SecureInput($parameters["image_id"]);
			
			$sql = sprintf("UPDATE images SET is_deleted = 1 WHERE images_id = %d", $userId);
			$resultDeleteUser = $this->db->SoftDelete($sql);
			return $resultDeleteUser;
		}
	}
?>