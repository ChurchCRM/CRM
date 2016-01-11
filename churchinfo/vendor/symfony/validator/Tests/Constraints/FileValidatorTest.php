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

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\FileValidator;
use Symfony\Component\Validator\Validation;

abstract class FileValidatorTest extends AbstractConstraintValidatorTest
{
    protected $path;

    protected $file;

    protected function createValidator()
    {
        return new FileValidator();
    }

    protected function setUp()
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'FileValidatorTest';
        $this->file = fopen($this->path, 'w');
        fwrite($this->file, ' ', 1);
    }

    protected function tearDown()
    {
        parent::tearDown();

        if (is_resource($this->file)) {
            fclose($this->file);
        }

        if (file_exists($this->path)) {
            unlink($this->path);
        }

        $this->path = null;
        $this->file = null;
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new File());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new File());

        $this->assertNoViolation();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleTypeOrFile()
    {
        $this->validator->validate(new \stdClass(), new File());
    }

    public function testValidFile()
    {
        $this->validator->validate($this->path, new File());

        $this->assertNoViolation();
    }

    public function testValidUploadedfile()
    {
        $file = new UploadedFile($this->path, 'originalName', null, null, null, true);
        $this->validator->validate($file, new File());

        $this->assertNoViolation();
    }

    public function provideMaxSizeExceededTests()
    {
        // We have various interesting limit - size combinations to test.
        // Assume a limit of 1000 bytes (1 kB). Then the following table
        // lists the violation messages for different file sizes:
        // -----------+--------------------------------------------------------
        // Size       | Violation Message
        // -----------+--------------------------------------------------------
        // 1000 bytes | No violation
        // 1001 bytes | "Size of 1001 bytes exceeded limit of 1000 bytes"
        // 1004 bytes | "Size of 1004 bytes exceeded limit of 1000 bytes"
        //            | NOT: "Size of 1 kB exceeded limit of 1 kB"
        // 1005 bytes | "Size of 1.01 kB exceeded limit of 1 kB"
        // -----------+--------------------------------------------------------

        // As you see, we have two interesting borders:

        // 1000/1001 - The border as of which a violation occurs
        // 1004/1005 - The border as of which the message can be rounded to kB

        // Analogous for kB/MB.

        // Prior to Symfony 2.5, violation messages are always displayed in the
        // same unit used to specify the limit.

        // As of Symfony 2.5, the above logic is implemented.
        return array(
            // limit in bytes
            array(1001, 1000, '1001', '1000', 'bytes'),
            array(1004, 1000, '1004', '1000', 'bytes'),
            array(1005, 1000, '1.01', '1', 'kB'),

            array(1000001, 1000000, '1000001', '1000000', 'bytes'),
            array(1004999, 1000000, '1005', '1000', 'kB'),
            array(1005000, 1000000, '1.01', '1', 'MB'),

            // limit in kB
            array(1001, '1k', '1001', '1000', 'bytes'),
            array(1004, '1k', '1004', '1000', 'bytes'),
            array(1005, '1k', '1.01', '1', 'kB'),

            array(1000001, '1000k', '1000001', '1000000', 'bytes'),
            array(1004999, '1000k', '1005', '1000', 'kB'),
            array(1005000, '1000k', '1.01', '1', 'MB'),

            // limit in MB
            array(1000001, '1M', '1000001', '1000000', 'bytes'),
            array(1004999, '1M', '1005', '1000', 'kB'),
            array(1005000, '1M', '1.01', '1', 'MB'),

            // limit in KiB
            array(1025, '1Ki', '1025', '1024', 'bytes'),
            array(1029, '1Ki', '1029', '1024', 'bytes'),
            array(1030, '1Ki', '1.01', '1', 'KiB'),

            array(1048577, '1024Ki', '1048577', '1048576', 'bytes'),
            array(1053818, '1024Ki', '1029.12', '1024', 'KiB'),
            array(1053819, '1024Ki', '1.01', '1', 'MiB'),

            // limit in MiB
            array(1048577, '1Mi', '1048577', '1048576', 'bytes'),
            array(1053818, '1Mi', '1029.12', '1024', 'KiB'),
            array(1053819, '1Mi', '1.01', '1', 'MiB'),
        );
    }

    /**
     * @dataProvider provideMaxSizeExceededTests
     */
    public function testMaxSizeExceeded($bytesWritten, $limit, $sizeAsString, $limitAsString, $suffix)
    {
        fseek($this->file, $bytesWritten - 1, SEEK_SET);
        fwrite($this->file, '0');
        fclose($this->file);

        $constraint = new File(array(
            'maxSize' => $limit,
            'maxSizeMessage' => 'myMessage',
        ));

        $this->validator->validate($this->getFile($this->path), $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ limit }}', $limitAsString)
            ->setParameter('{{ size }}', $sizeAsString)
            ->setParameter('{{ suffix }}', $suffix)
            ->setParameter('{{ file }}', '"'.$this->path.'"')
            ->setCode(File::TOO_LARGE_ERROR)
            ->assertRaised();
    }

    public function provideMaxSizeNotExceededTests()
    {
        return array(
            // limit in bytes
            array(1000, 1000),
            array(1000000, 1000000),

            // limit in kB
            array(1000, '1k'),
            array(1000000, '1000k'),

            // limit in MB
            array(1000000, '1M'),

            // limit in KiB
            array(1024, '1Ki'),
            array(1048576, '1024Ki'),

            // limit in MiB
            array(1048576, '1Mi'),
        );
    }

    /**
     * @dataProvider provideMaxSizeNotExceededTests
     */
    public function testMaxSizeNotExceeded($bytesWritten, $limit)
    {
        fseek($this->file, $bytesWritten - 1, SEEK_SET);
        fwrite($this->file, '0');
        fclose($this->file);

        $constraint = new File(array(
            'maxSize' => $limit,
            'maxSizeMessage' => 'myMessage',
        ));

        $this->validator->validate($this->getFile($this->path), $constraint);

        $this->assertNoViolation();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testInvalidMaxSize()
    {
        $constraint = new File(array(
            'maxSize' => '1abc',
        ));

        $this->validator->validate($this->path, $constraint);
    }

    public function provideBinaryFormatTests()
    {
        return array(
            array(11, 10, null, '11', '10', 'bytes'),
            array(11, 10, true, '11', '10', 'bytes'),
            array(11, 10, false, '11', '10', 'bytes'),

            // round(size) == 1.01kB, limit == 1kB
            array(ceil(1000 * 1.01), 1000, null, '1.01', '1', 'kB'),
            array(ceil(1000 * 1.01), '1k', null, '1.01', '1', 'kB'),
            array(ceil(1024 * 1.01), '1Ki', null, '1.01', '1', 'KiB'),

            array(ceil(1024 * 1.01), 1024, true, '1.01', '1', 'KiB'),
            array(ceil(1024 * 1.01 * 1000), '1024k', true, '1010', '1000', 'KiB'),
            array(ceil(1024 * 1.01), '1Ki', true, '1.01', '1', 'KiB'),

            array(ceil(1000 * 1.01), 1000, false, '1.01', '1', 'kB'),
            array(ceil(1000 * 1.01), '1k', false, '1.01', '1', 'kB'),
            array(ceil(1024 * 1.01 * 10), '10Ki', false, '10.34', '10.24', 'kB'),
        );
    }

    /**
     * @dataProvider provideBinaryFormatTests
     */
    public function testBinaryFormat($bytesWritten, $limit, $binaryFormat, $sizeAsString, $limitAsString, $suffix)
    {
        fseek($this->file, $bytesWritten - 1, SEEK_SET);
        fwrite($this->file, '0');
        fclose($this->file);

        $constraint = new File(array(
            'maxSize' => $limit,
            'binaryFormat' => $binaryFormat,
            'maxSizeMessage' => 'myMessage',
        ));

        $this->validator->validate($this->getFile($this->path), $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ limit }}', $limitAsString)
            ->setParameter('{{ size }}', $sizeAsString)
            ->setParameter('{{ suffix }}', $suffix)
            ->setParameter('{{ file }}', '"'.$this->path.'"')
            ->setCode(File::TOO_LARGE_ERROR)
            ->assertRaised();
    }

    public function testValidMimeType()
    {
        $file = $this
            ->getMockBuilder('Symfony\Component\HttpFoundation\File\File')
            ->setConstructorArgs(array(__DIR__.'/Fixtures/foo'))
            ->getMock();
        $file
            ->expects($this->once())
            ->method('getPathname')
            ->will($this->returnValue($this->path));
        $file
            ->expects($this->once())
            ->method('getMimeType')
            ->will($this->returnValue('image/jpg'));

        $constraint = new File(array(
            'mimeTypes' => array('image/png', 'image/jpg'),
        ));

        $this->validator->validate($file, $constraint);

        $this->assertNoViolation();
    }

    public function testValidWildcardMimeType()
    {
        $file = $this
            ->getMockBuilder('Symfony\Component\HttpFoundation\File\File')
            ->setConstructorArgs(array(__DIR__.'/Fixtures/foo'))
            ->getMock();
        $file
            ->expects($this->once())
            ->method('getPathname')
            ->will($this->returnValue($this->path));
        $file
            ->expects($this->once())
            ->method('getMimeType')
            ->will($this->returnValue('image/jpg'));

        $constraint = new File(array(
            'mimeTypes' => array('image/*'),
        ));

        $this->validator->validate($file, $constraint);

        $this->assertNoViolation();
    }

    public function testInvalidMimeType()
    {
        $file = $this
            ->getMockBuilder('Symfony\Component\HttpFoundation\File\File')
            ->setConstructorArgs(array(__DIR__.'/Fixtures/foo'))
            ->getMock();
        $file
            ->expects($this->once())
            ->method('getPathname')
            ->will($this->returnValue($this->path));
        $file
            ->expects($this->once())
            ->method('getMimeType')
            ->will($this->returnValue('application/pdf'));

        $constraint = new File(array(
            'mimeTypes' => array('image/png', 'image/jpg'),
            'mimeTypesMessage' => 'myMessage',
        ));

        $this->validator->validate($file, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ type }}', '"application/pdf"')
            ->setParameter('{{ types }}', '"image/png", "image/jpg"')
            ->setParameter('{{ file }}', '"'.$this->path.'"')
            ->setCode(File::INVALID_MIME_TYPE_ERROR)
            ->assertRaised();
    }

    public function testInvalidWildcardMimeType()
    {
        $file = $this
            ->getMockBuilder('Symfony\Component\HttpFoundation\File\File')
            ->setConstructorArgs(array(__DIR__.'/Fixtures/foo'))
            ->getMock();
        $file
            ->expects($this->once())
            ->method('getPathname')
            ->will($this->returnValue($this->path));
        $file
            ->expects($this->once())
            ->method('getMimeType')
            ->will($this->returnValue('application/pdf'));

        $constraint = new File(array(
            'mimeTypes' => array('image/*', 'image/jpg'),
            'mimeTypesMessage' => 'myMessage',
        ));

        $this->validator->validate($file, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ type }}', '"application/pdf"')
            ->setParameter('{{ types }}', '"image/*", "image/jpg"')
            ->setParameter('{{ file }}', '"'.$this->path.'"')
            ->setCode(File::INVALID_MIME_TYPE_ERROR)
            ->assertRaised();
    }

    public function testDisallowEmpty()
    {
        ftruncate($this->file, 0);

        $constraint = new File(array(
            'disallowEmptyMessage' => 'myMessage',
        ));

        $this->validator->validate($this->getFile($this->path), $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ file }}', '"'.$this->path.'"')
            ->setCode(File::EMPTY_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider uploadedFileErrorProvider
     */
    public function testUploadedFileError($error, $message, array $params = array(), $maxSize = null)
    {
        $file = new UploadedFile('/path/to/file', 'originalName', 'mime', 0, $error);

        $constraint = new File(array(
            $message => 'myMessage',
            'maxSize' => $maxSize,
        ));

        $this->validator->validate($file, $constraint);

        $this->buildViolation('myMessage')
            ->setParameters($params)
            ->setCode($error)
            ->assertRaised();
    }

    public function uploadedFileErrorProvider()
    {
        $tests = array(
            array(UPLOAD_ERR_FORM_SIZE, 'uploadFormSizeErrorMessage'),
            array(UPLOAD_ERR_PARTIAL, 'uploadPartialErrorMessage'),
            array(UPLOAD_ERR_NO_FILE, 'uploadNoFileErrorMessage'),
            array(UPLOAD_ERR_NO_TMP_DIR, 'uploadNoTmpDirErrorMessage'),
            array(UPLOAD_ERR_CANT_WRITE, 'uploadCantWriteErrorMessage'),
            array(UPLOAD_ERR_EXTENSION, 'uploadExtensionErrorMessage'),
        );

        if (class_exists('Symfony\Component\HttpFoundation\File\UploadedFile')) {
            // when no maxSize is specified on constraint, it should use the ini value
            $tests[] = array(UPLOAD_ERR_INI_SIZE, 'uploadIniSizeErrorMessage', array(
                '{{ limit }}' => UploadedFile::getMaxFilesize() / 1048576,
                '{{ suffix }}' => 'MiB',
            ));

            // it should use the smaller limitation (maxSize option in this case)
            $tests[] = array(UPLOAD_ERR_INI_SIZE, 'uploadIniSizeErrorMessage', array(
                '{{ limit }}' => 1,
                '{{ suffix }}' => 'bytes',
            ), '1');

            // it correctly parses the maxSize option and not only uses simple string comparison
            // 1000M should be bigger than the ini value
            $tests[] = array(UPLOAD_ERR_INI_SIZE, 'uploadIniSizeErrorMessage', array(
                '{{ limit }}' => UploadedFile::getMaxFilesize() / 1048576,
                '{{ suffix }}' => 'MiB',
            ), '1000M');

            // it correctly parses the maxSize option and not only uses simple string comparison
            // 1000M should be bigger than the ini value
            $tests[] = array(UPLOAD_ERR_INI_SIZE, 'uploadIniSizeErrorMessage', array(
                '{{ limit }}' => '0.1',
                '{{ suffix }}' => 'MB',
            ), '100K');
        }

        return $tests;
    }

    abstract protected function getFile($filename);
}
