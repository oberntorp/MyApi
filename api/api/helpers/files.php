<?php
class FilesHelper
{
	private $allowedFileTypes = array("pdf" => "application/pdf");
	private $allowedFileSize = 5242880;

	public function GetMaxUploadSizeBytes()
	{
		$limits = array_filter([
			$this->allowedFileSize,
			$this->IniSizeToBytes(ini_get('upload_max_filesize')),
			$this->IniSizeToBytes(ini_get('post_max_size')),
		], function ($value) {
			return $value > 0;
		});
		return count($limits) > 0 ? min($limits) : $this->allowedFileSize;
	}

	private function IniSizeToBytes($sizeStr)
	{
		$sizeStr = trim((string)$sizeStr);
		if ($sizeStr === '') {
			return 0;
		}
		$unit = strtolower(substr($sizeStr, -1));
		$value = (float)$sizeStr;
		switch ($unit) {
			case 'g':
				$value *= 1024;
				// fall through
			case 'm':
				$value *= 1024;
				// fall through
			case 'k':
				$value *= 1024;
		}
		return (int)$value;
	}

	public function GenerateFilesOutput($fileInfo)
	{
		$fileName = $fileInfo["documents_name"];
		$fileType = $fileInfo["documents_type"];
		$fileSize = $fileInfo["documents_size"];
		$fileContent = $fileInfo["documents_content"];
		header("Content-Type: $fileType");
		header("Content-Disposition: attachment; filename=\"" . str_replace(array("\"", "\\", "\r", "\n"), "_", $fileName) . "\"; filename*=UTF-8''" . rawurlencode($fileName));
		header("X-Content-Type-Options: nosniff");
		header("Content-Length: $fileSize");
		return $fileContent;
	}
	
	// Läser bara en fil som PHP själv har tagit emot som uppladdning, aldrig en sökväg eller URL från anropet.
	public function GenerateFilesInput($uploadedPath)
	{
		if (!is_string($uploadedPath) || $uploadedPath === "" || !is_uploaded_file($uploadedPath)) {
			return false;
		}
		return file_get_contents($uploadedPath);
	}
	
	public function ValidateFilesInput($type, $name, $size, $tmpPath = null)
	{
		$errors = array("errors" => array());
		$extension = strtolower(pathinfo((string)$name, PATHINFO_EXTENSION));
		if (!array_key_exists($extension, $this->allowedFileTypes) || !$this->HasAllowedContentType($extension, $type, $tmpPath)) {
			$errors["errors"][] = "Filtypen " . $extension . " får inte laddas upp.";
		}
		if ($size > $this->allowedFileSize) {
			$errors["errors"][] = "Filen är för stor, tillåten storlek är " . $this->allowedFileSize / 1048576 . " MB.";
		}
		return $errors;
	}

	private function HasAllowedContentType($extension, $type, $tmpPath)
	{
		$detectedType = ($tmpPath !== null && is_file($tmpPath)) ? (new finfo(FILEINFO_MIME_TYPE))->file($tmpPath) : $type;
		return in_array($detectedType, (array)$this->allowedFileTypes[$extension], true);
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