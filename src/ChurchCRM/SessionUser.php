<?php

namespace ChurchCRM;

class SessionUser
{

    public static function isActive()
    {
        return isset($_SESSION['user']);
    }

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
