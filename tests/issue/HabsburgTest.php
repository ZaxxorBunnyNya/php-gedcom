<?php

namespace GedcomTest\issue;

use Gedcom\Parser;

/**
 * Class Issue00012Test.
 */
class HabsburgTest extends \PHPUnit\Framework\TestCase
{
    public function testBirthDate()
    {
        $sample = realpath(__DIR__.'/files/Habsburg.ged');

        $parser = new Parser();
        $gedcom = $parser->parse($sample);

        $countIndi = count($gedcom->getIndi());

        $this->assertEquals(34020, $countIndi);
    }
}
