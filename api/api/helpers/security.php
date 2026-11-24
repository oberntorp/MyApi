<?php
    require_once(__DIR__ . "/config.php");
    require_once("jwt/php-jwt/src/BeforeValidException.php");
    require_once("jwt/php-jwt/src/ExpiredException.php");
    require_once("jwt/php-jwt/src/SignatureInvalidException.php");
    require_once("jwt/php-jwt/src/JWT.php");
    use Firebase\JWT\JWT;

    class Security
    {
        private $token = array(
            "iss" => "TärnöVa",
            "aud" => "TärnöVa",
            "iat" => null,
            "nbf" => null,
            "exp" => null,
            "data" => array(
                "session_id" => null
            )
         );
        private $key = "";
        private $salt = "";
        private $methodsRequiringAuth = array(
            "pages" => 
                array("getall" => "GetPages", "create" => "CreatePage", "update" => "UpdatePage"),
            "users" =>
                array("get" => "GetUserById", "create" => "CreateUser", "update" => "UpdateUser", "delete" => "DeleteUser", "approveuser" => "ApproveUsers", "getuserstoapprove" => "GetUsersToApprove", "deletedeniedapproval" => "DeleteDeniedApproval", "update" => "UpdateUser"),
            "login" =>
                array("getid" => "GetIdUserLoggedIn", "getinfo" => "GetInfoUserLoggedIn"),
            "pump" =>
                array("lends" => "GetPumpLends", "pumplenddone" => "UpdatePumpLendsEntryDone", "pumplendstatus" => "GetPumpLendStatus", "solutions" => "GetPumpSolutions", "createlends" => "CreatePumpLendsEntry", "createsolutions" => "CreatePumpSolutionsEntry"),
            "images"  => 
                array("get" => "GetImageById", "create" => "GreateImageEntry"),
            "documents" => 
                array("get" => "GetDocumentById", "getall" => "GetDocuments", "create" => "CreateDocumentEntry", "createdocumentmetaentry" => "CreateDocumentMetaEntry", "delete" => "DeleteDocument"),
            "links" =>
                array("create" => "CreateLink", "update" => "UpdateLink", "delete" => "DeleteLink"), 
            "importantinfo" =>
                array("getall" => "GetImportantInfoPosts", "create" => "CreateImportantInfoPost", "delete" => "DeleteImportantInfoPost"));

            private $methodsRequiringAdmin = array(
                "pages" => 
                    array("getall" => "GetPages", "create" => "CreatePage", "update" => "UpdatePage"),
                "users" =>
                    array("create" => "CreateUser", "delete" => "DeleteUser", "approveuser" => "ApproveUsers", "getuserstoapprove" => "GetUsersToApprove", "deletedeniedapproval" => "DeleteDeniedApproval"),
                "login" =>
                    array(),
                "pump" =>
                    array("pumplenddone" => "UpdatePumpLendsEntryDone"),
                "images"  => 
                    array("create" => "GreateImageEntry"),
                "documents" => 
                    array("getall" => "GetDocuments", "create" => "CreateDocumentEntry", "createdocumentmetaentry" => "CreateDocumentMetaEntry", "delete" => "DeleteDocument"),
                "links" =>
                    array("create" => "CreateLink", "update" => "UpdateLink", "delete" => "DeleteLink"), 
                "importantinfo" =>
                    array("create" => "CreateImportantInfoPost", "delete" => "DeleteImportantInfoPost"));

                private $methodsRequiringSpecificUser = array(
                    "pages" => 
                        array(),
                    "login" =>
                        array(),
                    "pump" =>
                        array(),
                    "users" =>
                        array("update" => "UpdateUser", "delete" => "DeleteUser"),
                    "images"  => 
                        array(),
                    "documents" => 
                        array(),
                    "links" =>
                        array(), 
                    "importantinfo" =>
                        array("delete" => "DeleteImportantInfoPost"));

        function __construct()
        {
            $secrets = Config::Secrets();
            $this->key = $secrets["jwt_key"];
            $this->salt = $secrets["password_salt"];
        }

        public function HashPassword($password)
        {
            return password_hash((string)$password, PASSWORD_DEFAULT);
        }

        public function VerifyPassword($password, $storedHash)
        {
            return is_string($storedHash) && $storedHash !== "" && password_verify((string)$password, $storedHash);
        }

        public function NeedsRehash($storedHash)
        {
            return password_needs_rehash($storedHash, PASSWORD_DEFAULT);
        }

        public function GenerateHash($unEncryptedString)
        {
            return crypt($unEncryptedString, $this->salt);
        }

        private function IsPreveledgeUserLoggedInNotAdmin($sessionId, $db)
        {
            $sessionData = ($this->CheckVilidityJWT($db->RemoveCitationMarks($sessionId))) ? explode("_", $this->GetDataOfJWT($db->RemoveCitationMarks($sessionId))) : null;
            $userId = (int)$sessionData[1];
            $result = $db->Select(
                "SELECT users_priviledge FROM users WHERE is_deleted = 0 AND users_id = ?",
                "i", [$userId]
            );
            return $result[0]["users_priviledge"] !== "admin";
        }

        public function RequiresAuth($class, $method)
        {
            return !isset($this->methodsRequiringAuth[$class]) || key_exists($method, $this->methodsRequiringAuth[$class]);
        }

        private function RequiresAdmin($class, $method)
        {
            return isset($this->methodsRequiringAdmin[$class]) && key_exists($method, $this->methodsRequiringAdmin[$class]);
        }

        private function RequiresSpecificUser($class, $method)
        {
            return isset($this->methodsRequiringSpecificUser[$class]) && key_exists($method, $this->methodsRequiringSpecificUser[$class]);
        }

        private function IsRighgtUserNotLoggedIn($sessionId, $userId, $db)
        {
            $sessionData = ($this->CheckVilidityJWT($db->RemoveCitationMarks($sessionId))) ? explode("_", $this->GetDataOfJWT($db->RemoveCitationMarks($sessionId))) : null;			
            return (int)$sessionData[1] !== (int)$userId;
        }

        private function CreateJWT()
        {
            return JWT::encode($this->token, $this->key, "HS512");
        }

        public function SetJWtToken($sessionId)
        {
            $timeOfLogin = time();
            $this->token["iat"] = $timeOfLogin;
            $this->token["exp"] = $timeOfLogin + (60 * 15);
            $this->token["data"]["session_id"] = $sessionId;
            return $this->CreateJWT();
        }

        public function CheckVilidityJWT($jwtToken)
        {
            try
            {
                JWT::decode($jwtToken, $this->key, array('HS512'));
                return true; 
            } 
            catch(Exception $e)
            {
                return false;        
            }
        }

        public function GetDataOfJWT($jwtToken)
        {
            try {
                $decodedData = JWT::decode($jwtToken, $this->key, array('HS512'));
                if ($decodedData != null)
                    return $decodedData->data->session_id;
                else
                    return null;
            } catch (Exception $e) {
                return null;
            }
        }

        public function CheckAccessRequirements($class, $db, $method, $parameters, $sessionId)
        {
            return (key_exists("user_id", $parameters)) ? ((($this->RequiresAuth($class, $method) && !$this->CheckVilidityJWT($db->RemoveCitationMarks($sessionId))
                    || ($this->RequiresAdmin($class, $method) && $this->IsPreveledgeUserLoggedInNotAdmin($sessionId, $db)) 
                    || $this->IsRighgtUserNotLoggedIn($sessionId, $parameters["user_id"], $db)))) : (($this->RequiresAuth($class, $method) && !$this->CheckVilidityJWT($db->RemoveCitationMarks($sessionId))) 
                    || ($this->RequiresAdmin($class, $method) && $this->IsPreveledgeUserLoggedInNotAdmin($sessionId, $db)));        
                
        }
    }
?>