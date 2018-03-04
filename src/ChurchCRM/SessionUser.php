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
        self::getUser()->isAdmin();
    }

}
