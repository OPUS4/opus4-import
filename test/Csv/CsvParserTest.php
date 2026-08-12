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
use Opus\Import\Csv\CsvParser;
use OpusTest\Import\TestAsset\TestCase;

class CsvParserTest extends TestCase
{
    public function testParseFile()
    {
        $parser = new CsvParser();
        $parser->parseFile(APPLICATION_PATH . '/Test-CSV-Import.csv');

        $documentCount = 0;

        do {
            $doc = $parser->next();
            if ($doc !== null) {
                $documentCount++;
            }
        } while ($doc !== null);

        $this->assertEquals(12, $documentCount);
    }

    public function testGetLineCount()
    {
        $parser = new CsvParser();
        $parser->parseFile(APPLICATION_PATH . '/Test-CSV-Import.csv');
        $this->assertEquals(13, $parser->getLineCount());
    }

    public function testParseFileWithoutHeader()
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    public function testParseDocument()
    {
        $parser = new CsvParser();
        $parser->parseFile(APPLICATION_PATH . '/Test-CSV-Import.csv');

        $doc = $parser->next();

        $this->assertNotNull($doc);
        $this->assertCount(1, $doc->getIdentifier());

        $this->assertEquals('zho', $doc->getLanguage());
        $this->assertEquals('Article', $doc->getType());
        $this->assertEquals(Document::STATE_PUBLISHED, $doc->getServerState());
    }
}
