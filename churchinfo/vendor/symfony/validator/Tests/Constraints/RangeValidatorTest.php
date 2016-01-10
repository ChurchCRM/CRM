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

use Symfony\Component\Intl\Util\IntlTestHelper;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\RangeValidator;
use Symfony\Component\Validator\Validation;

class RangeValidatorTest extends AbstractConstraintValidatorTest
{
    protected function createValidator()
    {
        return new RangeValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Range(array('min' => 10, 'max' => 20)));

        $this->assertNoViolation();
    }

    public function getTenToTwenty()
    {
        return array(
            array(10.00001),
            array(19.99999),
            array('10.00001'),
            array('19.99999'),
            array(10),
            array(20),
            array(10.0),
            array(20.0),
        );
    }

    public function getLessThanTen()
    {
        return array(
            array(9.99999, '9.99999'),
            array('9.99999', '"9.99999"'),
            array(5, '5'),
            array(1.0, '1.0'),
        );
    }

    public function getMoreThanTwenty()
    {
        return array(
            array(20.000001, '20.000001'),
            array('20.000001', '"20.000001"'),
            array(21, '21'),
            array(30.0, '30.0'),
        );
    }

    /**
     * @dataProvider getTenToTwenty
     */
    public function testValidValuesMin($value)
    {
        $constraint = new Range(array('min' => 10));
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getTenToTwenty
     */
    public function testValidValuesMax($value)
    {
        $constraint = new Range(array('max' => 20));
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getTenToTwenty
     */
    public function testValidValuesMinMax($value)
    {
        $constraint = new Range(array('min' => 10, 'max' => 20));
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getLessThanTen
     */
    public function testInvalidValuesMin($value, $formattedValue)
    {
        $constraint = new Range(array(
            'min' => 10,
            'minMessage' => 'myMessage',
        ));

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $formattedValue)
            ->setParameter('{{ limit }}', 10)
            ->setCode(Range::TOO_LOW_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getMoreThanTwenty
     */
    public function testInvalidValuesMax($value, $formattedValue)
    {
        $constraint = new Range(array(
            'max' => 20,
            'maxMessage' => 'myMessage',
        ));

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $formattedValue)
            ->setParameter('{{ limit }}', 20)
            ->setCode(Range::TOO_HIGH_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getMoreThanTwenty
     */
    public function testInvalidValuesCombinedMax($value, $formattedValue)
    {
        $constraint = new Range(array(
            'min' => 10,
            'max' => 20,
            'minMessage' => 'myMinMessage',
            'maxMessage' => 'myMaxMessage',
        ));

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMaxMessage')
            ->setParameter('{{ value }}', $formattedValue)
            ->setParameter('{{ limit }}', 20)
            ->setCode(Range::TOO_HIGH_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getLessThanTen
     */
    public function testInvalidValuesCombinedMin($value, $formattedValue)
    {
        $constraint = new Range(array(
            'min' => 10,
            'max' => 20,
            'minMessage' => 'myMinMessage',
            'maxMessage' => 'myMaxMessage',
        ));

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMinMessage')
            ->setParameter('{{ value }}', $formattedValue)
            ->setParameter('{{ limit }}', 10)
            ->setCode(Range::TOO_LOW_ERROR)
            ->assertRaised();
    }

    public function getTenthToTwentiethMarch2014()
    {
        // The provider runs before setUp(), so we need to manually fix
        // the default timezone
        $this->setDefaultTimezone('UTC');

        $tests = array(
            array(new \DateTime('March 10, 2014')),
            array(new \DateTime('March 15, 2014')),
            array(new \DateTime('March 20, 2014')),
        );

        $tests[] = array(new \DateTimeImmutable('March 10, 2014'));
        $tests[] = array(new \DateTimeImmutable('March 15, 2014'));
        $tests[] = array(new \DateTimeImmutable('March 20, 2014'));

        $this->restoreDefaultTimezone();

        return $tests;
    }

    public function getSoonerThanTenthMarch2014()
    {
        // The provider runs before setUp(), so we need to manually fix
        // the default timezone
        $this->setDefaultTimezone('UTC');

        $tests = array(
            array(new \DateTime('March 20, 2013'), 'Mar 20, 2013, 12:00 AM'),
            array(new \DateTime('March 9, 2014'), 'Mar 9, 2014, 12:00 AM'),
        );

        $tests[] = array(new \DateTimeImmutable('March 20, 2013'), 'Mar 20, 2013, 12:00 AM');
        $tests[] = array(new \DateTimeImmutable('March 9, 2014'), 'Mar 9, 2014, 12:00 AM');

        $this->restoreDefaultTimezone();

        return $tests;
    }

    public function getLaterThanTwentiethMarch2014()
    {
        // The provider runs before setUp(), so we need to manually fix
        // the default timezone
        $this->setDefaultTimezone('UTC');

        $tests = array(
            array(new \DateTime('March 21, 2014'), 'Mar 21, 2014, 12:00 AM'),
            array(new \DateTime('March 9, 2015'), 'Mar 9, 2015, 12:00 AM'),
        );

        $tests[] = array(new \DateTimeImmutable('March 21, 2014'), 'Mar 21, 2014, 12:00 AM');
        $tests[] = array(new \DateTimeImmutable('March 9, 2015'), 'Mar 9, 2015, 12:00 AM');

        $this->restoreDefaultTimezone();

        return $tests;
    }

    /**
     * @dataProvider getTenthToTwentiethMarch2014
     */
    public function testValidDatesMin($value)
    {
        $constraint = new Range(array('min' => 'March 10, 2014'));
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getTenthToTwentiethMarch2014
     */
    public function testValidDatesMax($value)
    {
        $constraint = new Range(array('max' => 'March 20, 2014'));
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getTenthToTwentiethMarch2014
     */
    public function testValidDatesMinMax($value)
    {
        $constraint = new Range(array('min' => 'March 10, 2014', 'max' => 'March 20, 2014'));
        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getSoonerThanTenthMarch2014
     */
    public function testInvalidDatesMin($value, $dateTimeAsString)
    {
        // Conversion of dates to string differs between ICU versions
        // Make sure we have the correct version loaded
        IntlTestHelper::requireIntl($this);

        $constraint = new Range(array(
            'min' => 'March 10, 2014',
            'minMessage' => 'myMessage',
        ));

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $dateTimeAsString)
            ->setParameter('{{ limit }}', 'Mar 10, 2014, 12:00 AM')
            ->setCode(Range::TOO_LOW_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getLaterThanTwentiethMarch2014
     */
    public function testInvalidDatesMax($value, $dateTimeAsString)
    {
        // Conversion of dates to string differs between ICU versions
        // Make sure we have the correct version loaded
        IntlTestHelper::requireIntl($this);

        $constraint = new Range(array(
            'max' => 'March 20, 2014',
            'maxMessage' => 'myMessage',
        ));

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', $dateTimeAsString)
            ->setParameter('{{ limit }}', 'Mar 20, 2014, 12:00 AM')
            ->setCode(Range::TOO_HIGH_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getLaterThanTwentiethMarch2014
     */
    public function testInvalidDatesCombinedMax($value, $dateTimeAsString)
    {
        // Conversion of dates to string differs between ICU versions
        // Make sure we have the correct version loaded
        IntlTestHelper::requireIntl($this);

        $constraint = new Range(array(
            'min' => 'March 10, 2014',
            'max' => 'March 20, 2014',
            'minMessage' => 'myMinMessage',
            'maxMessage' => 'myMaxMessage',
        ));

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMaxMessage')
            ->setParameter('{{ value }}', $dateTimeAsString)
            ->setParameter('{{ limit }}', 'Mar 20, 2014, 12:00 AM')
            ->setCode(Range::TOO_HIGH_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getSoonerThanTenthMarch2014
     */
    public function testInvalidDatesCombinedMin($value, $dateTimeAsString)
    {
        // Conversion of dates to string differs between ICU versions
        // Make sure we have the correct version loaded
        IntlTestHelper::requireIntl($this);

        $constraint = new Range(array(
            'min' => 'March 10, 2014',
            'max' => 'March 20, 2014',
            'minMessage' => 'myMinMessage',
            'maxMessage' => 'myMaxMessage',
        ));

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMinMessage')
            ->setParameter('{{ value }}', $dateTimeAsString)
            ->setParameter('{{ limit }}', 'Mar 10, 2014, 12:00 AM')
            ->setCode(Range::TOO_LOW_ERROR)
            ->assertRaised();
    }

    public function getInvalidValues()
    {
        return array(
            array(9.999999),
            array(20.000001),
            array('9.999999'),
            array('20.000001'),
            array(new \stdClass()),
        );
    }

    public function testNonNumeric()
    {
        $this->validator->validate('abcd', new Range(array(
            'min' => 10,
            'max' => 20,
            'invalidMessage' => 'myMessage',
        )));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"abcd"')
            ->setCode(Range::INVALID_CHARACTERS_ERROR)
            ->assertRaised();
    }
}
