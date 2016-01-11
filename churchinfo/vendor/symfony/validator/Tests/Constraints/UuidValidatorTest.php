<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\Uuid;
use Symfony\Component\Validator\Constraints\UuidValidator;
use Symfony\Component\Validator\Validation;

/**
 * @author Colin O'Dell <colinodell@gmail.com>
 */
class UuidValidatorTest extends AbstractConstraintValidatorTest
{
    protected function createValidator()
    {
        return new UuidValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Uuid());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Uuid());

        $this->assertNoViolation();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new Uuid());
    }

    /**
     * @dataProvider getValidStrictUuids
     */
    public function testValidStrictUuids($uuid, $versions = null)
    {
        $constraint = new Uuid();

        if (null !== $versions) {
            $constraint->versions = $versions;
        }

        $this->validator->validate($uuid, $constraint);

        $this->assertNoViolation();
    }

    public function getValidStrictUuids()
    {
        return array(
            array('216fff40-98d9-11e3-a5e2-0800200c9a66'), // Version 1 UUID in lowercase
            array('216fff40-98d9-11e3-a5e2-0800200c9a66', array(Uuid::V1_MAC)),
            array('216FFF40-98D9-11E3-A5E2-0800200C9A66'), // Version 1 UUID in UPPERCASE
            array('456daefb-5aa6-41b5-8dbc-068b05a8b201'), // Version 4 UUID in lowercase
            array('456daEFb-5AA6-41B5-8DBC-068B05A8B201'), // Version 4 UUID in mixed case
            array('456daEFb-5AA6-41B5-8DBC-068B05A8B201', array(Uuid::V4_RANDOM)),
        );
    }

    /**
     * @dataProvider getInvalidStrictUuids
     */
    public function testInvalidStrictUuids($uuid, $code, $versions = null)
    {
        $constraint = new Uuid(array(
            'message' => 'testMessage',
        ));

        if (null !== $versions) {
            $constraint->versions = $versions;
        }

        $this->validator->validate($uuid, $constraint);

        $this->buildViolation('testMessage')
            ->setParameter('{{ value }}', '"'.$uuid.'"')
            ->setCode($code)
            ->assertRaised();
    }

    public function getInvalidStrictUuids()
    {
        return array(
            array('216fff40-98d9-11e3-a5e2_0800200c9a66', Uuid::INVALID_CHARACTERS_ERROR),
            array('216gff40-98d9-11e3-a5e2-0800200c9a66', Uuid::INVALID_CHARACTERS_ERROR),
            array('216Gff40-98d9-11e3-a5e2-0800200c9a66', Uuid::INVALID_CHARACTERS_ERROR),
            array('216fff40-98d9-11e3-a5e-20800200c9a66', Uuid::INVALID_HYPHEN_PLACEMENT_ERROR),
            array('216f-ff40-98d9-11e3-a5e2-0800200c9a66', Uuid::INVALID_HYPHEN_PLACEMENT_ERROR),
            array('216fff40-98d9-11e3-a5e2-0800-200c9a66', Uuid::INVALID_HYPHEN_PLACEMENT_ERROR),
            array('216fff40-98d9-11e3-a5e2-0800200c-9a66', Uuid::INVALID_HYPHEN_PLACEMENT_ERROR),
            array('216fff40-98d9-11e3-a5e20800200c9a66', Uuid::INVALID_HYPHEN_PLACEMENT_ERROR),
            array('216fff4098d911e3a5e20800200c9a66', Uuid::INVALID_HYPHEN_PLACEMENT_ERROR),
            array('216fff40-98d9-11e3-a5e2-0800200c9a6', Uuid::TOO_SHORT_ERROR),
            array('216fff40-98d9-11e3-a5e2-0800200c9a666', Uuid::TOO_LONG_ERROR),
            array('216fff40-98d9-01e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR),
            array('216fff40-98d9-61e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR),
            array('216fff40-98d9-71e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR),
            array('216fff40-98d9-81e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR),
            array('216fff40-98d9-91e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR),
            array('216fff40-98d9-a1e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR),
            array('216fff40-98d9-b1e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR),
            array('216fff40-98d9-c1e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR),
            array('216fff40-98d9-d1e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR),
            array('216fff40-98d9-e1e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR),
            array('216fff40-98d9-f1e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR),
            array('216fff40-98d9-11e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR, array(Uuid::V2_DCE, Uuid::V3_MD5, Uuid::V4_RANDOM, Uuid::V5_SHA1)),
            array('216fff40-98d9-21e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR, array(Uuid::V1_MAC, Uuid::V3_MD5, Uuid::V4_RANDOM, Uuid::V5_SHA1)),
            array('216fff40-98d9-11e3-05e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR),
            array('216fff40-98d9-11e3-15e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR),
            array('216fff40-98d9-11e3-25e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR),
            array('216fff40-98d9-11e3-35e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR),
            array('216fff40-98d9-11e3-45e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR),
            array('216fff40-98d9-11e3-55e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR),
            array('216fff40-98d9-11e3-65e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR),
            array('216fff40-98d9-11e3-75e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR),
            array('216fff40-98d9-11e3-c5e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR),
            array('216fff40-98d9-11e3-d5e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR),
            array('216fff40-98d9-11e3-e5e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR),
            array('216fff40-98d9-11e3-f5e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR),

            // Non-standard UUID allowed by some other systems
            array('{216fff40-98d9-11e3-a5e2-0800200c9a66}', Uuid::INVALID_CHARACTERS_ERROR),
            array('[216fff40-98d9-11e3-a5e2-0800200c9a66]', Uuid::INVALID_CHARACTERS_ERROR),
        );
    }

    /**
     * @dataProvider getValidNonStrictUuids
     */
    public function testValidNonStrictUuids($uuid)
    {
        $constraint = new Uuid(array(
            'strict' => false,
        ));

        $this->validator->validate($uuid, $constraint);

        $this->assertNoViolation();
    }

    public function getValidNonStrictUuids()
    {
        return array(
            array('216fff40-98d9-11e3-a5e2-0800200c9a66'),    // Version 1 UUID in lowercase
            array('216FFF40-98D9-11E3-A5E2-0800200C9A66'),    // Version 1 UUID in UPPERCASE
            array('456daefb-5aa6-41b5-8dbc-068b05a8b201'),    // Version 4 UUID in lowercase
            array('456DAEFb-5AA6-41B5-8DBC-068b05a8B201'),    // Version 4 UUID in mixed case

            // Non-standard UUIDs allowed by some other systems
            array('216f-ff40-98d9-11e3-a5e2-0800-200c-9a66'), // Non-standard dash positions (every 4 chars)
            array('216fff40-98d911e3-a5e20800-200c9a66'),     // Non-standard dash positions (every 8 chars)
            array('216fff4098d911e3a5e20800200c9a66'),        // No dashes at all
            array('{216fff40-98d9-11e3-a5e2-0800200c9a66}'),  // Wrapped with curly braces
            array('[216fff40-98d9-11e3-a5e2-0800200c9a66]'),  // Wrapped with squared braces
        );
    }

    /**
     * @dataProvider getInvalidNonStrictUuids
     */
    public function testInvalidNonStrictUuids($uuid, $code)
    {
        $constraint = new Uuid(array(
            'strict' => false,
            'message' => 'myMessage',
        ));

        $this->validator->validate($uuid, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$uuid.'"')
            ->setCode($code)
            ->assertRaised();
    }

    public function getInvalidNonStrictUuids()
    {
        return array(
            array('216fff40-98d9-11e3-a5e2_0800200c9a66', Uuid::INVALID_CHARACTERS_ERROR),
            array('216gff40-98d9-11e3-a5e2-0800200c9a66', Uuid::INVALID_CHARACTERS_ERROR),
            array('216Gff40-98d9-11e3-a5e2-0800200c9a66', Uuid::INVALID_CHARACTERS_ERROR),
            array('216fff40-98d9-11e3-a5e2_0800200c9a6', Uuid::INVALID_CHARACTERS_ERROR),
            array('216fff40-98d9-11e3-a5e-20800200c9a66', Uuid::INVALID_HYPHEN_PLACEMENT_ERROR),
            array('216fff40-98d9-11e3-a5e2-0800200c9a6', Uuid::TOO_SHORT_ERROR),
            array('216fff40-98d9-11e3-a5e2-0800200c9a666', Uuid::TOO_LONG_ERROR),
        );
    }
}
