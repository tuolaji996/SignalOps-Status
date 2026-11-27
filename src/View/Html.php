<?php
declare(strict_types=1);

namespace SignalOps\Status\View;

/** A trusted HTML fragment. Ui helpers return these; View::e() passes them through unescaped. */
final class Html implements \Stringable
{
    public function __construct(private readonly string $html)
    {
    }

    public function __toString(): string
    {
        return $this->html;
    }
}
