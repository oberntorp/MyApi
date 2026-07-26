<?php
	require_once("api/apistart.php");
	$apiStart = new ApiStart();
	$apiStart->SetHeaders();
	if ($apiStart->CancelCallIfNoAccess()) {
		http_response_code(401);
		echo json_encode(["error" => "Unauthorized"]);
		exit;
	}
	$apiStart->StartApiCall();
	$apiStart->ReturnApiCallResult();
		
?>