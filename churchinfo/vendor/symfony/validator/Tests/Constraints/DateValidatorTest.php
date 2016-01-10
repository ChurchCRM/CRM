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

use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\DateValidator;
use Symfony\Component\Validator\Validation;

class DateValidatorTest extends AbstractConstraintValidatorTest
{
    protected function createValidator()
    {
        return new DateValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Date());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Date());

        $this->assertNoViolation();
    }

    public function testDateTimeClassIsValid()
    {
        $this->validator->validate(new \DateTime(), new Date());

        $this->assertNoViolation();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new Date());
    }

    /**
     * @dataProvider getValidDates
     */
    public function testValidDates($date)
    {
        $this->validator->validate($date, new Date());

        $this->assertNoViolation();
    }

    public function getValidDates()
    {
        return array(
            array('2010-01-01'),
            array('1955-12-12'),
            array('2030-05-31'),
        );
    }

    /**
     * @dataProvider getInvalidDates
     */
    public function testInvalidDates($date, $code)
    {
        $constraint = new Date(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate($date, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$date.'"')
            ->setCode($code)
            ->assertRaised();
    }

    public function getInvalidDates()
    {
        return array(
            array('foobar', Date::INVALID_FORMAT_ERROR),
            array('foobar 2010-13-01', Date::INVALID_FORMAT_ERROR),
            array('2010-13-01 foobar', Date::INVALID_FORMAT_ERROR),
            array('2010-13-01', Date::INVALID_DATE_ERROR),
            array('2010-04-32', Date::INVALID_DATE_ERROR),
            array('2010-02-29', Date::INVALID_DATE_ERROR),
        );
    }
}
