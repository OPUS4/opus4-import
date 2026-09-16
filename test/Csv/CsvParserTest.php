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
        $parser->parseFile(APPLICATION_PATH . '/test/_files/csv/default-example.csv');

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
        $parser->parseFile(APPLICATION_PATH . '/test/_files/csv/default-example.csv');
        $this->assertEquals(13, $parser->getLineCount());
    }

    public function testParseFileWithoutHeader()
    {
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    public function testParseDocument()
    {
        $parser = new CsvParser();
        $parser->parseFile(APPLICATION_PATH . '/test/_files/csv/default-example.csv');

        $doc = $parser->next();

        $this->assertNotNull($doc);
        $this->assertCount(2, $doc->getIdentifier());
        $this->assertCount(1, $doc->getIdentifierOld());
        $this->assertEquals('oldId1', $doc->getIdentifierOld()[0]->getValue());

        $this->assertEquals('deu', $doc->getLanguage());
        $this->assertEquals('Article', $doc->getType());
        $this->assertEquals(Document::STATE_PUBLISHED, $doc->getServerState());

        $titleMain = $doc->getTitleMain();
        $this->assertCount(1, $titleMain);
        $this->assertEquals('Deutscher Haupttitel', $titleMain[0]->getValue());
        $this->assertEquals('deu', $titleMain[0]->getLanguage());

        $titleAbstract = $doc->getTitleAbstract();
        $this->assertCount(1, $titleAbstract);
        $this->assertEquals('Zusammenfassung1', $titleAbstract[0]->getValue());
        $this->assertEquals('deu', $titleAbstract[0]->getLanguage());

        $titleParent = $doc->getTitleParent();
        $this->assertCount(1, $titleParent);
        $this->assertEquals('Collection of German titles used for testing', $titleParent[0]->getValue());
        $this->assertEquals('eng', $titleParent[0]->getLanguage());

        $author = $doc->getPerson();
        $this->assertCount(1, $author);
        $this->assertEquals('Michael', $author[0]->getFirstName());
        $this->assertEquals('Mustermann', $author[0]->getLastName());

        $this->assertEquals(1933, $doc->getPublishedYear());

        $this->assertCount(1, $doc->getIdentifierOpac());
        $this->assertEquals('opacId1', $doc->getIdentifierOpac()[0]->getValue());

        $this->assertCount(1, $doc->getNote());
        $this->assertEquals('public', $doc->getNote()[0]->getVisibility());
        $this->assertEquals('note1', $doc->getNote()[0]->getMessage());

        // TODO check collections
        // TODO check series

        $this->assertEquals('vol1', $doc->getVolume());

        // TODO check licence

        $enrichment = $doc->getEnrichmentValue('availability');

        // TODO check File
    }
}
