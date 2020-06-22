<?php 
	require_once("helpers/database.php");
	require_once("helpers/pages.php");

		
	class ImportantInfo
	{
		private $db = null;
		private $pagesHelper = null;
		function __construct()
		{
			$this->db = new Database();			
			$this->pagesHelper = new PagesHelper();

		}
		
		public function GetImportantInfoPosts()
		{
			$connection = $this->db->Connect();
			$sqlImportantInfoPosts = sprintf("SELECT important_info_posts.*, documents.documents_id, documents_name, documents_meta.documents_meta_doc_type AS documents_type, documents_meta.documents_meta_doc_date AS documents_date FROM important_info_posts LEFT JOIN documents ON important_info_posts.important_info_posts_id = documents.important_info_posts_id LEFT JOIN documents_meta ON documents.documents_id = documents_meta.documents_id WHERE important_info_posts.is_deleted = 0");
									
			$resultGetInfoPosts = $this->db->Select($sqlImportantInfoPosts);
						
			return $resultGetInfoPosts;
			
		}
		
		public function GetPostById($parameters)
		{
			$connection = $this->db->Connect();
			$sqlImportantPost = sprintf("SELECT * FROM important_info_posts RIGHT JOIN documents ON important_info_posts.important_info_posts_id = documents.important_info_posts_id WHERE important_info_posts.important_info_posts_id = '%d'", $parameters["post_id"]);
									
			$resultGetPage = $this->db->Select($sqlImportantPost);
						
			return $resultGetPage[0];
			
		}
		
		public function CreateImportantInfoPost($parameters)
		{
			$connection = $this->db->Connect();
			$sql = sprintf("INSERT INTO important_info_posts SET important_info_posts_content = '%s'", $parameters["post_content"]);
			$postId = $this->db->Insert($sql);
			$postId = $postId[0];
			$resultUpdateDocument = null;
			if($postId != -1)
			{
				$sqlDocumentsUpdate = sprintf("UPDATE documents SET important_info_posts_id = %d WHERE documents_id = %d", $postId, intval($parameters["document_id"]));
				$resultUpdateDocument = $this->db->Update($sqlDocumentsUpdate);
			}
			return ($resultUpdateDocument != null) ? $postId : -1;
		}
		
		public function DeleteImportantInfoPost($parameters)
		{
			$connection = $this->db->connect();
			$postId = $this->db->SecureInput($parameters["post_id"]);
			$infoPostConnectedToDocument = $this->IsDocumentConnectedPost($postId);
			$isPostConnectedToDocument = $infoPostConnectedToDocument["connectionExists"];
			$documentId = $infoPostConnectedToDocument["id"];
			$resultDeletePost = null;
			if(!$isPostConnectedToDocument){
				$sql = sprintf("UPDATE important_info_posts SET is_deleted = 1 WHERE important_info_posts_id = %d", $postId);
				$resultDeletePost = $this->db->SoftDelete($sql);
			}
			else{
				$sql = sprintf("UPDATE documents SET important_info_posts_id = NULL WHERE documents_id = %d", $documentId);
				$this->db->Update($sql);
				$sqlPostDelete = sprintf("UPDATE important_info_posts SET is_deleted = 1 WHERE important_info_posts_id = %d", $postId);
				$resultDeletePost = $this->db->SoftDelete($sqlPostDelete);
			}
			return $resultDeletePost[0];
		}

		private function IsDocumentConnectedPost($postId)
		{
			$sql = sprintf("SELECT documents_id FROM documents WHERE important_info_posts_id = %d", $postId);
			return array("connectionExists" => $this->db->Select($sql)[0]["documents_id"] != null, "id" => $this->db->Select($sql)[0]["documents_id"]);
		}
	}
?>