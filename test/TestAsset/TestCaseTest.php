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
 * @copyright   Copyright (c) 2025, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace OpusTest\Import\TestAsset;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

use function sys_get_temp_dir;
use function uniqid;

class TestCaseTest extends TestCase
{
    /** @var Filesystem */
    private $filesystem;

    /** @var string */
    private $filesPath;

    public function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();

        $this->filesPath = Path::join(APPLICATION_PATH, 'build/workspace/files');
        $this->filesystem->mkdir($this->filesPath);
    }

    public function testClearFiles()
    {
        $testFile = Path::join($this->filesPath, 'test.txt');
        $this->filesystem->touch($testFile);

        $this->assertFileExists($testFile);

        $this->clearFiles();

        $this->assertFileDoesNotExist($testFile);
    }

    public function testClearFilesSpecificDirectory()
    {
        $dir1 = Path::join($this->filesPath, 'test1');
        $dir2 = Path::join($this->filesPath, 'test2');

        $this->filesystem->mkdir($dir1);
        $this->filesystem->mkdir($dir2);

        $file1 = Path::join($dir1, 'test1.txt');
        $file2 = Path::join($dir2, 'test2.txt');

        $this->filesystem->touch($file1);
        $this->filesystem->touch($file2);

        $this->assertFileExists($file1);
        $this->assertFileExists($file2);

        $this->clearFiles($dir1);

        $this->assertFileDoesNotExist($file1);
        $this->assertFileExists($file2);
    }

    public function testClearFilesNotOutsideOfBuildFolder()
    {
        $tempDir = sys_get_temp_dir();

        $dir = Path::join($tempDir, uniqid('opus4test-'));
        $this->filesystem->mkdir($dir);
        $file = Path::join($dir, 'test.txt');
        $this->filesystem->touch($file);

        $this->assertFileExists($file);

        $this->clearFiles($dir);

        $this->assertFileExists($file);

        $this->filesystem->remove($dir);
    }
}
