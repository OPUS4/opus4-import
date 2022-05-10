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
 * @copyright   Copyright (c) 2018-2022, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace OpusTest\Import;

use Opus\Common\Log;
use Opus\Document;
use Opus\EnrichmentKey;
use Opus\Import\Importer;
use Opus\Import\Xml\MetadataImportSkippedDocumentsException;
use OpusTest\Import\TestAsset\TestCase;

use function file_get_contents;

class ImporterTest extends TestCase
{
    protected $additionalResources = 'database';

    public function setUp()
    {
        parent::setUp();

        $enrichmentKey = EnrichmentKey::new();
        $enrichmentKey->setName('City');
        $enrichmentKey->store();

        $enrichmentKey = EnrichmentKey::new();
        $enrichmentKey->setName('validtestkey');
        $enrichmentKey->store();
    }

    public function tearDown()
    {
        $enrichmentKey = EnrichmentKey::fetchByName('City');
        if ($enrichmentKey !== null) {
            $enrichmentKey->delete();
        }

        $enrichmentKey = EnrichmentKey::fetchByName('validtestkey');
        if ($enrichmentKey !== null) {
            $enrichmentKey->delete();
        }

        parent::tearDown();
    }

    public function testImportEnrichmentWithoutValue()
    {
        $xml = file_get_contents(APPLICATION_PATH . '/test/_files/test_import_enrichment_without_value.xml');

        $importer = new Importer($xml, false, Log::get());

        $importer->run();

        $document = $importer->getDocument();

        $this->assertNotNull($document);
        $this->assertInstanceOf(Document::class, $document);

        $this->assertCount(1, $document->getEnrichment());
        $this->assertEquals('Berlin', $document->getEnrichmentValue('City'));
    }

    /**
     * Bei der Angabe eines EmbargoDate im Import-XML muss eine Tages- und Monatsangabe sowie
     * eine Jahresangabe enthalten sein. Eine alleinige Jahresangabe ist nicht zulässig.
     */
    public function testImportInvalidEmbargoDate()
    {
        $xml = file_get_contents(APPLICATION_PATH . '/test/_files/incomplete-embargo-date.xml');

        $importer = new Importer($xml, false, Log::get());

        $this->expectException(MetadataImportSkippedDocumentsException::class);
        $importer->run();
    }

    public function testValidEmbargoDate()
    {
        $xml = file_get_contents(APPLICATION_PATH . '/test/_files/embargo-date.xml');

        $importer = new Importer($xml, false, Log::get());

        $importer->run();

        $document = $importer->getDocument();

        $this->assertNotNull($document);
        $this->assertInstanceOf(Document::class, $document);

        $embargoDate = $document->getEmbargoDate();
        $this->assertEquals(12, $embargoDate->getDay());
        $this->assertEquals(11, $embargoDate->getMonth());
        $this->assertEquals(2042, $embargoDate->getYear());
    }

    public function testFromArray()
    {
        $this->markTestIncomplete('Test for debugging - TODO expand');
        $doc = Document::get(146);

        // var_dump($doc->toArray());
    }
}
