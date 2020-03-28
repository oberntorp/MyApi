<?php
	class UrlMappHelper
	{
		private $debug = false;
		private $parameterPosition = null;
		private $securityHelper = null;

		function __construct()
		{
			$this->debug = $_SERVER['SERVER_NAME'] == "localhost";
			$this->parameterPosition = ($this->debug) ? 5 : 4;
			$this->securityHelper = new Security();

		}
		
		public $mappOfClassesMethods = array("classes" => 
											array("pages" => "Pages", "users" => "Users", "login" => "Login", "pump" => "Pump", "links" => "Links", "documents" => "Documents", "importantinfo" => "ImportantInfo"), 
										"methods" => 
											array(
											"pages" => 
											array("get" => "GetPageById", "getall" => "GetPages", "create" => "CreatePage", "update" => "UpdatePage"),
											"users" =>
											array("get" => "GetUserById", "create" => "CreateUser", "update" => "UpdateUser", "delete" => "DeleteUser", "addawaitingapproval" => "AddAwaitingApprovalUsers", "approveuser" => "ApproveUsers", "getuserstoapprove" => "GetUsersToApprove", "deletedeniedapproval" => "DeleteDeniedApproval"),
											"login" =>
											array("get" => "CheckLogedIn", "getid" => "GetIdUserLoggedIn", "getinfo" => "GetInfoUserLoggedIn", "login" => "CheckLoginChredentials", "logout" => "LogOut"),
											"pump" =>
											array("lends" => "GetPumpLends", "pumplenddone" => "UpdatePumpLendsEntryDone", "pumplendstatus" => "GetPumpLendStatus", "solutions" => "GetPumpSolutions", "createlends" => "CreatePumpLendsEntry", "createsolutions" => "CreatePumpSolutionsEntry"),
											"images"  => 
											array("get" => "GetImageById", "create" => "GreateImageEntry"),
											"documents" => 
											array("get" => "GetDocumentById", "getall" => "GetDocuments", "create" => "CreateDocumentEntry", "createdocumentmetaentry" => "CreateDocumentMetaEntry", "delete" => "DeleteDocument"),
											"links" =>
											array("get" => "GetLinkById", "getall" => "GetLinks", "create" => "CreateLink", "update" => "UpdateLink", "delete" => "DeleteLink"), 
											"importantinfo" =>
											array("getall" => "GetImportantInfoPosts", "create" => "CreateImportantInfoPost", "delete" => "DeleteImportantInfoPost")), 
										"queryStrings" =>
											array("pages" =>
											array("get" => array("page_id")),
											"users" =>
												array("get" => array("user_id")),
											"login" =>
												array("get" => array("session_id"),"getinfo" => array("session_id")),
											"documents" =>
											array("delete" => array("document_id"), "get" => array("document_id", "session_id"), "getall" => array()),
											"images" =>
											array("get" => array("image_id")),
											"links" =>
											array("get" => array("link_id"), "delete" => array("link_id")),
											"pump" =>
											array("pumplendstatus" => array("session_id"))),
										"postKeys" => 
											array("pages" =>
												array("create" => 
												array("page_name", "page_header", "page_paragraphs")),
											"users" => 
												array("create" =>
												array("user_firstname", "user_lastname", "user_username", "user_password", "user_email_confirmation", "user_priviledge"),
											"addawaitingapproval" =>
												array("user_username", "user_password", "user_firstname", "user_lastname", "user_email"),
											"approveuser" =>
												array("approval_of_users_id","is_approved")),
											"login" =>
												array("login" =>
												array("user_username", "user_password")),
											"pump" =>
												array("createlends" =>
												array("lends_start", "lends_lended_by", "lends_description", "session_id"),
											"createsolutions" =>
												array("solutions_created_by", "solutions_description")),
											"documents" =>
												array("create" => array("document_file"), "createdocumentmetaentry" => array("document_doc_type", "document_doc_date", "document_id", "document_name_other")),
											"images" =>
												array("create" => array("image_name", "image_src", "image_type", "image_size", "image_alt")),
											"links" =>
												array("create" => array("link_address", "link_label")),
											"importantinfo" =>
												array("create" => array("post_content", "document_id"))),
										"deleteKeys" =>
											array("links" =>
												array("delete" =>
													array("link_id")),
											"documents" =>
												array("delete" =>
													array("document_id")),
											"importantinfo" =>
												array("delete" =>
													array("post_id")),
											"users" =>
												array("delete" =>
													array("users_id"),
												"deletedeniedapproval" =>
													array("approval_of_users_id"))),
										"putKeys" =>
										array("pages" =>
											array("update" =>
												array("page_id", "page_name", "page_text")),
											"users" =>
											array("update" =>
												array("user_id", "user_username", "user_password")),
											"links" =>
											array("update" =>
												array("link_id", "link_address", "link_label")),
											"pump" =>
											array("pumplenddone" =>
												array("session_id", "lends_end"))));
		
		public function CheckParametersExist($class, $methodToCall, $requestMethod, $parametersToBeSent)
		{
			$result = array();
			$parametersShouldExist = array();
			if(array_key_exists($class, $this->mappOfClassesMethods[$this->GetParameterType($requestMethod)]) && array_key_exists($methodToCall, $this->mappOfClassesMethods[$this->GetParameterType($requestMethod)][$class]))
				$parametersShouldExist = $this->mappOfClassesMethods[$this->GetParameterType($requestMethod)][$class][$methodToCall];
			if(count($parametersShouldExist) != 0)
			{
				$isMultiDImentional = key_exists(0, $parametersToBeSent) && is_array($parametersToBeSent[0]);
				foreach($parametersShouldExist as $parameterShouldExist)
				{
					if($isMultiDImentional)
					{
						foreach($parametersToBeSent as $parameterToBeSent)
						{
							if(is_array($parameterToBeSent))
							{
								foreach($parameterToBeSent as $secondLevelKey => $secondLevelValue)
								{
									if(!key_exists($parameterShouldExist, $parameterToBeSent))
									{
										array_push($result, $parameterShouldExist);
									}
								}
							}
						}
					}
					else 
					{
						if(!key_exists($parameterShouldExist, $parametersToBeSent))
						{
							array_push($result, $parameterShouldExist);
						}
					}
				}
			}
			return $result;
		}
		
		public function CheckClassMethodExist($class, $methodToCall)
		{
			return key_exists($class, $this->mappOfClassesMethods["classes"]) && key_exists($methodToCall, $this->mappOfClassesMethods["methods"][$class]);
		}
		
		private function HasParameters($getParametersIn)
		{
			return count($getParametersIn) > 0;
		}
		
		public function GetParametersOfRequest($requestMethod, $urlParts, $class, $method, $sessionId, &$getParametersIn)
		{
			$getParameters = array_slice($urlParts, $this->parameterPosition);
			$jsonData = json_decode(file_get_contents('php://input'), true);
			$parameterType = $this->GetParameterType($requestMethod);
			$this->CollectParameters($requestMethod, $parameterType, $jsonData, $getParameters, $class, $method, $sessionId, $getParametersIn);
		}
		
		private function CollectParameters($requestMethod, $parameterType, $jsonData, $getParameters, $class, $method, $sessionId, &$getParametersIn)
		{
			$mapp = null;
			if($requestMethod == "GET" && $this->HasParameters($getParameters) || $requestMethod == "PUT" || $requestMethod == "POST")
				$mapp = $this->mappOfClassesMethods[$parameterType][$class][$method];
			if($requestMethod == "GET" || $requestMethod == "PUT" || $requestMethod == "DELETE")
			{
				if($requestMethod == "GET" || $requestMethod == "DELETE")
				{
					if($this->HasParameters($getParameters))
					{
						foreach($this->mappOfClassesMethods[$parameterType][$class][$method] as $queryStringKey => $queryString)
						{
							$getParametersIn[$queryString] = $getParameters[$queryStringKey];
						}
					}
				}
				else
				{
					$this->CollectBody($jsonData, $mapp, $this->securityHelper->RequiresAuth($class, $method), $getParametersIn);
					$nameOfIdParameter = $this->GetIdParametersClassName($class);
					if(in_array($nameOfIdParameter, $getParametersIn))
						$getParametersIn[$nameOfIdParameter] = $getParameters[0];
					else{
						if($this->HasParameters($getParameters))
						{
							$getParametersIn[$nameOfIdParameter] = $getParameters[0];
						}
						if(!key_exists("session_id", $getParametersIn))
							$getParametersIn["session_id"] = $sessionId;
					}
				}
			}
			else if($requestMethod == "POST")
			{	
				$this->CollectBody($jsonData, $mapp, $this->securityHelper->RequiresAuth($class, $method), $getParametersIn);
			}
		}

		private function GetIdParametersClassName($class)
		{
			return (substr($class, -1) === 's') ? substr($class, 0, -1)."_id" : $class ."_id";
		}

		private function AddSessionIdParameter(&$getParametegetParametersIn, $getParameters)
		{
			$getParametersIn["session_id"] = $getParameters["session_id"];
		}

		private function GetIdParametersOfPut($arrayValue)
		{
			return explode("_", $arrayValue)[1] === "id" ? $arrayValue : null;
		}

		private function CollectBody($jsonDataIn, $mappIn, $requiresAuth = false, &$getParametersIn)
		{
			$valueArrayIndex = 0;
			if(!empty($jsonDataIn))
			{
				foreach($jsonDataIn as $key => $value)
				{
					if(is_array($value))
					{
						foreach($value as $valueArrayKey => $valueArrayValue)
						{
							if(is_array($valueArrayValue))
							{
								foreach($valueArrayValue as $secondLevelKey => $secondLevelValue)
								{
									foreach($mappIn as $postKey)
									{	
										if($postKey == $secondLevelKey)
										{
											$getParametersIn[$valueArrayIndex][$postKey] = $secondLevelValue;
										}
									}
								}
							}
							else 
							{
								foreach($mappIn as $postKey)
								{	
									if($postKey == $valueArrayKey)
									{
										$getParametersIn[$valueArrayIndex][$postKey] = $valueArrayValue;
									}
								}

							}
							$valueArrayIndex++;
						}
					}
					else
					{
						foreach($mappIn as $postKey)
						{	
							if($postKey == $key)
							{
								$getParametersIn[$postKey] = $value;
							}
						}
					}
				}
			}
			foreach($_FILES as $fileKey => $file)
			{
				foreach($mappIn as $postKey)
				{	
					$getParametersIn[$postKey] = $file;
				}
			}

			//print_r($getParametersIn);
		}
		
		private function GetParameterType($requestMethod)
		{
			$parameterType =  "";
			if($requestMethod == "POST")
			{
				$parameterType = "postKeys";
			}
			else if($requestMethod == "GET")
			{
				$parameterType = "queryStrings";
			}
			else if($requestMethod == "PUT")
			{
				$parameterType = "putKeys";
			}
			else
			{
				$parameterType = "deleteKeys";
			}
			return $parameterType;
		}
	}
?>