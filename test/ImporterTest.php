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
 * @copyright   Copyright (c) 2018, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace OpusTest\Import;

use Opus\Common\Document;
use Opus\Common\DocumentInterface;
use Opus\Common\EnrichmentKey;
use Opus\Common\Log;
use Opus\Common\Model\ModelException;
use Opus\Common\Security\SecurityException;
use Opus\Common\Subject;
use Opus\Import\Importer;
use Opus\Import\Xml\MetadataImportInvalidXmlException;
use Opus\Import\Xml\MetadataImportSkippedDocumentsException;
use OpusTest\Import\TestAsset\TestCase;
use Zend_Exception;

use function file_get_contents;

class ImporterTest extends TestCase
{
    /** @var string */
    protected $additionalResources = 'database';

    public function setUp(): void
    {
        parent::setUp();

        $enrichmentKey = EnrichmentKey::new();
        $enrichmentKey->setName('City');
        $enrichmentKey->store();

        $enrichmentKey = EnrichmentKey::new();
        $enrichmentKey->setName('validtestkey');
        $enrichmentKey->store();
    }

    public function tearDown(): void
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
        $this->assertInstanceOf(DocumentInterface::class, $document);

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
        $this->assertInstanceOf(DocumentInterface::class, $document);

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

    /**
     * @return DocumentInterface
     * @throws MetadataImportSkippedDocumentsException
     * @throws ModelException
     * @throws SecurityException
     * @throws MetadataImportInvalidXmlException
     * @throws Zend_Exception
     */
    protected function getTestImportDocument()
    {
        $xml = file_get_contents(APPLICATION_PATH . '/test/_files/import-rules-test.xml');

        $importer = new Importer($xml, false, Log::get());

        $importer->run();

        $document = $importer->getDocument();

        $this->assertNotNull($document);
        $this->assertInstanceOf(DocumentInterface::class, $document);

        return $document;
    }

    public function testImport()
    {
        $document = $this->getTestImportDocument();

        $authors = $document->getPersonAuthor();

        $this->assertCount(1, $authors);

        $this->assertEquals('deu', $document->getLanguage());
        $this->assertEquals('Der Titel', $document->getMainTitle()->getValue());
    }

    public function testImportRulesAddCollectionByAccount()
    {
        $doc = $this->getTestImportDocument();
    }

    public function testImportRulesAddCollectionForKeyword()
    {
        // TODO add/enable rules (use global config fallback?)
    }

    public function testImportRulesAddLicenceForKeyword()
    {
    }

    public function testImportRulesRemoveKeyword()
    {
    }

    public function testImportRulesAddCollectionAndRemoveKeyword()
    {
    }

    public function testImportRulesDisabled()
    {
        $doc = $this->getTestImportDocument();
        $this->assertFalse($doc->hasSubject('RulesEnabled'));
    }

    public function testImportRulesEnabled()
    {
        $this->adjustConfiguration([
            'sword' => [
                'enableImportRules' => true,
            ],
        ]);
        $doc = $this->getTestImportDocument();
        $this->assertTrue($doc->hasSubject('RulesEnabled'));
    }

    public function testKeywordTypeDefaultUncontrolled()
    {
        $doc = $this->getTestImportDocument();

        $keywords = $doc->getSubject();

        $this->assertCount(3, $keywords);

        $this->assertTrue($doc->hasSubject('oa-green', Subject::TYPE_UNCONTROLLED));
        $this->assertTrue($doc->hasSubject('oa-gold', Subject::TYPE_UNCONTROLLED));
    }
}
