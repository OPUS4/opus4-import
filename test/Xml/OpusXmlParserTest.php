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

namespace OpusTest\Import\Xml;

use Opus\Import\Xml\OpusXmlParser;
use OpusTest\Import\TestAsset\TestCase;

use function file_get_contents;

class OpusXmlParserTest extends TestCase
{
    private OpusXmlParser $parser;

    public function setUp(): void
    {
        parent::setUp();

        $this->parser = new OpusXmlParser();
    }

    public function testParseFile()
    {
        $parser = $this->parser;

        $testFilePath = APPLICATION_PATH . '/test/_files/xml/test_import_multiple_documents.xml';

        $parser->parseFile($testFilePath);

        $this->assertEquals(2, $parser->getDocumentCount());

        $document = $parser->next();
        $this->assertNotNull($document);
        $this->assertEquals('La Vie un Rose', $document->getMainTitle()->getValue());

        $document = $parser->next();
        $this->assertNotNull($document);
        $this->assertEquals('Ein Titel in Deutsch', $document->getMainTitle()->getValue());

        $this->assertNull($parser->next());
    }

    public function testParse()
    {
        $parser       = $this->parser;
        $testFilePath = APPLICATION_PATH . '/test/_files/xml/test_import_minimal.xml';
        $xml          = file_get_contents($testFilePath);

        $parser->parse($xml);

        $this->assertEquals(1, $parser->getDocumentCount());

        $document = $parser->next();
        $this->assertNotNull($document);
        $this->assertEquals('La Vie un Rose', $document->getMainTitle()->getValue());

        $this->assertNull($parser->next());
        $this->assertNull($parser->next()); // checking if multiple NULL calls are possible
    }

    public function testReusingParserObject()
    {
        $this->markTestIncomplete('not implemented yet');
    }

    public function testDocumentFilesAdded()
    {
        $this->markTestIncomplete('not implemented yet.');
    }

    public function testGetCurrentLineNo()
    {
        $parser       = $this->parser;
        $testFilePath = APPLICATION_PATH . '/test/_files/xml/test_import_multiple_documents.xml';

        $parser->parseFile($testFilePath);

        $this->assertEquals(2, $parser->getDocumentCount());

        $document = $parser->next();
        $this->assertNotNull($document);
        $this->assertEquals(7, $parser->getCurrentLineNo());

        $document = $parser->next();
        $this->assertNotNull($document);
        $this->assertEquals(16, $parser->getCurrentLineNo());

        $this->assertNull($parser->next());
    }

    public function testUpdateDocument()
    {
        $this->markTestIncomplete('Test not implemented yet');
        // TODO create test document
        // TODO update document from file
        // TODO check changes
    }
}
