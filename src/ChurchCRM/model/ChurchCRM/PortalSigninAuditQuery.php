<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\PortalSigninAuditQuery as BasePortalSigninAuditQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'portal_signin_audit_psa' table.
 *
 * The member sign-in audit (design §11.2). Never holds a code, link, token, password or passkey secret
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @template ParentQuery extends \Propel\Runtime\ActiveQuery\TypedModelCriteria|null = null
 * @extends BasePortalSigninAuditQuery<ParentQuery>
 */
class PortalSigninAuditQuery extends BasePortalSigninAuditQuery
{

}
