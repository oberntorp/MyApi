<?php
	require_once("api/apistart.php");
	$apiStart = new ApiStart();
	$apiStart->SetHeaders();
	/*if($apiStart->CancelCallIfNoAccess())
	{
		http_response_code(401);
		return;
	}*/
	$apiStart->StartApiCall();
	$apiStart->ReturnApiCallResult();
		
?>