<?php
	require_once("helpers/security.php");
	require_once("contact.php");
	require_once("pages.php");
	require_once("users.php");
	require_once("login.php");
	require_once("pump.php");
	require_once("documents.php");
	require_once("images.php");
	require_once("importantinfo.php");
	require_once("links.php");
	require_once("helpers/urlmapp.php");

	class UrlMapp
	{
		private $requestUrl = "";
		private $requestMethod = "";
		public $urlMappHelper = null;	
		private $debug = false;
		private $classPositionUrl = null;
		private $methodPositionUrl = null;
		public $securityHelper = null;
		public $sessionId = "";
		
		function __construct($requestUrl, $requestMethod, $sessionId)
		{
			$this->requestUrl = $requestUrl;
			$this->requestMethod = $requestMethod;
			$this->debug = $_SERVER['SERVER_NAME'] == "localhost";
			$this->classPositionUrl = ($this->debug) ? 3 : 2;
			$this->methodPositionUrl = ($this->debug) ? 4 : 3;
			$this->urlMappHelper = new UrlMappHelper();
			$this->securityHelper = new Security();
			$this->sessionId = $sessionId;
		}
		
		public function ExtractClassMethod()
		{
			$error = "";
			$parameters = array();
			$urlParts = explode("/", $this->requestUrl);
			$class = $urlParts[$this->classPositionUrl];
			$method = $urlParts[$this->methodPositionUrl];
			if($this->urlMappHelper->CheckClassMethodExist($class, $method))
			{
				$this->urlMappHelper->GetParametersOfRequest($this->requestMethod, $urlParts, $class, $method, $this->sessionId, $parameters);
			}
			else
			{
				$error = "The url was wrong";
			}
			return array($class, $method, $parameters, $error);
		}
		
		public function CheckParametersExist($classIn, $methodIn, $parametersIn = array())
		{		
			return $this->urlMappHelper->CheckParametersExist($classIn, $methodIn, $this->requestMethod, $parametersIn);
		}
		
		public function CollectParameterErrors($parameterErrors)
		{
			$errors = array();
			foreach($parameterErrors as $parameterError)
			{
				array_push($errors, sprintf("Parameter %s saknas", $parameterError));
			}
			return $errors;
		}
		
		public function CallMethod($classToCallIn, $methodToCallIn, $parametersIn)
		{	
			$result = null;
			if($this->requestMethod != "OPTIONS")
			{
				if(count($parametersIn) > 0)
				{
					$result = $classToCallIn->$methodToCallIn($parametersIn);
				}
				else
				{
					$result = $classToCallIn->$methodToCallIn();
				}
			}
			return $result;
		}
		
		public function GetClass($classIn)
		{
			return new $this->urlMappHelper->mappOfClassesMethods["classes"][$classIn];
		}
		
		public function GetMethod($classIn, $methodIn)
		{
			return $this->urlMappHelper->mappOfClassesMethods["methods"][$classIn][$methodIn];
		}
	}
?>