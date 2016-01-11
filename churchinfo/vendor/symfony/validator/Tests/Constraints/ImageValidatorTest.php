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

use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\ImageValidator;
use Symfony\Component\Validator\Validation;

/**
 * @requires extension fileinfo
 */
class ImageValidatorTest extends AbstractConstraintValidatorTest
{
    protected $context;

    /**
     * @var ImageValidator
     */
    protected $validator;

    protected $path;
    protected $image;
    protected $imageLandscape;
    protected $imagePortrait;
    protected $image4By3;

    protected function createValidator()
    {
        return new ImageValidator();
    }

    protected function setUp()
    {
        parent::setUp();

        $this->image = __DIR__.'/Fixtures/test.gif';
        $this->imageLandscape = __DIR__.'/Fixtures/test_landscape.gif';
        $this->imagePortrait = __DIR__.'/Fixtures/test_portrait.gif';
        $this->image4By3 = __DIR__.'/Fixtures/test_4by3.gif';
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Image());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Image());

        $this->assertNoViolation();
    }

    public function testValidImage()
    {
        $this->validator->validate($this->image, new Image());

        $this->assertNoViolation();
    }

    public function testFileNotFound()
    {
        // Check that the logic from FileValidator still works
        $constraint = new Image(array(
            'notFoundMessage' => 'myMessage',
        ));

        $this->validator->validate('foobar', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ file }}', '"foobar"')
            ->setCode(Image::NOT_FOUND_ERROR)
            ->assertRaised();
    }

    public function testValidSize()
    {
        $constraint = new Image(array(
            'minWidth' => 1,
            'maxWidth' => 2,
            'minHeight' => 1,
            'maxHeight' => 2,
        ));

        $this->validator->validate($this->image, $constraint);

        $this->assertNoViolation();
    }

    public function testWidthTooSmall()
    {
        $constraint = new Image(array(
            'minWidth' => 3,
            'minWidthMessage' => 'myMessage',
        ));

        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', '2')
            ->setParameter('{{ min_width }}', '3')
            ->setCode(Image::TOO_NARROW_ERROR)
            ->assertRaised();
    }

    public function testWidthTooBig()
    {
        $constraint = new Image(array(
            'maxWidth' => 1,
            'maxWidthMessage' => 'myMessage',
        ));

        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', '2')
            ->setParameter('{{ max_width }}', '1')
            ->setCode(Image::TOO_WIDE_ERROR)
            ->assertRaised();
    }

    public function testHeightTooSmall()
    {
        $constraint = new Image(array(
            'minHeight' => 3,
            'minHeightMessage' => 'myMessage',
        ));

        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ height }}', '2')
            ->setParameter('{{ min_height }}', '3')
            ->setCode(Image::TOO_LOW_ERROR)
            ->assertRaised();
    }

    public function testHeightTooBig()
    {
        $constraint = new Image(array(
            'maxHeight' => 1,
            'maxHeightMessage' => 'myMessage',
        ));

        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ height }}', '2')
            ->setParameter('{{ max_height }}', '1')
            ->setCode(Image::TOO_HIGH_ERROR)
            ->assertRaised();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testInvalidMinWidth()
    {
        $constraint = new Image(array(
            'minWidth' => '1abc',
        ));

        $this->validator->validate($this->image, $constraint);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testInvalidMaxWidth()
    {
        $constraint = new Image(array(
            'maxWidth' => '1abc',
        ));

        $this->validator->validate($this->image, $constraint);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testInvalidMinHeight()
    {
        $constraint = new Image(array(
            'minHeight' => '1abc',
        ));

        $this->validator->validate($this->image, $constraint);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testInvalidMaxHeight()
    {
        $constraint = new Image(array(
            'maxHeight' => '1abc',
        ));

        $this->validator->validate($this->image, $constraint);
    }

    public function testRatioTooSmall()
    {
        $constraint = new Image(array(
            'minRatio' => 2,
            'minRatioMessage' => 'myMessage',
        ));

        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ ratio }}', 1)
            ->setParameter('{{ min_ratio }}', 2)
            ->setCode(Image::RATIO_TOO_SMALL_ERROR)
            ->assertRaised();
    }

    public function testRatioTooBig()
    {
        $constraint = new Image(array(
            'maxRatio' => 0.5,
            'maxRatioMessage' => 'myMessage',
        ));

        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ ratio }}', 1)
            ->setParameter('{{ max_ratio }}', 0.5)
            ->setCode(Image::RATIO_TOO_BIG_ERROR)
            ->assertRaised();
    }

    public function testMaxRatioUsesTwoDecimalsOnly()
    {
        $constraint = new Image(array(
            'maxRatio' => 1.33,
        ));

        $this->validator->validate($this->image4By3, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testInvalidMinRatio()
    {
        $constraint = new Image(array(
            'minRatio' => '1abc',
        ));

        $this->validator->validate($this->image, $constraint);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testInvalidMaxRatio()
    {
        $constraint = new Image(array(
            'maxRatio' => '1abc',
        ));

        $this->validator->validate($this->image, $constraint);
    }

    public function testSquareNotAllowed()
    {
        $constraint = new Image(array(
            'allowSquare' => false,
            'allowSquareMessage' => 'myMessage',
        ));

        $this->validator->validate($this->image, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', 2)
            ->setParameter('{{ height }}', 2)
            ->setCode(Image::SQUARE_NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    public function testLandscapeNotAllowed()
    {
        $constraint = new Image(array(
            'allowLandscape' => false,
            'allowLandscapeMessage' => 'myMessage',
        ));

        $this->validator->validate($this->imageLandscape, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', 2)
            ->setParameter('{{ height }}', 1)
            ->setCode(Image::LANDSCAPE_NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    public function testPortraitNotAllowed()
    {
        $constraint = new Image(array(
            'allowPortrait' => false,
            'allowPortraitMessage' => 'myMessage',
        ));

        $this->validator->validate($this->imagePortrait, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ width }}', 1)
            ->setParameter('{{ height }}', 2)
            ->setCode(Image::PORTRAIT_NOT_ALLOWED_ERROR)
            ->assertRaised();
    }
}
