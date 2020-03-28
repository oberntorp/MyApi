<?php
class LoginHelper
{
	public function SetSession($sessionId)
	{
		$_SESSION["loggedIn"] = $sessionId;
		return $this->GetSession($sessionId);
	}
	
	public function CheckSession($sessionId)
	{
		return (isset($_SESSION["loggedIn"]) && $sessionId == $_SESSION["loggedIn"]) ? true : false;
	}
	
	public function DestroySession()
	{
		session_unset();
		session_destroy();
	}
	
	public function GetSession($sessionId)
	{
		return $_SESSION["loggedIn"];
	}
}
?>