<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

/**
 * Default implementation of {@ConstraintViolationListInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ConstraintViolationList implements \IteratorAggregate, ConstraintViolationListInterface
{
    /**
     * @var ConstraintViolationInterface[]
     */
    private $violations = array();

    /**
     * Creates a new constraint violation list.
     *
     * @param ConstraintViolationInterface[] $violations The constraint violations to add to the list
     */
    public function __construct(array $violations = array())
    {
        foreach ($violations as $violation) {
            $this->add($violation);
        }
    }

    /**
     * Converts the violation into a string for debugging purposes.
     *
     * @return string The violation as string.
     */
    public function __toString()
    {
        $string = '';

        foreach ($this->violations as $violation) {
            $string .= $violation."\n";
        }

        return $string;
    }

    /**
     * {@inheritdoc}
     */
    public function add(ConstraintViolationInterface $violation)
    {
        $this->violations[] = $violation;
    }

    /**
     * {@inheritdoc}
     */
    public function addAll(ConstraintViolationListInterface $otherList)
    {
        foreach ($otherList as $violation) {
            $this->violations[] = $violation;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get($offset)
    {
        if (!isset($this->violations[$offset])) {
            throw new \OutOfBoundsException(sprintf('The offset "%s" does not exist.', $offset));
        }

        return $this->violations[$offset];
    }

    /**
     * {@inheritdoc}
     */
    public function has($offset)
    {
        return isset($this->violations[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function set($offset, ConstraintViolationInterface $violation)
    {
        $this->violations[$offset] = $violation;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($offset)
    {
        unset($this->violations[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->violations);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->violations);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $violation)
    {
        if (null === $offset) {
            $this->add($violation);
        } else {
            $this->set($offset, $violation);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }
}
