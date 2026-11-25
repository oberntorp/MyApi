<?php
    require_once(__DIR__ . "/config.php");
    require_once(__DIR__ . "/jwt/php-jwt/src/JWTExceptionWithPayloadInterface.php");
    require_once(__DIR__ . "/jwt/php-jwt/src/BeforeValidException.php");
    require_once(__DIR__ . "/jwt/php-jwt/src/ExpiredException.php");
    require_once(__DIR__ . "/jwt/php-jwt/src/SignatureInvalidException.php");
    require_once(__DIR__ . "/jwt/php-jwt/src/Key.php");
    require_once(__DIR__ . "/jwt/php-jwt/src/JWT.php");
    use Firebase\JWT\JWT;
    use Firebase\JWT\Key;

    class Security
    {
        const JWT_ALGORITHM = "HS512";
        const SESSION_USER_ID_PART = 1;

        private $token = array(
            "iss" => "TärnöVa",
            "aud" => "TärnöVa",
            "iat" => null,
            "nbf" => null,
            "exp" => null,
            "data" => array(
                "session_id" => null,
                "token_version" => 0,
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

        public static function SessionIdFromRequest()
        {
            $header = $_SERVER["HTTP_AUTHORIZATION"] ?? $_SERVER["REDIRECT_HTTP_AUTHORIZATION"] ?? "";
            return preg_match('/^Bearer\s+(\S+)$/i', trim($header), $matches) ? $matches[1] : "";
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

        // Användar-id ur en giltig session, eller -1. Användaren måste finnas och inte vara raderad, och tokenens
        // token_version måste vara den aktuella. Tokens utan token_version (utfärdade före migreringen) räknas som version 0.
        public function GetUserIdFromSession($sessionId, $db)
        {
            $data = $this->DecodeSessionData($db->RemoveCitationMarks($sessionId));
            if (!isset($data->session_id)) {
                return -1;
            }
            $parts = explode("_", (string)$data->session_id);
            $userId = (int)($parts[self::SESSION_USER_ID_PART] ?? 0);
            if ($userId <= 0) {
                return -1;
            }
            $user = $db->Select(
                "SELECT users_id FROM users WHERE is_deleted = 0 AND users_id = ? AND users_token_version = ?",
                "ii", [$userId, (int)($data->token_version ?? 0)]
            );
            return $user[0] == -1 ? -1 : $userId;
        }

        private function IsAdmin($userId, $db)
        {
            $result = $db->Select(
                "SELECT users_id FROM users WHERE is_deleted = 0 AND users_priviledge = 'admin' AND users_id = ?",
                "i", [(int)$userId]
            );
            return $result[0] != -1;
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

        private function CreateJWT()
        {
            return JWT::encode($this->token, $this->key, self::JWT_ALGORITHM);
        }

        private function DecodeJWT($jwtToken)
        {
            return JWT::decode((string)$jwtToken, new Key($this->key, self::JWT_ALGORITHM));
        }

        public function SetJWtToken($sessionId, $tokenVersion = 0)
        {
            $timeOfLogin = time();
            $this->token["iat"] = $timeOfLogin;
            $this->token["exp"] = $timeOfLogin + (60 * 15);
            $this->token["data"]["session_id"] = $sessionId;
            $this->token["data"]["token_version"] = (int)$tokenVersion;
            return $this->CreateJWT();
        }

        // Token får användarens aktuella token_version. Ökas versionen (RevokeSessions) slutar alla äldre tokens att gälla.
        public function CreateSession($user, $db)
        {
            $sessionInput = $user["users_id"] . "_" . $this->GenerateHash($user["users_username"]);
            $clientAddress = $_SERVER["REMOTE_ADDR"] ?? "";
            return $this->SetJWtToken($clientAddress . "_" . $sessionInput, $this->TokenVersion($user["users_id"], $db)) ?: "";
        }

        private function TokenVersion($userId, $db)
        {
            $result = $db->Select("SELECT users_token_version AS version FROM users WHERE users_id = ?", "i", [(int)$userId]);
            return $result[0] == -1 ? 0 : (int)$result[0]["version"];
        }

        public function RevokeSessions($userId, $db)
        {
            $result = $db->Update(
                "UPDATE users SET users_token_version = users_token_version + 1 WHERE is_deleted = 0 AND users_id = ?",
                "i", [(int)$userId]
            );
            return $result[0] >= 0;
        }

        public function CheckVilidityJWT($jwtToken)
        {
            try
            {
                $this->DecodeJWT($jwtToken);
                return true;
            }
            catch(Exception $e)
            {
                return false;
            }
        }

        private function DecodeSessionData($jwtToken)
        {
            try {
                return $this->DecodeJWT($jwtToken)->data ?? null;
            } catch (Exception $e) {
                return null;
            }
        }

        public function GetDataOfJWT($jwtToken)
        {
            return $this->DecodeSessionData($jwtToken)->session_id ?? null;
        }

        // Returnerar true när anropet ska nekas. Samma regler som förut, men användaren slås upp i databasen
        // vid varje anrop: raderade användare nekas, och admin kontrolleras i databasen.
        public function CheckAccessRequirements($class, $db, $method, $parameters, $sessionId)
        {
            $requiresAuth = $this->RequiresAuth($class, $method);
            $hasUserParameter = key_exists("user_id", $parameters);
            if (!$requiresAuth && !$hasUserParameter) {
                return false;
            }
            $userId = $this->GetUserIdFromSession($sessionId, $db);
            if ($userId === -1) {
                return true;
            }
            if ($this->RequiresAdmin($class, $method) && !$this->IsAdmin($userId, $db)) {
                return true;
            }
            return $hasUserParameter && (int)$parameters["user_id"] !== $userId;
        }

        function CreateGuid()
        {
            if (function_exists('com_create_guid') === true)
                return trim(com_create_guid(), '{}');

            $data = openssl_random_pseudo_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
            }
        }
?>