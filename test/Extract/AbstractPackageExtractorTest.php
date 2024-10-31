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

namespace OpusTest\Import\Extract;

use Opus\Import\Extract\AbstractPackageExtractor;
use Opus\Import\Xml\MetadataImportInvalidXmlException;
use OpusTest\Import\TestAsset\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

use function file_put_contents;
use function is_dir;
use function is_writable;
use function mkdir;
use function rmdir;
use function touch;
use function unlink;

use const DIRECTORY_SEPARATOR;

class AbstractPackageExtractorTest extends TestCase
{
    /** @var AbstractPackageExtractor */
    private $mockReader;

    /** @var string */
    protected $additionalResources = 'database';

    public function setUp(): void
    {
        parent::setUp();

        // TODO Mock abstract class
        $this->mockReader = $this->getMockForAbstractClass(AbstractPackageExtractor::class);
    }

    public function testCreateExtractionDir()
    {
        $method = $this->getMethod('createExtractionDir');

        $baseDir = APPLICATION_PATH . '/build/workspace/tmp/Application_Import_PackageReaderTest_createExtractionDir';
        mkdir($baseDir);

        $extractDir = $method->invokeArgs($this->mockReader, [$baseDir]);

        $this->assertTrue(is_dir($extractDir));
        $this->assertTrue(is_writable($extractDir));
        $this->assertStringStartsWith($baseDir, $extractDir);

        rmdir($extractDir);
        rmdir($baseDir);

        $this->assertFalse(is_dir($extractDir));
    }

    public function testProcessPackageWithMissingFile()
    {
        $method = $this->getMethod('processPackage');

        $extractDir = APPLICATION_PATH . '/build/workspace/tmp/Application_Import_PackageReaderTest_processPackage_1';
        mkdir($extractDir);

        $statusDoc = $method->invokeArgs($this->mockReader, [$extractDir]);
        $this->assertNull($statusDoc);

        rmdir($extractDir);
    }

    public function testProcessPackageWithEmptyFile()
    {
        $method = $this->getMethod('processPackage');

        $extractDir = APPLICATION_PATH . '/build/workspace/tmp/Application_Import_PackageReaderTest_processPackage_2';
        mkdir($extractDir);

        $metadataFile = $extractDir . DIRECTORY_SEPARATOR . AbstractPackageExtractor::METADATA_FILENAME;
        touch($metadataFile);

        $statusDoc = $method->invokeArgs($this->mockReader, [$extractDir]);
        $this->assertNull($statusDoc);

        unlink($metadataFile);
        rmdir($extractDir);
    }

    public function testProcessPackageWithInvalidFile()
    {
        $method = $this->getMethod('processPackage');

        $extractDir = APPLICATION_PATH . '/build/workspace/tmp/Application_Import_PackageReaderTest_processPackage_3';
        mkdir($extractDir);

        $metadataFile = $extractDir . DIRECTORY_SEPARATOR . AbstractPackageExtractor::METADATA_FILENAME;
        touch($metadataFile);
        file_put_contents($metadataFile, '<import><opusDocument></opusDocument></import>');

        try {
            $this->expectException(MetadataImportInvalidXmlException::class);
            $method->invokeArgs($this->mockReader, [$extractDir]);
        } finally {
            unlink($metadataFile);
            rmdir($extractDir);
        }
    }

    public function testProcessPackageWithValidFile()
    {
        $method = $this->getMethod('processPackage');

        $extractDir = APPLICATION_PATH . '/build/workspace/tmp/Application_Import_PackageReaderTest_processPackage_4';
        mkdir($extractDir);

        $metadataFile = $extractDir . DIRECTORY_SEPARATOR . AbstractPackageExtractor::METADATA_FILENAME;
        touch($metadataFile);

        $xml = <<<XML
<import>
    <opusDocument language="eng" type="article" serverState="unpublished">
        <titlesMain>
            <titleMain language="eng">This is a test document</titleMain>
        </titlesMain>   
    </opusDocument>
</import>
XML;

        file_put_contents($metadataFile, $xml);

        $statusDoc = $method->invokeArgs($this->mockReader, [$extractDir]);
        $this->assertFalse($statusDoc->noDocImported());
        $this->assertCount(1, $statusDoc->getDocs());

        $doc = $statusDoc->getDocs()[0];
        $this->assertEquals('eng', $doc->getLanguage());
        $this->assertEquals('article', $doc->getType());
        $this->assertEquals('unpublished', $doc->getServerState());
        $this->assertCount(1, $doc->getTitleMain());
        $title = $doc->getTitleMain()[0];
        $this->assertEquals('eng', $title->getLanguage());
        $this->assertEquals('This is a test document', $title->getValue());

        // TODO Do we need this?
        // $this->addTestDocument($doc); // for cleanup

        unlink($metadataFile);
        rmdir($extractDir);
    }

    /**
     * @param string $name
     * @return ReflectionMethod
     * @throws ReflectionException
     */
    protected static function getMethod($name)
    {
        $class  = new ReflectionClass(AbstractPackageExtractor::class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }

    /**
     * @param string $tmpDirName
     */
    public static function cleanupTmpDir($tmpDirName)
    {
        parent::cleanupTmpDir($tmpDirName);
        rmdir($tmpDirName); // TODO is this necessary - should it be moved to parent function?
    }
}
