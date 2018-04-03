<?php

namespace ChurchCRM;

class SessionUser
{
    /**
     * @return User
     */
    public static function getUser()
    {
        return $_SESSION['user'];
    }

    public static function isAdmin()
    {
        return self::getUser()->isAdmin();
    }

    public static function getId()
    {
        return self::getUser()->getId();
    }
}
