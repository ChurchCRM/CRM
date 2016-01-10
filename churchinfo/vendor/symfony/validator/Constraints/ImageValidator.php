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

use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates whether a value is a valid image file and is valid
 * against minWidth, maxWidth, minHeight and maxHeight constraints.
 *
 * @author Benjamin Dulau <benjamin.dulau@gmail.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ImageValidator extends FileValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Image) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Image');
        }

        $violations = count($this->context->getViolations());

        parent::validate($value, $constraint);

        $failed = count($this->context->getViolations()) !== $violations;

        if ($failed || null === $value || '' === $value) {
            return;
        }

        if (null === $constraint->minWidth && null === $constraint->maxWidth
            && null === $constraint->minHeight && null === $constraint->maxHeight
            && null === $constraint->minRatio && null === $constraint->maxRatio
            && $constraint->allowSquare && $constraint->allowLandscape && $constraint->allowPortrait) {
            return;
        }

        $size = @getimagesize($value);

        if (empty($size) || ($size[0] === 0) || ($size[1] === 0)) {
            if ($this->context instanceof ExecutionContextInterface) {
                $this->context->buildViolation($constraint->sizeNotDetectedMessage)
                    ->setCode(Image::SIZE_NOT_DETECTED_ERROR)
                    ->addViolation();
            } else {
                $this->buildViolation($constraint->sizeNotDetectedMessage)
                    ->setCode(Image::SIZE_NOT_DETECTED_ERROR)
                    ->addViolation();
            }

            return;
        }

        $width = $size[0];
        $height = $size[1];

        if ($constraint->minWidth) {
            if (!ctype_digit((string) $constraint->minWidth)) {
                throw new ConstraintDefinitionException(sprintf('"%s" is not a valid minimum width', $constraint->minWidth));
            }

            if ($width < $constraint->minWidth) {
                if ($this->context instanceof ExecutionContextInterface) {
                    $this->context->buildViolation($constraint->minWidthMessage)
                        ->setParameter('{{ width }}', $width)
                        ->setParameter('{{ min_width }}', $constraint->minWidth)
                        ->setCode(Image::TOO_NARROW_ERROR)
                        ->addViolation();
                } else {
                    $this->buildViolation($constraint->minWidthMessage)
                        ->setParameter('{{ width }}', $width)
                        ->setParameter('{{ min_width }}', $constraint->minWidth)
                        ->setCode(Image::TOO_NARROW_ERROR)
                        ->addViolation();
                }

                return;
            }
        }

        if ($constraint->maxWidth) {
            if (!ctype_digit((string) $constraint->maxWidth)) {
                throw new ConstraintDefinitionException(sprintf('"%s" is not a valid maximum width', $constraint->maxWidth));
            }

            if ($width > $constraint->maxWidth) {
                if ($this->context instanceof ExecutionContextInterface) {
                    $this->context->buildViolation($constraint->maxWidthMessage)
                        ->setParameter('{{ width }}', $width)
                        ->setParameter('{{ max_width }}', $constraint->maxWidth)
                        ->setCode(Image::TOO_WIDE_ERROR)
                        ->addViolation();
                } else {
                    $this->buildViolation($constraint->maxWidthMessage)
                        ->setParameter('{{ width }}', $width)
                        ->setParameter('{{ max_width }}', $constraint->maxWidth)
                        ->setCode(Image::TOO_WIDE_ERROR)
                        ->addViolation();
                }

                return;
            }
        }

        if ($constraint->minHeight) {
            if (!ctype_digit((string) $constraint->minHeight)) {
                throw new ConstraintDefinitionException(sprintf('"%s" is not a valid minimum height', $constraint->minHeight));
            }

            if ($height < $constraint->minHeight) {
                if ($this->context instanceof ExecutionContextInterface) {
                    $this->context->buildViolation($constraint->minHeightMessage)
                        ->setParameter('{{ height }}', $height)
                        ->setParameter('{{ min_height }}', $constraint->minHeight)
                        ->setCode(Image::TOO_LOW_ERROR)
                        ->addViolation();
                } else {
                    $this->buildViolation($constraint->minHeightMessage)
                        ->setParameter('{{ height }}', $height)
                        ->setParameter('{{ min_height }}', $constraint->minHeight)
                        ->setCode(Image::TOO_LOW_ERROR)
                        ->addViolation();
                }

                return;
            }
        }

        if ($constraint->maxHeight) {
            if (!ctype_digit((string) $constraint->maxHeight)) {
                throw new ConstraintDefinitionException(sprintf('"%s" is not a valid maximum height', $constraint->maxHeight));
            }

            if ($height > $constraint->maxHeight) {
                if ($this->context instanceof ExecutionContextInterface) {
                    $this->context->buildViolation($constraint->maxHeightMessage)
                        ->setParameter('{{ height }}', $height)
                        ->setParameter('{{ max_height }}', $constraint->maxHeight)
                        ->setCode(Image::TOO_HIGH_ERROR)
                        ->addViolation();
                } else {
                    $this->buildViolation($constraint->maxHeightMessage)
                        ->setParameter('{{ height }}', $height)
                        ->setParameter('{{ max_height }}', $constraint->maxHeight)
                        ->setCode(Image::TOO_HIGH_ERROR)
                        ->addViolation();
                }
            }
        }

        $ratio = round($width / $height, 2);

        if (null !== $constraint->minRatio) {
            if (!is_numeric((string) $constraint->minRatio)) {
                throw new ConstraintDefinitionException(sprintf('"%s" is not a valid minimum ratio', $constraint->minRatio));
            }

            if ($ratio < $constraint->minRatio) {
                if ($this->context instanceof ExecutionContextInterface) {
                    $this->context->buildViolation($constraint->minRatioMessage)
                        ->setParameter('{{ ratio }}', $ratio)
                        ->setParameter('{{ min_ratio }}', $constraint->minRatio)
                        ->setCode(Image::RATIO_TOO_SMALL_ERROR)
                        ->addViolation();
                } else {
                    $this->buildViolation($constraint->minRatioMessage)
                        ->setParameter('{{ ratio }}', $ratio)
                        ->setParameter('{{ min_ratio }}', $constraint->minRatio)
                        ->setCode(Image::RATIO_TOO_SMALL_ERROR)
                        ->addViolation();
                }
            }
        }

        if (null !== $constraint->maxRatio) {
            if (!is_numeric((string) $constraint->maxRatio)) {
                throw new ConstraintDefinitionException(sprintf('"%s" is not a valid maximum ratio', $constraint->maxRatio));
            }

            if ($ratio > $constraint->maxRatio) {
                if ($this->context instanceof ExecutionContextInterface) {
                    $this->context->buildViolation($constraint->maxRatioMessage)
                        ->setParameter('{{ ratio }}', $ratio)
                        ->setParameter('{{ max_ratio }}', $constraint->maxRatio)
                        ->setCode(Image::RATIO_TOO_BIG_ERROR)
                        ->addViolation();
                } else {
                    $this->buildViolation($constraint->maxRatioMessage)
                        ->setParameter('{{ ratio }}', $ratio)
                        ->setParameter('{{ max_ratio }}', $constraint->maxRatio)
                        ->setCode(Image::RATIO_TOO_BIG_ERROR)
                        ->addViolation();
                }
            }
        }

        if (!$constraint->allowSquare && $width == $height) {
            if ($this->context instanceof ExecutionContextInterface) {
                $this->context->buildViolation($constraint->allowSquareMessage)
                    ->setParameter('{{ width }}', $width)
                    ->setParameter('{{ height }}', $height)
                    ->setCode(Image::SQUARE_NOT_ALLOWED_ERROR)
                    ->addViolation();
            } else {
                $this->buildViolation($constraint->allowSquareMessage)
                    ->setParameter('{{ width }}', $width)
                    ->setParameter('{{ height }}', $height)
                    ->setCode(Image::SQUARE_NOT_ALLOWED_ERROR)
                    ->addViolation();
            }
        }

        if (!$constraint->allowLandscape && $width > $height) {
            if ($this->context instanceof ExecutionContextInterface) {
                $this->context->buildViolation($constraint->allowLandscapeMessage)
                    ->setParameter('{{ width }}', $width)
                    ->setParameter('{{ height }}', $height)
                    ->setCode(Image::LANDSCAPE_NOT_ALLOWED_ERROR)
                    ->addViolation();
            } else {
                $this->buildViolation($constraint->allowLandscapeMessage)
                    ->setParameter('{{ width }}', $width)
                    ->setParameter('{{ height }}', $height)
                    ->setCode(Image::LANDSCAPE_NOT_ALLOWED_ERROR)
                    ->addViolation();
            }
        }

        if (!$constraint->allowPortrait && $width < $height) {
            if ($this->context instanceof ExecutionContextInterface) {
                $this->context->buildViolation($constraint->allowPortraitMessage)
                    ->setParameter('{{ width }}', $width)
                    ->setParameter('{{ height }}', $height)
                    ->setCode(Image::PORTRAIT_NOT_ALLOWED_ERROR)
                    ->addViolation();
            } else {
                $this->buildViolation($constraint->allowPortraitMessage)
                    ->setParameter('{{ width }}', $width)
                    ->setParameter('{{ height }}', $height)
                    ->setCode(Image::PORTRAIT_NOT_ALLOWED_ERROR)
                    ->addViolation();
            }
        }
    }
}
