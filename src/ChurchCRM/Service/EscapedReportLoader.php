<?php

namespace ChurchCRM\Service;

use Twig\Loader\LoaderInterface;
use Twig\Source;

final class EscapedReportLoader implements LoaderInterface
{
    public function __construct(private readonly LoaderInterface $loader)
    {
    }

    public function getSourceContext(string $name): Source
    {
        return $this->loader->getSourceContext($name);
    }

    public function getCacheKey(string $name): string
    {
        // Do not reuse a template class compiled in an unescaped injected environment.
        return 'churchcrm-html-escaped:' . $this->loader->getCacheKey($name);
    }

    public function isFresh(string $name, int $time): bool
    {
        return $this->loader->isFresh($name, $time);
    }

    public function exists(string $name): bool
    {
        return $this->loader->exists($name);
    }
}
