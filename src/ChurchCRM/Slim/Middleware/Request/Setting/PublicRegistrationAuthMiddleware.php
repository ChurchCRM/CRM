<?php

namespace ChurchCRM\Slim\Middleware\Request\Setting;

class PublicRegistrationAuthMiddleware extends BaseAuthSettingMiddleware
{
    public function getSettingName()
    {
        return 'bEnableSelfRegistration';
    }
}
