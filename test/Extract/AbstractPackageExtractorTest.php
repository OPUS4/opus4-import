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

use Exception;
use Opus\Import\Extract\AbstractPackageExtractor;
use OpusTest\Import\TestAsset\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\Filesystem\Filesystem;

class AbstractPackageExtractorTest extends TestCase
{
    /** @var AbstractPackageExtractor */
    private $mock;

    public function setUp(): void
    {
        parent::setUp();

        // TODO use createMock function (problem: $supportedMimeTypes does not get initialized)

        $this->mock = new class extends AbstractPackageExtractor {
            /**
             * @param string $src
             * @param string $dest
             * @return void
             */
            public function extractFile($src, $dest)
            {
            }
        };
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    public function testGetSupportedMimeTypesNone()
    {
        $this->assertIsArray($this->mock->getSupportedMimeTypes());
        $this->assertCount(0, $this->mock->getSupportedMimeTypes());
    }

    public function testSetSupportedMimeTypes()
    {
        $method = $this->getMethod('setSupportedMimeTypes');

        $this->assertEquals($this->mock, $method->invoke($this->mock, [
            'application/tar',
            'application/x-tar',
        ]));

        $this->assertEqualsCanonicalizing(
            ['application/tar', 'application/x-tar'],
            $this->mock->getSupportedMimeTypes()
        );
    }

    public function testIsSupportedMimeTypeFalse()
    {
        $this->assertFalse($this->mock->isSupportedMimeType('application/zip'));
    }

    public function testIsSupportedMimeTypeCaseInsensitive()
    {
        $method = $this->getMethod('setSupportedMimeTypes');

        $this->assertEquals($this->mock, $method->invoke($this->mock, [
            'application/tar',
            'application/x-tar',
        ]));

        $this->assertTrue($this->mock->isSupportedMimeType('APPLICATION/TAR'));
    }

    public function testGenerateTargetPath()
    {
        $srcFile = APPLICATION_PATH . '/test/_files/single-doc-pdf-xml.tar';
        $this->assertEquals(
            './test/_files/single-doc-pdf-xml',
            $this->mock->generateTargetPath($srcFile)
        );
    }

    public function testGenerateTargetPathAlreadyExists()
    {
        $srcFile    = APPLICATION_PATH . '/build/workspace/tmp/extractionDir';
        $fileSystem = new Filesystem();
        $fileSystem->mkdir($srcFile);

        $this->assertEquals(
            $srcFile . '_1',
            $this->mock->generateTargetPath($srcFile . '.tar')
        );
    }

    public function testExtractMissingFile()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('is not readable');
        $this->mock->extract(APPLICATION_PATH . '/test/_files/unknown.tar');
    }

    public function testExtract()
    {
        $this->assertEquals(
            APPLICATION_PATH . '/test/_files/sword-packages/single-doc-pdf-xml',
            $this->mock->extract(APPLICATION_PATH . '/test/_files/sword-packages/single-doc-pdf-xml.tar')
        );
    }

    /**
     * @param string $name
     * @return ReflectionMethod
     * @throws ReflectionException
     */
    protected function getMethod($name)
    {
        $class = new ReflectionClass(AbstractPackageExtractor::class);
        return $class->getMethod($name);
    }
}
