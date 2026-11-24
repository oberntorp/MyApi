<?php
	class Config
	{
		public static function DatabaseSettings()
		{
			$config = self::Load("db");
			$environment = ($_SERVER["SERVER_NAME"] == "localhost") ? "local" : $config["environment"];
			return $config[$environment];
		}

		public static function Secrets()
		{
			return self::Load("secrets");
		}

		private static function Load($name)
		{
			$file = __DIR__ . "/../config/{$name}.config.php";
			if (!is_file($file)) {
				throw new RuntimeException("config/{$name}.config.php saknas (se {$name}.config.example.php).");
			}
			return require $file;
		}
	}
?>