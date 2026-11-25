<?php
	require_once("helpers/database.php");
	require_once("helpers/users.php");
	require_once("helpers/ratelimit.php");
	require_once("helpers/loginthrottle.php");

	class Users
	{
		const REGISTER_BUCKET = "register_ip";
		const MAX_REGISTER_PER_IP = 10;
		const REGISTER_WINDOW_MINUTES = 15;
		const ACCESS_TABLES = array(
			"passkeys" => "passkeys_users_id",
			"passkey_challenges" => "passkey_challenges_users_id",
			"totp_secrets" => "totp_secrets_users_id",
			"mfa_recovery" => "mfa_recovery_users_id",
			"mfa_logins" => "mfa_logins_users_id",
		);

		private $db = null;
		private $usersHelper = null;

		function __construct()
		{
			$this->db = new Database();
			$this->usersHelper = new UsersHelper($this->db);
		}
		public function GetUserById($parameters)
		{
			$connection = $this->db->Connect();
			$userId = $parameters["user_id"];

			$result = $this->db->Select("SELECT users_id, users_username, users_priviledge FROM users WHERE is_deleted = 0 AND users_id = ?", "i", [(int)$userId]);
			return $result[0];

		}

		// Varje anrop räknas, så att registreringen inte kan användas för att fylla kön eller skicka mejl i mängd.
		private function RegisterAttemptBlockedFor()
		{
			$limiter = new RateLimit($this->db);
			$address = RateLimit::ClientIp();
			$seconds = $limiter->SecondsUntilUnblocked(self::REGISTER_BUCKET, $address, self::MAX_REGISTER_PER_IP, self::REGISTER_WINDOW_MINUTES);
			if ($seconds === 0) {
				$limiter->RegisterFailure(self::REGISTER_BUCKET, $address, self::REGISTER_WINDOW_MINUTES);
			}
			return $seconds;
		}

		public function AddAwaitingApprovalUsers($params)
		{
			$seconds = $this->RegisterAttemptBlockedFor();
			if ($seconds > 0) {
				return RateLimit::TooManyAttempts($seconds);
			}
			return $this->usersHelper->AddAwaitingApprovalUsers($params);
		}

		public function ApproveUsers($params)
		{
			return $this->usersHelper->ApproveUsers($params);
		}

		public function CreateUser($parameters)
		{
			return $this->usersHelper->AddUsersToDb($parameters);
		}

		// Kräver nuvarande lösenord så att en stulen session inte kan ta över kontot. Fel lösenord räknas mot inloggningsspärren.
		public function UpdateUser($parameters)
		{
			$userId = (int)$parameters["user_id"];
			$newUserName = trim((string)$parameters["user_username"]);
			$newPassword = (string)$parameters["user_password"];

			$current = $this->db->Select("SELECT users_username, users_password FROM users WHERE is_deleted = 0 AND users_id = ?", "i", [$userId]);
			if ($current[0] == -1) {
				return UsersHelper::Failure("Användaren hittades inte");
			}
			$currentUserName = $current[0]["users_username"];

			$throttle = new LoginThrottle($this->db);
			$secondsBlocked = $throttle->SecondsUntilAllowed($currentUserName);
			if ($secondsBlocked > 0) {
				return array(0 => 0) + RateLimit::TooManyAttempts($secondsBlocked);
			}
			$currentPassword = (string)$parameters["user_password_current"];
			if ($currentPassword === "" || !$this->usersHelper->security->VerifyPassword($currentPassword, $current[0]["users_password"])) {
				$throttle->RegisterFailure($currentUserName);
				return UsersHelper::Failure("Fel nuvarande lösenord");
			}

			$error = UserValidation::FindCredentialsError($newUserName, $newPassword);
			if ($error === null && $newUserName !== $currentUserName && UserValidation::IsUsernameTaken($this->db, $newUserName)) {
				$error = "Användarnamnet används redan";
			}
			if ($error !== null) {
				return UsersHelper::Failure($error);
			}

			return $this->db->Update(
				"UPDATE users SET users_username = ?, users_password = ? WHERE users_id = ?",
				"ssi", [$newUserName, $this->usersHelper->security->HashPassword($newPassword), $userId]
			);
		}

		public function GetUsersToApprove()
		{
			return $this->usersHelper->GetInfoOfUsersToApprove();
		}

		// Routen skickar users_id. Tidigare lästes user_id, så ingen användare raderades.
		public function DeleteUser($parameters)
		{
			$userId = (int)$parameters["users_id"];
			$resultDeleteUser = $this->db->SoftDelete("UPDATE users SET is_deleted = 1 WHERE users_id = ?", "i", [$userId]);
			if ($resultDeleteUser[0] > 0) {
				$this->RemoveAccessOf($userId);
			}
			return $resultDeleteUser[0];
		}

		// Användaren är mjukt raderad, men passkeys och tvåstegsverifiering som ger åtkomst tas bort.
		private function RemoveAccessOf($userId)
		{
			foreach (self::ACCESS_TABLES as $table => $userColumn) {
				$this->db->Remove("DELETE FROM " . $table . " WHERE " . $userColumn . " = ?", "i", [$userId]);
			}
		}

		public function DeleteDeniedApproval($parameters)
		{
			return $this->usersHelper->DeleteDeniedApproval($parameters["approval_of_users_id"]);
		}
	}
?>
