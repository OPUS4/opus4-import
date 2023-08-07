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
 * @copyright   Copyright (c) 2008-2022, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace OpusTest\Import;

use DirectoryIterator;
use Opus\Import\Xml\MetadataImportInvalidXmlException;
use Opus\Import\Xml\XmlDocument;
use OpusTest\Import\TestAsset\TestCase;

use function file_get_contents;
use function strpos;

class XmlDocumentTest extends TestCase
{
    /**
     * Check if all 'import*.xml' files are valid.
     */
    public function testValidation()
    {
        foreach (new DirectoryIterator(APPLICATION_PATH . '/test/_files') as $fileInfo) {
            if (
                $fileInfo->getExtension() === 'xml' && ! $fileInfo->isDot()
                    && strpos($fileInfo->getBasename(), 'import') === 0
            ) {
                $xml = file_get_contents($fileInfo->getRealPath());
                $this->checkValid($xml, $fileInfo->getBasename());
            }
        }
    }

    public function testValidation2()
    {
        $xml = file_get_contents(APPLICATION_PATH . '/test/_files/import2.xml');
        $this->checkValid($xml, 'import2.xml');
    }

    /**
     * TODO Check if all 'invalid-import*.xml' files are invalid.
     */
    public function testInvalid()
    {
        $xml = file_get_contents(APPLICATION_PATH . '/test/_files/invalid-import1.xml');

        $this->expectException(MetadataImportInvalidXmlException::class);

        $xmlDocument = new XmlDocument();
        $xmlDocument->loadXML($xml);
        $xmlDocument->validate();
        $this->assertCount(1, $xmlDocument->getErrors());
    }

    public function testEnrichmentWithoutValueValid()
    {
        $xml = file_get_contents(APPLICATION_PATH . '/test/_files/enrichment-without-value.xml');

        $xmlDocument = new XmlDocument();

        try {
            $xmlDocument->loadXML($xml);
            $xmlDocument->validate();
        } catch (MetadataImportInvalidXmlException $e) {
            $this->fail("XML is not valid.");
        }

        $this->assertCount(0, $xmlDocument->getErrors());
    }

    /**
     * In XML Schema 1.0 lässt sich die Forderung, dass bei der Angabe eines Embargo-Date
     * sowohl das Attribute monthDay und year angegeben werden muss, nicht definieren.
     *
     * Daher wird ein Dokument, in dem für das Embargo-Date nur die Jahresangabe enthalten
     * ist, als valide betrachtet.
     */
    public function testIncompleteEmbargoDateMissingMonthDay()
    {
        $xml = file_get_contents(APPLICATION_PATH . '/test/_files/incomplete-embargo-date.xml');

        $xmlDocument = new XmlDocument();

        try {
            $xmlDocument->loadXML($xml);
            $xmlDocument->validate();
        } catch (MetadataImportInvalidXmlException $e) {
            $this->fail("XML is not valid.");
        }

        $this->assertCount(0, $xmlDocument->getErrors());
    }

    public function testIncompleteEmbargoDateMissingYear()
    {
        $xml = file_get_contents(APPLICATION_PATH . '/test/_files/incomplete-embargo-year.xml');

        $xmlDocument = new XmlDocument();

        $this->expectException(MetadataImportInvalidXmlException::class);

        $xmlDocument->loadXML($xml);
        $xmlDocument->validate();

        $this->assertCount(1, $xmlDocument->getErrors());
    }

    public function testCompleteEmbargoDate()
    {
        $xml = file_get_contents(APPLICATION_PATH . '/test/_files/embargo-date.xml');

        $xmlDocument = new XmlDocument();

        try {
            $xmlDocument->loadXML($xml);
            $xmlDocument->validate();
        } catch (MetadataImportInvalidXmlException $e) {
            $this->fail("XML is not valid.");
        }

        $this->assertCount(0, $xmlDocument->getErrors());
    }

    /**
     * @param string $xml
     * @param string $name
     */
    private function checkValid($xml, $name)
    {
        $xmlDocument = new XmlDocument();

        try {
            $xmlDocument->loadXML($xml);
            $xmlDocument->validate();
        } catch (MetadataImportInvalidXmlException $e) {
            $this->fail("Import XML file '$name' not valid.");
        }

        $this->assertCount(0, $xmlDocument->getErrors());
    }
}
