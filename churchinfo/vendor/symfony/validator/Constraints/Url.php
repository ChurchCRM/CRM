<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Url extends Constraint
{
    const INVALID_URL_ERROR = '57c2f299-1154-4870-89bb-ef3b1f5ad229';

    protected static $errorNames = array(
        self::INVALID_URL_ERROR => 'INVALID_URL_ERROR',
    );

    public $message = 'This value is not a valid URL.';
    public $dnsMessage = 'The host could not be resolved.';
    public $protocols = array('http', 'https');
    public $checkDNS = false;
}
