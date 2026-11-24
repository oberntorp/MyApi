<?php
	require_once(__DIR__ . "/ratelimit.php");

	class LoginThrottle
	{
		const IP_BUCKET = "login_ip";
		const USER_BUCKET = "login_user";
		const MAX_FAILED_PER_IP = 10;
		const MAX_FAILED_PER_USER = 10;
		const WINDOW_MINUTES = 15;

		private $limiter = null;
		private $address = "";

		function __construct($db)
		{
			$this->limiter = new RateLimit($db);
			$this->address = RateLimit::ClientIp();
		}

		public function SecondsUntilAllowed($userName)
		{
			return max(
				$this->limiter->SecondsUntilUnblocked(self::IP_BUCKET, $this->address, self::MAX_FAILED_PER_IP, self::WINDOW_MINUTES),
				$this->limiter->SecondsUntilUnblocked(self::USER_BUCKET, $userName, self::MAX_FAILED_PER_USER, self::WINDOW_MINUTES)
			);
		}

		public function RegisterFailure($userName)
		{
			$this->limiter->RegisterFailure(self::IP_BUCKET, $this->address, self::WINDOW_MINUTES);
			$this->limiter->RegisterFailure(self::USER_BUCKET, $userName, self::WINDOW_MINUTES);
		}

		public function RegisterSuccess($userName)
		{
			$this->limiter->Clear(self::USER_BUCKET, $userName);
		}
	}
?>
