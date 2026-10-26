<?php

/**
 * This file is part of OPUS. The software OPUS has been originally developed
 * at the University of Stuttgart with funding from the German Research Net,
 * the Federal Department of Higher Education and Research and the Ministry
 * of Science, Research and the Arts of the State of Baden-Wuerttemberg.
 *
 * OPUS 4 is a complete rewrite of the original OPUS software and was developed
 * by the Stuttgart University Library, the Library Service Center
 * Baden-Wuerttemberg, the Cooperative Library Network Berlin-Brandenburg,
 * the Saarland University and State Library, the Saxon State Library -
 * Dresden State and University Library, the Bielefeld University Library and
 * the University Library of Hamburg University of Technology with funding from
 * the German Research Foundation and the European Regional Development Fund.
 *
 * LICENCE
 * OPUS is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or any later version.
 * OPUS is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details. You should have received a copy of the GNU General Public License
 * along with OPUS; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * @copyright   Copyright (c) 2026, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace OpusTest\Import\Csv;

use Opus\Common\Document;
use Opus\Common\Licence;
use Opus\Common\LicenceInterface;
use Opus\Import\Csv\CsvLicenceProcessor;
use OpusTest\Import\TestAsset\TestCase;

class CsvLicenceProcessorTest extends TestCase
{
    /** @var CsvLicenceProcessor */
    private $processor;

    /** @var LicenceInterface */
    private $licenceCcBy;

    /** @var LicenceInterface */
    private $licenceCcBySa;

    public function setUp(): void
    {
        parent::setUp();

        $this->processor = new CsvLicenceProcessor();

        $licence = Licence::new();
        $licence->setName('CC BY 4.0');
        $licence->setNameLong('Creative Commons - CC BY - Namensnennung 4.0 International');
        $licence->setLinkLicence('https://creativecommons.org/licenses/by/4.0/deed.de');
        $licence->store();
        $this->licenceCcBy = $licence;

        $licence = Licence::fromArray([
            'Name'        => 'CC BY-SA 4.0',
            'NameLong'    => 'Creative Commons - CC BY-SA - Namensnennung - Weitergabe unter gleichen Bedingungen 4.0 International',
            'LinkLicence' => 'https://creativecommons.org/licenses/by-sa/4.0/deed.de',
        ]);
        $licence->store();
        $this->licenceCcBySa = $licence;
    }

    public function testProcessLicenceId()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);

        $doc = Document::new();
        $row = [$this->licenceCcBy->getId()];
        $processor->process($row, $doc);

        $this->assertCount(1, $doc->getLicence());
        $this->assertEquals('CC BY 4.0', $doc->getLicence()[0]->getName());
    }

    public function testProcessMultipleLicences()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);

        $doc = Document::new();
        $row = [$this->licenceCcBy->getId() . ' || ' . $this->licenceCcBySa->getId()];
        $processor->process($row, $doc);

        $this->assertCount(2, $doc->getLicence());
        $this->assertEquals('CC BY 4.0', $doc->getLicence()[0]->getName());
        $this->assertEquals('CC BY-SA 4.0', $doc->getLicence()[1]->getName());
    }

    public function testProcessUnknownLicenceId()
    {
        $processor = $this->processor;
        $processor->setColumnNo(0);

        $doc = Document::new();
        $row = ['9999'];

        $this->expectExceptionMessage('does not exist');
        $processor->process($row, $doc);
    }
}
