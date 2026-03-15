<?php

/**
 * @OA\Info(
 *     title="ChurchCRM Private API",
 *     version="7.0.1",
 *     description="Authenticated REST API for ChurchCRM. All endpoints require an API key passed via the x-api-key header. Obtain your API key from Profile → API Key inside the application, or via POST /public/user/login. Many endpoints require specific role permissions beyond basic authentication.",
 *     @OA\Contact(name="ChurchCRM", email="info@churchcrm.io", url="https://churchcrm.io"),
 *     @OA\License(name="MIT", url="https://opensource.org/licenses/MIT")
 * )
 *
 * @OA\Server(
 *     url="{scheme}://{host}/api",
 *     description="Self-hosted ChurchCRM API (/api)",
 *     @OA\ServerVariable(serverVariable="scheme", enum={"https","http"}, default="https", description="Protocol (https for production, http for local)"),
 *     @OA\ServerVariable(serverVariable="host", default="your-server.com", description="Your ChurchCRM server hostname")
 * )
 * @OA\Server(
 *     url="{scheme}://{host}/admin",
 *     description="Self-hosted ChurchCRM Admin (/admin)",
 *     @OA\ServerVariable(serverVariable="scheme", enum={"https","http"}, default="https", description="Protocol (https for production, http for local)"),
 *     @OA\ServerVariable(serverVariable="host", default="your-server.com", description="Your ChurchCRM server hostname")
 * )
 * @OA\Server(
 *     url="{scheme}://{host}",
 *     description="Self-hosted ChurchCRM root (for /kiosk and /plugins)",
 *     @OA\ServerVariable(serverVariable="scheme", enum={"https","http"}, default="https", description="Protocol (https for production, http for local)"),
 *     @OA\ServerVariable(serverVariable="host", default="your-server.com", description="Your ChurchCRM server hostname")
 * )
 *
 * @OA\Server(url="http://localhost/churchcrm/api", description="Local development — API (/api)")
 * @OA\Server(url="http://localhost/churchcrm/admin", description="Local development — Admin (/admin)")
 * @OA\Server(url="http://localhost/churchcrm", description="Local development — root (for /kiosk and /plugins)")
 *
 * @OA\SecurityScheme(
 *     securityScheme="ApiKeyAuth",
 *     type="apiKey",
 *     in="header",
 *     name="x-api-key",
 *     description="API key obtained from Profile → API Key in ChurchCRM, or via POST /public/user/login"
 * )
 *
 * @OA\Tag(name="Calendar", description="Calendar and event management")
 * @OA\Tag(name="People", description="Person records")
 * @OA\Tag(name="Families", description="Family records")
 * @OA\Tag(name="Groups", description="Group management")
 * @OA\Tag(name="Properties", description="Custom person and family properties")
 * @OA\Tag(name="Finance", description="Deposits and payments (Finance role required)")
 * @OA\Tag(name="Users", description="User settings and API key management")
 * @OA\Tag(name="2FA", description="Two-factor authentication setup")
 * @OA\Tag(name="System", description="System configuration and notifications")
 * @OA\Tag(name="Admin", description="Administration operations (Admin role required)")
 * @OA\Tag(name="Cart", description="Selection cart for bulk operations")
 * @OA\Tag(name="Search", description="Global search")
 * @OA\Tag(name="Map", description="Geographic map data")
 * @OA\Tag(name="Kiosk", description="Kiosk device management (Admin role required)")
 * @OA\Tag(name="Plugins", description="Plugin management (Admin role required)")
 */
