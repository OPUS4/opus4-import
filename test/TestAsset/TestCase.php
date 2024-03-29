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

namespace OpusTest\Import\TestAsset;

use Opus\Common\Config;
use Opus\Db\Util\DatabaseHelper;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Zend_Config;

use function array_diff;
use function is_dir;
use function rmdir;
use function scandir;
use function unlink;

use const DIRECTORY_SEPARATOR;

/**
 * Superclass for all tests.  Providing maintenance tasks.
 */
class TestCase extends SimpleTestCase
{
    protected function clearDatabase()
    {
        $databaseHelper = new DatabaseHelper();
        $databaseHelper->clearTables();
    }

    /**
     * Deletes folders in workspace/files in case a test didn't do proper cleanup.
     *
     * @param string|null $directory
     */
    protected function clearFiles($directory = null)
    {
        if ($directory === null) {
            if (empty(APPLICATION_PATH)) {
                return;
            }
            $filesDir = APPLICATION_PATH . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'workspace'
            . DIRECTORY_SEPARATOR . 'files';
            $files    = array_diff(scandir($filesDir), ['.', '..', '.gitignore']);
        } else {
            $filesDir = $directory;
            $files    = array_diff(scandir($filesDir), ['.', '..']);
        }

        foreach ($files as $file) {
            $path = $filesDir . DIRECTORY_SEPARATOR . $file;

            if (is_dir($path)) {
                $this->clearFiles($path);
            } else {
                unlink($path);
            }
        }

        if ($directory !== null) {
            rmdir($directory);
        }

        return;
    }

    /**
     * Standard setUp method for clearing database.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->clearDatabase();
        $this->clearFiles();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        self::cleanupTmpDir(APPLICATION_PATH . '/build/workspace/tmp');
    }

    /**
     * TODO adjustConfiguration also makes it configurable - so maybe not needed anymore
     */
    public function makeConfigurationModifiable()
    {
        $config = new Zend_Config([], true);
        Config::set($config->merge(Config::get()));
    }

    /**
     * Empties the workspace tmp directory.
     *
     * @param string $tmpDirName
     */
    public static function cleanupTmpDir($tmpDirName)
    {
        $it    = new RecursiveDirectoryIterator($tmpDirName, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
    }
}
