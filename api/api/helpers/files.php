<?php
class FilesHelper
{
	private $allowedFileTypes = array("pdf" => "application/pdf");
	private $allowedFileSize = 5242880;
	
	public function GenerateFilesOutput($fileInfo)
	{
		$fileName = $fileInfo["documents_name"];
		$fileType = $fileInfo["documents_type"];
		$fileSize = $fileInfo["documents_size"];
		$fileContent = $fileInfo["documents_content"];
		header("Content-Type: $fileType");
		header("Content-Disposition: attachment; filename=$fileName");
		header("Content-Length: $fileSize");
		return $fileContent;
	}
	
	public function GenerateFilesInput($fileData)
	{
		return file_get_contents($fileData);
	}
	
	public function ValidateFilesInput($type, $name, $size)
	{
		$errors = array("errors" => array());
		$ext = explode(".", $name);
		$ext = $ext[1];
		if(!array_key_exists($ext,  $this->allowedFileTypes) && $this->allowedFileSize[$ext] != $type)
		{
			$errors["errors"][] = "Filtypen " . $ext . " " . "-" . " " . $this->allowedFileSize[$ext] . " " . "får inte laddas upp.";
		}
		if($size > $this->allowedFileSize)
		{
			$errors["errors"][] = "Filen är för stor" . " " . "tillåten storlek är" . " " . $this->allowedFileSize/1048576;
		}
		
		return $errors;
	}
	
	public function FilesInputFormatName($documentType, $documentDate, $nameOfOtherDocument = "")
	{
		$result = "";
		switch($documentType)
		{
			case "stadgar":
				$result = "tarno_va_stadgar" . "_" . $documentDate;
				break;
			case "forvaltningsberattelse":
				$result = "tarno_va_forvaltningsberattelse" . "_" . $documentDate;
				break;
			case "arsavgift":
				$result = "tarno_va_arsavgift" . "_" . $documentDate;
				break;
			case "arsmotesprotokoll":
				$result = "tarno_va_arsmotesprotokoll" . "_" . $documentDate;
				break;
			case "arsredovisning":
				$result = "tarno_va_arsredovisning" . "_" . $documentDate;
				break;
			case "ovrigt":
				$result = preg_replace("/\s/", "_", $nameOfOtherDocument);
		}
		return $result . ".pdf";
	}
}
?>