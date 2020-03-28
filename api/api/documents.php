<?php 
	require_once("helpers/database.php");
	require_once("helpers/users.php");

	class Documents
	{
		private $db = null;
		private $filesHelper = null;
		
		function __construct()
		{
			$this->db = ($_SERVER['SERVER_NAME'] == "localhost") ? new Database("root", "", "localhost", "tarnova") : new Database("tarno_nu", "ruDGWUGAywcKeA89fAACYi83", "tarno.nu.mysql", "tarno_nu");			
			$this->filesHelper = new FilesHelper();
		}
		
		public function GetDocumentById($parameters)
		{
			$connection = $this->db->Connect();
			$documentId = $this->db->SecureInput($parameters["document_id"]);
			
			$sql = sprintf("SELECT * FROM documents WHERE is_deleted = 0 AND documents_id = %d", $documentId);
			$result = $this->db->Select($sql);
			return $this->filesHelper->GenerateFilesOutput($result[0]);
			
		}
		
		public function GetDocuments()
		{
			$connection = $this->db->Connect();
			
			$sql = sprintf("SELECT documents.documents_id AS documents_id, documents_name, documents_meta.documents_meta_doc_type AS documents_type, documents_meta.documents_meta_doc_date AS documents_date FROM documents JOIN documents_meta ON documents.documents_id = documents_meta.documents_id WHERE is_deleted = 0 ORDER BY documents_meta.documents_meta_doc_date DESC");
			$resultGetDocuments = $this->db->Select($sql);
			return $resultGetDocuments;
		}
		
		public function CreateDocumentEntry($parameters)
		{
			$result = null;
			$connection = $this->db->Connect();
			$documentFile = $parameters["document_file"];
			$documentType = $this->db->SecureInput($documentFile["type"]);
			$documentSize = $this->db->SecureInput($documentFile["size"]);
			$documentName = $this->db->SecureInput($documentFile["name"]);
			$fileRelatedErrors = $this->filesHelper->ValidateFilesInput($documentType, $documentName, $documentSize);
			if(count($fileRelatedErrors["errors"]) == 0)
			{
				$content = $this->db->SecureInput($this->filesHelper->GenerateFilesInput($documentFile["tmp_name"]));
				$sql = sprintf("INSERT INTO documents SET documents_name = '%s', documents_type = '%s', documents_size = '%s', documents_content = '%s'", $documentName, $documentType, $documentSize, $content);
				$resultCreateDocument = $this->db->Insert($sql);
			}
			$result = array("result" => (isset($resultCreateDocument)) ? $resultCreateDocument[0] : -1, "errors" => $fileRelatedErrors["errors"]);
			return $result;
		}
		
		public function CreateDocumentMetaEntry($parameters)
		{
			$result = null;
			$documentDocDate = $parameters["document_doc_date"];
			$documentDocType = $parameters["document_doc_type"];
			$documentId = $parameters["document_id"];
			$documentNameOther = $parameters["document_name_other"];
			if($documentDocType != "instruktioner")
			{
				$newDocumentName = $this->filesHelper->FilesInputFormatName($documentDocType, $documentDocDate, $documentNameOther);
				$this->UpdateDocumentName($newDocumentName, $documentId);
			}
			$sqlDocumentMeta = sprintf("INSERT INTO documents_meta SET documents_meta_doc_type = '%s', documents_meta_doc_date = '%s', documents_id = %d", $documentDocType, $documentDocDate, $documentId);
			$resultCreateDocumentMeta = $this->db->Insert($sqlDocumentMeta);
			$result = array("result" => $resultCreateDocumentMeta[0]);
			
			return $result;
		}
		
		public function DeleteDocument($parameters)
		{
			$connection = $this->db->connect();
			$documentId = $this->db->SecureInput($parameters["document_id"]);
			$isDocumentConnectedPost = $this->IsDocumentConnectedPost($documentId);
			$resultDeleteDocument = null;
			if(!$isDocumentConnectedPost)
			{
				$sql = sprintf("UPDATE documents SET is_deleted = 1 WHERE documents_id = %d", $documentId);
				$resultDeleteDocument = $this->db->SoftDelete($sql);
			}
			return ($resultDeleteDocument != null) ? $resultDeleteDocument[0] : -2;
		}
		
		private function UpdateDocumentName($documentName, $documentId)
		{
			$sqlUpdateDocumentName = sprintf("UPDATE documents SET documents_name = '%s' WHERE documents.documents_id = %d", $documentName, $documentId);
			return $this->db->Update($sqlUpdateDocumentName);
		}
		
		private function IsDocumentConnectedPost($documentId)
		{
			$sql = sprintf("SELECT important_info_posts_id FROM documents WHERE documents_id = %d", $documentId);
			return $resultConnectedPost = $this->db->Select($sql)[0]["important_info_posts_id"] != null;
		}
	}
?>