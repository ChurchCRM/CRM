<?php

/**
 * @OA\Info(
 *     title="ChurchCRM Public API",
 *     version="__CHURCHCRM_VERSION__",
 *     description="Publicly accessible endpoints requiring no authentication. Used for self-registration, public calendar access, country/state lookups, and login.",
 *     @OA\Contact(name="ChurchCRM", email="info@churchcrm.io", url="https://churchcrm.io"),
 *     @OA\License(name="MIT", url="https://opensource.org/licenses/MIT")
 * )
 *
 * @OA\Server(
 *     url="{scheme}://{host}{basePath}",
 *     description="ChurchCRM install root. Public paths start with /api/public.",
 *     @OA\ServerVariable(serverVariable="scheme", enum={"https","http"}, default="https", description="Protocol (https for production, http for local)"),
 *     @OA\ServerVariable(serverVariable="host", default="your-server.com", description="Your ChurchCRM server hostname"),
 *     @OA\ServerVariable(serverVariable="basePath", default="", description="Install path. Empty when ChurchCRM is at the domain root, or /churchcrm for a subdirectory install.")
 * )
 * @OA\Server(url="http://localhost/churchcrm", description="Local development at /churchcrm")
 *
 * @OA\Tag(name="Utility", description="Health check and CSP reporting")
 * @OA\Tag(name="Auth", description="Login and password reset")
 * @OA\Tag(name="Registration", description="Public self-registration (requires registration to be enabled in system settings)")
 * @OA\Tag(name="Calendar", description="Public calendar access via shared token")
 * @OA\Tag(name="Lookups", description="Country and state/province reference data")
 */
