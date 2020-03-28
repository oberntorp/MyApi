<?php
require_once("api/urlmapp.php");
class ApiStart
{
    private $fileDownload = false;
    private $db = null;
    private $urlMapp = null;
    private $class = "";
    private $method = "";
    private $parameters = array();
    private $error = "";
    private $classToCall = null;
    private $methodToCall = null;
    private $errors = array();
    private $headers = null;
    private $callResult = null;

    function __construct()
    {
        $this->headers = apache_request_headers();
        $this->db = ($_SERVER['SERVER_NAME'] == "localhost") ? new Database("root", "", "localhost", "tarnova") : new Database("tarno_nu", "ruDGWUGAywcKeA89fAACYi83", "tarno.nu.mysql", "tarno_nu");
        $this->urlMapp = new UrlMapp($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD'], $this->GetSessionId());
        list($this->class, $this->method, $this->parameters, $this->error) = $this->urlMapp->ExtractClassMethod();
    }

    public function SetHeaders()
    {
        if($this->class == "documents" && $this->method == "get")
            $this->fileDownload = true;
        else
            header('Content-Type: application/json, charset=utf-8');

        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS, DELETE');
        header('Access-Control-Allow-Origin: http://localhost:4200');
        header('Access-Control-Allow-Credentials: true');
    }

    public function CancelCallIfNoAccess()
    {
        return $_SERVER["REQUEST_METHOD"] != "OPTIONS" && $this->urlMapp->securityHelper->CheckAccessRequirements($this->class, $this->db, $this->method, $this->parameters, $this->urlMapp->sessionId);
    }

    public function StartApiCall()
    {
        $this->classToCall = $this->urlMapp->GetClass($this->class);
        $this->methodToCall = $this->urlMapp->GetMethod($this->class, $this->method);
        $this->errors = $this->urlMapp->CollectParameterErrors($this->urlMapp->CheckParametersExist($this->class, $this->method, $this->parameters));
    }

    public function ReturnApiCallResult()
    {
        if($this->ErrorsExist())
            echo json_encode($this->errors);
        else
            $this->callResult = $this->urlMapp->CallMethod($this->classToCall, $this->methodToCall, $this->parameters);
        if($this->HasErrorsOccuredWithRequest())
            http_response_code(204);
        if($_SERVER["REQUEST_METHOD"] == "OPTIONS")
            http_response_code(200);
        if($this->fileDownload)
            echo $this->callResult;
        if(!$this->fileDownload)
            echo json_encode($this->callResult);
    }

    private function ErrorsExist()
    {
        return count($this->errors) > 0;
    }

    private function HasErrorsOccuredWithRequest()
    {
        return $_SERVER["REQUEST_METHOD"] != "OPTIONS" && isset($this->callResult[0]) && $this->callResult[0] == -1;
    }

    private function GetSessionId()
    {
        $result = "";
        if(key_exists("Authorization", $this->parameters))
            $result = $this->parameters["session_id"];
        else
            $result = (key_exists("Authorization", $this->headers)) ? explode(' ', $this->headers["Authorization"])[1] : "";
        return $result;
    }
}
?>