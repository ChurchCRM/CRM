<?php

namespace ChurchCRM\Slim\Middleware\Request\Setting;


class PublicRegistrationAuthMiddleware extends BaseAuthSettingMiddleware
{

    function getSettingName()
    {
        return "bEnableSelfRegistration";
    }
}