<?php
    class Contact
    {
        private $sendEmailTo = "tarno-va@hotmail.com";
        private $header = "From:info@tarno.nu\r\nContent-type: text/plain; charset=UTF-8";
        public function SendNewUserAwaitsApprovalAlert($realNameOfNewUser, $userNameOfNewUser)
        {
            $subject = "En ny användare p&aring; tarno.nu inväntar ditt godkännande";
            $message = "Hej \n\n";
            $message .= "En ny användare inväntar ditt godkännande";
            $message .= "\n\n";
            $message .= "Namn" . " " . $realNameOfNewUser . "\n";
            $message .= "Användarnamn" . " " . $userNameOfNewUser;
            $this->SendMail($this->header, $subject, $message, $this->sendEmailTo);
        }

        public function SendRegistrationAlertAddedByAdmin($realNameOfNewUser, $userNameOfNewUser, $emailOfNewUser)
        {
            $subject = "Bekräftelse på registrering som användare på tarno.nu";
            $message = "Hej " . " " . $realNameOfNewUser . ""  . ".\n\n";
            $message .= "Detta mail är en bekräftelse på att du har registrerats som användare på tarno.nu, du kan börja använda ditt konto.";
            $message .= "\n\n";
            $message .= "Dina inloggningsuppgifter:";
            $message .= "\n\n";
            $message .= "Användarnamn:" . " " . $userNameOfNewUser . "\n";
            $message .= "Lösenord: det du valde vid registreringen, eller det du har fått av administratören.";
            $this->SendMail($this->header, $subject, $message, $emailOfNewUser);
        }

        public function SendRegistrationAlert($realNameOfNewUser, $userNameOfNewUser, $emailOfNewUser)
        {
            $subject = "Bekräftelse på registrering som användare på tarno.nu";
            $message = "Hej " . " " . $realNameOfNewUser . ""  . ".\n\n";
            $message .= "Detta mail är en bekräftelse på att du har registrerats som användare på tarno.nu, när du får ett mail om att din registrering blivit godkänd, kan du börja använda ditt konto.";
            $message .= "\n\n";
            $message .= "Dina inloggningsuppgifter:";
            $message .= "\n\n";
            $message .= "Användarnamn:" . " " . $userNameOfNewUser . "\n";
            $message .= "Lösenord: det du valde vid registreringen, eller det du har fått av administratören.";
            $this->SendMail($this->header, $subject, $message, $emailOfNewUser);
        }

        public function SendNewUserApprovedAlert($realNameOfNewUser, $emailOfNewUser, $userNameOfNewUser)
        {
            $subject = "Bekrä;ftelse på godkännande av registrering som användare på tarno.nu";
            $message = "Hej " . " " . $realNameOfNewUser . ""  . ".\n\n";
            $message .= "Detta mail är en bekräftelse på att du har blivit godkänd som användare på tarno.nu";
            $message .= "\n\n";
            $message .= "Dina inloggningsuppgifter:";
            $message .= "\n\n";
            $message .= "Användarnamn:" . " " . $userNameOfNewUser . "\n";
            $message .= "Lösenord: det du valde vid registreringen.";
            $this->SendMail($this->header, $subject, $message, $emailOfNewUser);
        }

        private function SendMail($header, $subject, $message, $sendTo = "")
        {
            $to = ($sendTo == "") ? $this->sendEmailTo : $sendTo;
            mail($to, $subject, $message, $header);
        }

    }
?>