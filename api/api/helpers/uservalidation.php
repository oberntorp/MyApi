<?php
	// Samma regler som i Passwordmngr (UserApproval): användarnamn och lösenord kontrolleras alltid på servern.
	class UserValidation
	{
		const MAX_USERNAME_CHARS = 100;
		const MIN_PASSWORD_CHARS = 8;

		public static function FindUsernameError($username)
		{
			$username = trim((string)$username);
			if ($username === "") {
				return "Användarnamn krävs";
			}
			if (mb_strlen($username) > self::MAX_USERNAME_CHARS) {
				return "Användarnamnet är för långt";
			}
			if (!preg_match('/^[\p{L}\p{N} .\-_@]+$/u', $username)) {
				return "Användarnamnet får bara innehålla bokstäver, siffror, mellanslag och .-_@";
			}
			return null;
		}

		public static function FindPasswordError($password)
		{
			if (mb_strlen((string)$password) < self::MIN_PASSWORD_CHARS) {
				return "Lösenordet måste ha minst " . self::MIN_PASSWORD_CHARS . " tecken";
			}
			return null;
		}

		public static function FindCredentialsError($username, $password)
		{
			return self::FindUsernameError($username) ?? self::FindPasswordError($password);
		}

		public static function IsUsernameTaken($db, $username)
		{
			$username = trim((string)$username);
			$existing = $db->Select("SELECT users_id FROM users WHERE is_deleted = 0 AND users_username = ?", "s", [$username]);
			if ($existing[0] != -1) {
				return true;
			}
			$pending = $db->Select("SELECT approval_of_users_id FROM approval_of_users WHERE approval_of_users_username = ?", "s", [$username]);
			return $pending[0] != -1;
		}
	}
?>
