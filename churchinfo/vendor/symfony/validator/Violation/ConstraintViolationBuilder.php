<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Violation;

use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Util\PropertyPath;

/**
 * Default implementation of {@link ConstraintViolationBuilderInterface}.
 *
 * @since  2.5
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal You should not instantiate or use this class. Code against
 *           {@link ConstraintViolationBuilderInterface} instead.
 */
class ConstraintViolationBuilder implements ConstraintViolationBuilderInterface
{
    /**
     * @var ConstraintViolationList
     */
    private $violations;

    /**
     * @var string
     */
    private $message;

    /**
     * @var array
     */
    private $parameters;

    /**
     * @var mixed
     */
    private $root;

    /**
     * @var mixed
     */
    private $invalidValue;

    /**
     * @var string
     */
    private $propertyPath;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var string|null
     */
    private $translationDomain;

    /**
     * @var int|null
     */
    private $plural;

    /**
     * @var Constraint
     */
    private $constraint;

    /**
     * @var mixed
     */
    private $code;

    /**
     * @var mixed
     */
    private $cause;

    public function __construct(ConstraintViolationList $violations, Constraint $constraint, $message, array $parameters, $root, $propertyPath, $invalidValue, TranslatorInterface $translator, $translationDomain = null)
    {
        $this->violations = $violations;
        $this->message = $message;
        $this->parameters = $parameters;
        $this->root = $root;
        $this->propertyPath = $propertyPath;
        $this->invalidValue = $invalidValue;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
        $this->constraint = $constraint;
    }

    /**
     * {@inheritdoc}
     */
    public function atPath($path)
    {
        $this->propertyPath = PropertyPath::append($this->propertyPath, $path);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setParameter($key, $value)
    {
        $this->parameters[$key] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setTranslationDomain($translationDomain)
    {
        $this->translationDomain = $translationDomain;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setInvalidValue($invalidValue)
    {
        $this->invalidValue = $invalidValue;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setPlural($number)
    {
        $this->plural = $number;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setCause($cause)
    {
        $this->cause = $cause;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addViolation()
    {
        if (null === $this->plural) {
            $translatedMessage = $this->translator->trans(
                $this->message,
                $this->parameters,
                $this->translationDomain
            );
        } else {
            try {
                $translatedMessage = $this->translator->transChoice(
                    $this->message,
                    $this->plural,
                    $this->parameters,
                    $this->translationDomain#
                );
            } catch (\InvalidArgumentException $e) {
                $translatedMessage = $this->translator->trans(
                    $this->message,
                    $this->parameters,
                    $this->translationDomain
                );
            }
        }

        $this->violations->add(new ConstraintViolation(
            $translatedMessage,
            $this->message,
            $this->parameters,
            $this->root,
            $this->propertyPath,
            $this->invalidValue,
            $this->plural,
            $this->code,
            $this->constraint,
            $this->cause
        ));
    }
}
