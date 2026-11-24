<?php 
	require_once("helpers/database.php");
	require_once("helpers/users.php");

	class Documents
	{
		private $db = null;
		private $filesHelper = null;
		
		function __construct()
		{
			$this->db = new Database();			
			$this->filesHelper = new FilesHelper();
		}
		
		public function GetDocumentById($parameters)
		{
			$connection = $this->db->Connect();
			$documentId = $parameters["document_id"];
			
			$result = $this->db->Select("SELECT * FROM documents WHERE is_deleted = 0 AND documents_id = ?", "i", [(int)$documentId]);
			return $this->filesHelper->GenerateFilesOutput($result[0]);
			
		}
		
		public function GetDocuments()
		{
			$connection = $this->db->Connect();

			$resultGetDocuments = $this->db->Select("SELECT documents.documents_id AS documents_id, documents_name, documents_meta.documents_meta_doc_type AS documents_type, documents_meta.documents_meta_doc_date AS documents_date FROM documents JOIN documents_meta ON documents.documents_id = documents_meta.documents_id WHERE is_deleted = 0 ORDER BY documents_meta.documents_meta_doc_date DESC");
			return $resultGetDocuments;
		}

		public function GetMaxUploadSize()
		{
			return array("maxUploadSizeBytes" => $this->filesHelper->GetMaxUploadSizeBytes());
		}

		public function CreateDocumentEntry($parameters)
		{
			$result = null;
			$connection = $this->db->Connect();
			$documentFile = $parameters["document_file"];
			$uploadError = $documentFile["error"] ?? UPLOAD_ERR_NO_FILE;
			if ($uploadError !== UPLOAD_ERR_OK) {
				$uploadErrorMessages = [
					UPLOAD_ERR_INI_SIZE   => "Filen är större än vad servern tillåter (upload_max_filesize).",
					UPLOAD_ERR_FORM_SIZE  => "Filen är större än vad formuläret tillåter.",
					UPLOAD_ERR_PARTIAL    => "Filen laddades bara upp delvis.",
					UPLOAD_ERR_NO_FILE    => "Ingen fil valdes.",
					UPLOAD_ERR_NO_TMP_DIR => "Servern saknar en temporär mapp för uppladdningar.",
					UPLOAD_ERR_CANT_WRITE => "Kunde inte skriva filen till disk.",
					UPLOAD_ERR_EXTENSION  => "En serverutökning avbröt uppladdningen.",
				];
				return array("result" => -1, "errors" => [$uploadErrorMessages[$uploadError] ?? "Uppladdningen misslyckades."]);
			}
			$documentType = $documentFile["type"];
			$documentSize = $documentFile["size"];
			$documentName = $documentFile["name"];
			$fileRelatedErrors = $this->filesHelper->ValidateFilesInput($documentType, $documentName, $documentSize, $documentFile["tmp_name"] ?? null);
			if(count($fileRelatedErrors["errors"]) == 0)
			{
				$content = $this->filesHelper->GenerateFilesInput($documentFile["tmp_name"]);
				$resultCreateDocument = $this->db->Insert("INSERT INTO documents SET documents_name = ?, documents_type = ?, documents_size = ?, documents_content = ?", "ssss", [$documentName, $documentType, $documentSize, $content]);
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
			$resultCreateDocumentMeta = $this->db->Insert("INSERT INTO documents_meta SET documents_meta_doc_type = ?, documents_meta_doc_date = ?, documents_id = ?", "ssi", [$documentDocType, $documentDocDate, (int)$documentId]);
			$result = array("result" => $resultCreateDocumentMeta[0]);
			
			return $result;
		}
		
		public function DeleteDocument($parameters)
		{
			$connection = $this->db->connect();
			$documentId = $parameters["document_id"];
			$isDocumentConnectedPost = $this->IsDocumentConnectedPost($documentId);
			$resultDeleteDocument = null;
			if(!$isDocumentConnectedPost)
			{
				$resultDeleteDocument = $this->db->SoftDelete("UPDATE documents SET is_deleted = 1 WHERE documents_id = ?", "i", [(int)$documentId]);
			}
			return ($resultDeleteDocument != null) ? $resultDeleteDocument[0] : -2;
		}
		
		private function UpdateDocumentName($documentName, $documentId)
		{
			return $this->db->Update("UPDATE documents SET documents_name = ? WHERE documents.documents_id = ?", "si", [$documentName, (int)$documentId]);
		}
		
		private function IsDocumentConnectedPost($documentId)
		{
			$sql = sprintf("SELECT important_info_posts_id FROM documents WHERE documents_id = %d", $documentId);
			return $resultConnectedPost = $this->db->Select($sql)[0]["important_info_posts_id"] != null;
		}
	}
?>