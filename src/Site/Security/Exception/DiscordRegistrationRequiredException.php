<?php

namespace App\Site\Security\Exception;

use NeoPHP\Package\Security\Exception\CustomUserMessageAuthenticationException;

class DiscordRegistrationRequiredException extends CustomUserMessageAuthenticationException
{
    public function __construct()
    {
        parent::__construct('Choose your username to finish your registration.');
    }
}