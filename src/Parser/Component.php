<?php

declare(strict_types=1);

namespace Gedcom\Parser;

abstract class Component
{
    abstract public static function parse(\Gedcom\Parser $parser);
}