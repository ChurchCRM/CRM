<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\TokenQuery as BaseTokenQuery;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Skeleton subclass for performing query and update operations on the 'tokens' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class TokenQuery extends BaseTokenQuery
{
    public function findPasswordResetToken(string $token): ?Token
    {
        // A stored digest must never be accepted as a bearer token.
        if (str_starts_with($token, 'sha256:')) {
            return null;
        }

        return $this->findPk(Token::passwordResetStorageKey($token))
            ?? $this->filterByToken('sha256:%', Criteria::NOT_LIKE)->findPk($token);
    }
}
