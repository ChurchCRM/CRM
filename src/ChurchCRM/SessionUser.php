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
        if (self::isActive()) {
            return self::getUser()->isAdmin();
        } else {
            return false;
        }
    }

    public static function getId()
    {
        if (self::isActive()) {
            return self::getUser()->getId();
        } else {
            return 0;
        }
    }
}
