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
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RangeValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Range) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Range');
        }

        if (null === $value) {
            return;
        }

        if (!is_numeric($value) && !$value instanceof \DateTime && !$value instanceof \DateTimeInterface) {
            if ($this->context instanceof ExecutionContextInterface) {
                $this->context->buildViolation($constraint->invalidMessage)
                    ->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))
                    ->setCode(Range::INVALID_CHARACTERS_ERROR)
                    ->addViolation();
            } else {
                $this->buildViolation($constraint->invalidMessage)
                    ->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))
                    ->setCode(Range::INVALID_CHARACTERS_ERROR)
                    ->addViolation();
            }

            return;
        }

        $min = $constraint->min;
        $max = $constraint->max;

        // Convert strings to DateTimes if comparing another DateTime
        // This allows to compare with any date/time value supported by
        // the DateTime constructor:
        // http://php.net/manual/en/datetime.formats.php
        if ($value instanceof \DateTime || $value instanceof \DateTimeInterface) {
            if (is_string($min)) {
                $min = new \DateTime($min);
            }

            if (is_string($max)) {
                $max = new \DateTime($max);
            }
        }

        if (null !== $constraint->max && $value > $max) {
            if ($this->context instanceof ExecutionContextInterface) {
                $this->context->buildViolation($constraint->maxMessage)
                    ->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))
                    ->setParameter('{{ limit }}', $this->formatValue($max, self::PRETTY_DATE))
                    ->setCode(Range::TOO_HIGH_ERROR)
                    ->addViolation();
            } else {
                $this->buildViolation($constraint->maxMessage)
                    ->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))
                    ->setParameter('{{ limit }}', $this->formatValue($max, self::PRETTY_DATE))
                    ->setCode(Range::TOO_HIGH_ERROR)
                    ->addViolation();
            }

            return;
        }

        if (null !== $constraint->min && $value < $min) {
            if ($this->context instanceof ExecutionContextInterface) {
                $this->context->buildViolation($constraint->minMessage)
                    ->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))
                    ->setParameter('{{ limit }}', $this->formatValue($min, self::PRETTY_DATE))
                    ->setCode(Range::TOO_LOW_ERROR)
                    ->addViolation();
            } else {
                $this->buildViolation($constraint->minMessage)
                    ->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))
                    ->setParameter('{{ limit }}', $this->formatValue($min, self::PRETTY_DATE))
                    ->setCode(Range::TOO_LOW_ERROR)
                    ->addViolation();
            }
        }
    }
}
