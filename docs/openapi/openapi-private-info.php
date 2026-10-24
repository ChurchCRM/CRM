<?php

/**
 * @OA\Info(
 *     title="ChurchCRM Private API",
 *     version="__CHURCHCRM_VERSION__",
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
 * @OA\Tag(name="Volunteer", description="Volunteer Management v2 (requires the sVolunteerVersion rollout state to include v2)")
 */

/**
 * @OA\Schema(
 *     schema="Person",
 *     type="object",
 *     description="A person record as returned by GET /person/{personId} (Person::exportTo('JSON') plus computed fields). Keys use the Propel PHP names. Related objects (e.g. Family) are embedded when the person has them.",
 *     @OA\Property(property="Id", type="integer", example=42),
 *     @OA\Property(property="Title", type="string", nullable=true, example="Mr."),
 *     @OA\Property(property="FirstName", type="string", example="John"),
 *     @OA\Property(property="MiddleName", type="string", nullable=true),
 *     @OA\Property(property="LastName", type="string", example="Doe"),
 *     @OA\Property(property="Suffix", type="string", nullable=true),
 *     @OA\Property(property="Address1", type="string", nullable=true),
 *     @OA\Property(property="Address2", type="string", nullable=true),
 *     @OA\Property(property="City", type="string", nullable=true),
 *     @OA\Property(property="State", type="string", nullable=true),
 *     @OA\Property(property="Zip", type="string", nullable=true),
 *     @OA\Property(property="Country", type="string", nullable=true),
 *     @OA\Property(property="HomePhone", type="string", nullable=true),
 *     @OA\Property(property="WorkPhone", type="string", nullable=true),
 *     @OA\Property(property="CellPhone", type="string", nullable=true),
 *     @OA\Property(property="Email", type="string", nullable=true),
 *     @OA\Property(property="WorkEmail", type="string", nullable=true),
 *     @OA\Property(property="BirthMonth", type="integer", description="0 when unknown"),
 *     @OA\Property(property="BirthDay", type="integer", description="0 when unknown"),
 *     @OA\Property(property="BirthYear", type="integer", nullable=true),
 *     @OA\Property(property="MembershipDate", type="string", format="date", nullable=true),
 *     @OA\Property(property="DateDeceased", type="string", format="date", nullable=true),
 *     @OA\Property(property="Gender", type="integer", description="0 = unknown, 1 = male, 2 = female"),
 *     @OA\Property(property="FmrId", type="integer", description="Family role ID"),
 *     @OA\Property(property="ClsId", type="integer", description="Classification ID"),
 *     @OA\Property(property="FamId", type="integer", description="Family ID; 0 when the person has no family"),
 *     @OA\Property(property="Envelope", type="integer", nullable=true),
 *     @OA\Property(property="DateLastEdited", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="DateEntered", type="string", format="date-time"),
 *     @OA\Property(property="EnteredBy", type="integer"),
 *     @OA\Property(property="EditedBy", type="integer", nullable=true),
 *     @OA\Property(property="FriendDate", type="string", format="date", nullable=true),
 *     @OA\Property(property="Flags", type="integer"),
 *     @OA\Property(property="Facebook", type="string", nullable=true),
 *     @OA\Property(property="Twitter", type="string", nullable=true),
 *     @OA\Property(property="LinkedIn", type="string", nullable=true),
 *     @OA\Property(property="DateDeactivated", type="string", format="date", nullable=true, description="Empty/null for an active person"),
 *     @OA\Property(property="NeedsReview", type="boolean"),
 *     @OA\Property(property="Address", type="string", description="Computed one-line address (falls back to the family address)"),
 *     @OA\Property(property="FullName", type="string", description="Computed display name"),
 *     @OA\Property(property="HasPhoto", type="boolean", description="True when a photo has been uploaded"),
 *     @OA\Property(property="Family", ref="#/components/schemas/Family", description="Embedded family, present when FamId > 0"),
 *     @OA\Property(property="singlePersonCustom", type="object", additionalProperties=true, description="Custom field values the caller is allowed to see, keyed by custom field")
 * )
 *
 * @OA\Schema(
 *     schema="Family",
 *     type="object",
 *     description="A family record as returned by GET /family/{familyId} (Family::toArray()). Keys use the Propel PHP names. ScanCheck, ScanCredit and Envelope are only returned to users with the Finance role.",
 *     @OA\Property(property="Id", type="integer", example=7),
 *     @OA\Property(property="Name", type="string", example="Doe"),
 *     @OA\Property(property="Address1", type="string", nullable=true),
 *     @OA\Property(property="Address2", type="string", nullable=true),
 *     @OA\Property(property="City", type="string", nullable=true),
 *     @OA\Property(property="State", type="string", nullable=true),
 *     @OA\Property(property="Zip", type="string", nullable=true),
 *     @OA\Property(property="Country", type="string", nullable=true),
 *     @OA\Property(property="SecondAddress1", type="string", nullable=true),
 *     @OA\Property(property="SecondAddress2", type="string", nullable=true),
 *     @OA\Property(property="SecondCity", type="string", nullable=true),
 *     @OA\Property(property="SecondState", type="string", nullable=true),
 *     @OA\Property(property="SecondZip", type="string", nullable=true),
 *     @OA\Property(property="SecondCountry", type="string", nullable=true),
 *     @OA\Property(property="SecondIsMailing", type="boolean"),
 *     @OA\Property(property="HomePhone", type="string", nullable=true),
 *     @OA\Property(property="Email", type="string", readOnly=true, deprecated=true, description="Deprecated in 7.8.3: the head of household's email, else the spouse's. Removed with the family email column."),
 *     @OA\Property(property="Weddingdate", type="string", format="date", nullable=true),
 *     @OA\Property(property="DateEntered", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="DateLastEdited", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="EnteredBy", type="integer", nullable=true),
 *     @OA\Property(property="EditedBy", type="integer", nullable=true),
 *     @OA\Property(property="ScanCheck", type="string", nullable=true, description="Finance role only"),
 *     @OA\Property(property="ScanCredit", type="string", nullable=true, description="Finance role only"),
 *     @OA\Property(property="SendNewsletter", type="string", nullable=true),
 *     @OA\Property(property="DateDeactivated", type="string", format="date", nullable=true, description="Empty/null for an active family"),
 *     @OA\Property(property="Latitude", type="number", format="double", nullable=true),
 *     @OA\Property(property="Longitude", type="number", format="double", nullable=true),
 *     @OA\Property(property="Envelope", type="integer", nullable=true, description="Finance role only"),
 *     @OA\Property(property="NeedsReview", type="boolean"),
 *     @OA\Property(property="Address", type="string", description="Primary address, one line"),
 *     @OA\Property(property="FamilyString", type="string", description="Display string for the family"),
 *     @OA\Property(property="HasSecondAddress", type="boolean"),
 *     @OA\Property(property="SecondAddressIsMailing", type="boolean"),
 *     @OA\Property(property="SecondAddress", type="string", description="Second address, one line"),
 *     @OA\Property(property="MailingAddressLines", type="string", description="Resolved mailing address, one line"),
 *     @OA\Property(
 *         property="MailingAddress",
 *         type="object",
 *         description="Resolved mailing address parts",
 *         @OA\Property(property="Address1", type="string"),
 *         @OA\Property(property="Address2", type="string"),
 *         @OA\Property(property="City", type="string"),
 *         @OA\Property(property="State", type="string"),
 *         @OA\Property(property="Zip", type="string"),
 *         @OA\Property(property="Country", type="string")
 *     )
 * )
 */
