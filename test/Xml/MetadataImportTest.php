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
 * @copyright   Copyright (c) 2008, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace OpusTest\Import\Xml;

use DOMDocument;
use Exception;
use Opus\Common\Document;
use Opus\Common\Model\NotFoundException;
use Opus\Common\Repository;
use Opus\Db\Util\DatabaseHelper;
use Opus\Import\Xml\MetadataImport;
use Opus\Import\Xml\MetadataImportInvalidXmlException;
use Opus\Import\Xml\MetadataImportSkippedDocumentsException;
use OpusTest\Import\TestAsset\TestCase;

use function array_pop;
use function count;
use function dirname;
use function get_class;

/**
 * TODO get rid of class variables that are not needed, like $xml
 */
class MetadataImportTest extends TestCase
{
    /** @var bool */
    private $documentImported;

    /** @var string */
    private $filename;

    /** @var string */
    private $xml;

    /** @var string */
    private $xmlDir;

    public function setUp(): void
    {
        parent::setUp();

        $this->documentImported = false;
        $this->xmlDir           = dirname(dirname(__FILE__)) . '/_files/xml/';
    }

    public function tearDown(): void
    {
        $finder = Repository::getInstance()->getDocumentFinder();

        if ($this->documentImported) {
            $ids    = $finder->getIds();
            $lastId = array_pop($ids);
            $doc    = Document::new($lastId);
            $doc->delete();
        }
        parent::tearDown();
    }

    public function testInvalidXmlExceptionWhenNotWellFormed()
    {
        $importer = new MetadataImport('This ist no XML');
        $this->expectException(MetadataImportInvalidXmlException::class);
        $importer->run();
    }

    public function testInvalidXmlExceptionWhenNotWellFormedWithFile()
    {
        $importer = new MetadataImport($this->xmlDir . 'test_import_badformed.xml', true);
        $this->expectException(MetadataImportInvalidXmlException::class);
        $importer->run();
    }

    public function testInvalidXmlException()
    {
        $this->filename = 'test_import_schemainvalid.xml';
        $this->loadInputFile();
        $importer = new MetadataImport($this->xml);

        $this->expectException(MetadataImportInvalidXmlException::class);
        $importer->run();
    }

    public function testNoMetadataImportException()
    {
        $this->filename = 'test_import_minimal.xml';
        $this->loadInputFile();
        $importer = new MetadataImport($this->xml);

        $e = null;
        try {
            $importer->run();
        } catch (MetadataImportInvalidXmlException $ex) {
            $e = $ex;
        } catch (MetadataImportSkippedDocumentsException $ex) {
            $e = $ex;
        }

        if ($e !== null) {
            $exceptionClass = get_class($e);
        } else {
            $exceptionClass = '';
        }

        $this->assertNull($e, 'unexpected exception was thrown: ' . $exceptionClass);

        $this->documentImported = true;
    }

    public function testImportOfDocumentAttributes()
    {
        $this->filename = 'test_import_document_attributes.xml';
        $this->loadInputFile();
        $importer = new MetadataImport($this->xml);
        $importer->run();

        $finder = Repository::getInstance()->getDocumentFinder();
        $docId  = $finder->getIds()[0];
        $doc    = Document::get($docId);
        $this->assertEquals(1, $doc->getPageFirst());
        $this->assertEquals(2, $doc->getPageLast());
        $this->assertEquals(3, $doc->getPageNumber());
        $this->assertEquals(4, $doc->getArticleNumber());

        $this->documentImported = true;
    }

    public function testSkippedDocumentsException()
    {
        $this->filename = 'test_import_invalid_collectionid.xml';
        $this->loadInputFile();
        $importer = new MetadataImport($this->xml);

        $this->expectException(MetadataImportSkippedDocumentsException::class);
        $importer->run();
    }

    /**
     * Test for document update
     */
    public function testUpdateDocument()
    {
        $databaseHelper = new DatabaseHelper();
        $databaseHelper->clearTables(true);

        $this->filename = 'test_import_minimal.xml';
        $this->loadInputFile();
        $importer = new MetadataImport($this->xml);

        $importer->run();

        try {
            $importedDoc = Document::get(1);
            $titleMain   = $importedDoc->getTitleMain();
            $this->assertCount(1, $titleMain);
            $this->assertEquals('La Vie un Rose', $titleMain[0]->getValue());
        } catch (NotFoundException $e) {
            $this->fail("Import failed");
        }

        $this->filename = 'test_import_minimal_update1.xml';
        $this->loadInputFile();

        $importer = new MetadataImport($this->xml);
        $importer->run();

        $updatedDoc = Document::get(1);
        $titleMain  = $updatedDoc->getTitleMain();
        $abstracts  = $updatedDoc->getTitleAbstract();

        $this->assertCount(1, $titleMain);
        $this->assertEquals('La Vie en Rose', $titleMain[0]->getValue(), "Update failed");
        $this->assertCount(1, $abstracts, 'Expected 1 abstract after update');
    }

    /**
     * Regression Test for OPUSVIER-3211
     */
    public function testUpdateKeepField()
    {
        $this->filename = 'test_import_minimal.xml';
        $this->loadInputFile();
        $importer = new MetadataImport($this->xml);

        $importer->run();

        $this->filename = 'test_import_minimal_update1.xml';
        $this->loadInputFile();
        $importer = new MetadataImport($this->xml);

        $importer->run();
        try {
            $importedDoc = Document::get(1);
            $titleMain   = $importedDoc->getTitleMain();
            $this->assertCount(1, $titleMain);
            $this->assertEquals('La Vie en Rose', $titleMain[0]->getValue());
        } catch (NotFoundException $e) {
            $this->fail("Import failed");
        }

        $this->filename = 'test_import_minimal_update2.xml';
        $this->loadInputFile();

        $importer = new MetadataImport($this->xml);
        $importer->keepFieldsOnUpdate(['TitleAbstract']);
        $importer->run();

        $updatedDoc = Document::get(1);
        $abstracts  = $updatedDoc->getTitleAbstract();

        $this->assertEquals(2, count($abstracts), 'Expected 2 abstracts after update');
    }

    /**
     * Regression Test for OPUSVIER-3204
     */
    public function testSkippedDocumentsExceptionOnUpdateDoesNotDestroyExistingDocument()
    {
        $this->filename = 'test_import_minimal.xml';
        $this->loadInputFile();
        $importer = new MetadataImport($this->xml);

        $importer->run();
        try {
            $importedDoc = Document::get(1);
            $titleMain   = $importedDoc->getTitleMain();
            $this->assertCount(1, $titleMain);
            $this->assertEquals('La Vie un Rose', $titleMain[0]->getValue());
        } catch (NotFoundException $e) {
            $this->fail("Import failed");
        }
        $this->filename = 'test_import_minimal_corrupted_update.xml';
        $this->loadInputFile();
        $importer          = new MetadataImport($this->xml);
        $expectedException = false;
        try {
            $importer->run();
        } catch (NotFoundException $e) {
            $this->fail("Document was deleted during update.");
        } catch (MetadataImportSkippedDocumentsException $e) {
            // expected exception
            $expectedException = true;
        } catch (Exception $e) {
            $this->fail('unexpected exception was thrown: ' . get_class($e));
        }

        $this->assertTrue($expectedException, "The expected exception did not occur.");

        $updatedDoc = Document::get(1);
        $titleMain  = $updatedDoc->getTitleMain();
        $this->assertNotEmpty($titleMain, 'Existing Document was corrupted on failed update attempt.');
        $this->assertEquals(
            'La Vie un Rose',
            $titleMain[0]->getValue(),
            "Failed update recovery failed. TitleMain was modified."
        );
    }

    private function loadInputFile()
    {
        $doc = new DOMDocument();
        $doc->load($this->xmlDir . $this->filename);
        $this->xml = $doc->saveXML();
    }

    /**
     * Testet ob true/false und 0/1 als Wert für allowEmailContact akzeptiert wird.
     * Regressiontest für OPUSVIER-2570.
     */
    public function testGetAllowEmailContact()
    {
        $this->filename = 'test_import_regression2570.xml';
        $this->loadInputFile();
        $importer = new MetadataImport($this->xml);

        $importer->run();
        $importedDoc = Document::get(1);
        $authors     = $importedDoc->getPersonAuthor();

        $this->assertCount(1, $authors);
        $this->assertEquals(1, $authors[0]->getAllowEmailContact());

        $contributors = $importedDoc->getPersonContributor();
        $this->assertCount(1, $contributors);
        $this->assertEquals(0, $contributors[0]->getAllowEmailContact());

        $editor = $importedDoc->getPersonEditor();
        $this->assertCount(1, $editor);
        $this->assertEquals(0, $editor[0]->getAllowEmailContact());

        $referee = $importedDoc->getPersonReferee();
        $this->assertCount(1, $referee);
        $this->assertEquals(0, $referee[0]->getAllowEmailContact());

        $advisor = $importedDoc->getPersonAdvisor();
        $this->assertCount(1, $advisor);
        $this->assertEquals(1, $advisor[0]->getAllowEmailContact());
    }

    /**
     * Regressiontest OPUSVIER-3323
     */
    public function testSupportPersonOther()
    {
        $this->filename = 'test_import_regression2570.xml';
        $this->loadInputFile();
        $importer = new MetadataImport($this->xml);

        $importer->run();
        $importedDoc = Document::get(1);

        $other = $importedDoc->getPersonOther();
        $this->assertCount(1, $other);
        $this->assertEquals('Janet', $other[0]->getFirstName());
        $this->assertEquals('Doe', $other[0]->getLastName());
    }

    /**
     * Testet ob true/false und 0/1 als Wert für BelongsToBibliography akzeptiert wird.
     * Regressiontest für OPUSVIER-2570 und OPUSVIER-3323.
     */
    public function testBelongsToBibliography()
    {
        $this->filename = 'test_import_regression2570.xml';
        $this->loadInputFile();
        $importer = new MetadataImport($this->xml);

        $importer->run();

        $importedDoc = Document::get(1);
        $this->assertEquals(1, $importedDoc->getField('BelongsToBibliography')->getValue()); // "true" in XML

        $importedDoc = Document::get(2);
        $this->assertEquals(1, $importedDoc->getField('BelongsToBibliography')->getValue()); // "1"

        $importedDoc = Document::get(3);
        $this->assertEquals(0, $importedDoc->getField('BelongsToBibliography')->getValue()); // "false"

        $importedDoc = Document::get(4);
        $this->assertEquals(0, $importedDoc->getField('BelongsToBibliography')->getValue()); // "0"
    }
}
