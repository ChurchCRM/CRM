<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\RateLimit as BaseRateLimit;

/**
 * Skeleton subclass for representing a row from the 'rate_limit_rlm' table.
 *
 * Attempt counters for RateLimiter (#10197): one row per bucket and key, counting hits in a window that starts at the first hit. The key is stored only as a SHA-256 hash, and a row is deleted once its window has ended.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class RateLimit extends BaseRateLimit
{

}
