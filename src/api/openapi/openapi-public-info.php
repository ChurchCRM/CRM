<?php

/**
 * @OA\Info(
 *     title="ChurchCRM Public API",
 *     version="7.0.1",
 *     description="Publicly accessible endpoints requiring no authentication. Used for self-registration, public calendar access, country/state lookups, and login.",
 *     @OA\Contact(name="ChurchCRM", email="info@churchcrm.io", url="https://churchcrm.io"),
 *     @OA\License(name="MIT", url="https://opensource.org/licenses/MIT")
 * )
 *
 * @OA\Server(
 *     url="{scheme}://{host}/api",
 *     description="Self-hosted ChurchCRM instance",
 *     @OA\ServerVariable(serverVariable="scheme", enum={"https","http"}, default="https", description="Protocol (https for production, http for local)"),
 *     @OA\ServerVariable(serverVariable="host", default="your-server.com", description="Your ChurchCRM server hostname")
 * )
 * @OA\Server(url="http://localhost/churchcrm/api", description="Local development (localhost)")
 *
 * @OA\Tag(name="Utility", description="Health check and CSP reporting")
 * @OA\Tag(name="Auth", description="Login and password reset")
 * @OA\Tag(name="Registration", description="Public self-registration (requires registration to be enabled in system settings)")
 * @OA\Tag(name="Calendar", description="Public calendar access via shared token")
 * @OA\Tag(name="Lookups", description="Country and state/province reference data")
 */
