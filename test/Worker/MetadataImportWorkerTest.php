<?php

/*
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

namespace OpusTest\Import\Worker;

use DOMDocument;
use Exception;
use Opus\Common\Job;
use Opus\Import\Worker\MetadataImportWorker;
use Opus\Import\Xml\MetadataImportInvalidXmlException;
use Opus\Import\Xml\MetadataImportSkippedDocumentsException;
use Opus\Job\InvalidJobException;
use OpusTest\Import\TestAsset\TestCase;

use function dirname;
use function get_class;

class MetadataImportWorkerTest extends TestCase
{
    /** @var string */
    private $filename;

    /** @var Job */
    private $job;

    /** @var MetadataImportWorker */
    private $worker;

    /** @var string|null */
    private $xml;

    /** @var string */
    private $xmlDir;

    public function setUp(): void
    {
        parent::setUp();
        $this->job    = Job::new();
        $this->worker = new MetadataImportWorker();
        $this->xml    = null;
        $this->xmlDir = dirname(dirname(__FILE__)) . '/_files/xml/';
    }

    public function testActivationLabel()
    {
         $this->assertEquals(MetadataImportWorker::LABEL, $this->worker->getActivationLabel());
    }

    public function testWrongLabelException()
    {
        $this->job->setLabel('wrong-label');
        $this->job->setData(['xml' => $this->xml]);
        $this->expectException(InvalidJobException::class);
        $this->worker->work($this->job);
    }

    public function testMissingDataException()
    {
        $this->job->setLabel('opus-metadata-import');
        $this->job->setData([]);
        $this->expectException(InvalidJobException::class);
        $this->worker->work($this->job);
    }

    public function testIncompleteDataException()
    {
        $this->job->setLabel('opus-metadata-import');
        $this->job->setData(['xml' => $this->xml]);
        $this->expectException(InvalidJobException::class);
        $this->worker->work($this->job);
    }

    public function testInvalidXmlException()
    {
        $this->filename = 'test_import_schemainvalid.xml';
        $this->loadInputFile();
        $this->job->setLabel('opus-metadata-import');
        $this->job->setData(['xml' => $this->xml]);
        $this->expectException(MetadataImportInvalidXmlException::class);
        $this->worker->work($this->job);
    }

    public function testSkippedDocumentException()
    {
        $this->filename = 'test_import_invalid_collectionid.xml';
        $this->loadInputFile();
        $this->job->setLabel('opus-metadata-import');
        $this->job->setData(['xml' => $this->xml]);
        $this->expectException(MetadataImportSkippedDocumentsException::class);
        $this->worker->work($this->job);
    }

    public function testImportValidXml()
    {
        $this->filename = 'test_import_minimal.xml';
        $this->loadInputFile();
        $this->job->setLabel('opus-metadata-import');
        $this->job->setData(['xml' => $this->xml]);

        $e = null;
        try {
            $this->worker->work($this->job);
        } catch (Exception $ex) {
            $e = $ex;
        }
        $exceptionClass = $e !== null ? get_class($e) : '';
        $this->assertNull($e, 'unexpected exception was thrown: ' . $exceptionClass);
    }

    private function loadInputFile()
    {
        $xml = new DOMDocument();
        $xml->load($this->xmlDir . $this->filename);
        $this->xml = $xml->saveXML();
    }
}
