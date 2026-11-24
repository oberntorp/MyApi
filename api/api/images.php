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
			$imageId = $parameters["image_id"];
			
			$result = $this->db->Select("SELECT * FROM images WHERE is_deleted = 0 AND images_id = ?", "i", [(int)$imageId]);
			return $this->filesHelper->GenerateFilesOutput($result[0]);
			
		}
		
		public function CreateImageEntry($parameters)
		{
			$connection = $this->db->Connect();
			$imageName = $parameters["image_name"];
			$imageContent = $this->filesHelper->GenerateFilesInput($parameters["image_content"]);
			$imageType = $parameters["image_type"];
			$imageSize = $parameters["image_size"];
			$imageAlt = $parameters["image_alt"];
			
			$resultCreateImage = $this->db->Insert("INSERT INTO images SET images_name = ?, images_content = ?, images_type = ?, images_size = ?, images_alt = ?", "sssis", [$imageName, $imageContent, $imageType, (int)$imageSize, $imageAlt]);
			return $resultCreateImage;
		}
		
		public function DeleteImage($parameters)
		{
			$connection = $this->db->connect();
			$userId = $parameters["image_id"];
			
			$resultDeleteUser = $this->db->SoftDelete("UPDATE images SET is_deleted = 1 WHERE images_id = ?", "i", [(int)$userId]);
			return $resultDeleteUser;
		}
	}
?>