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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Zend_Config;

use function realpath;

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
    protected function clearFiles($directory = null): void
    {
        if (empty(APPLICATION_PATH)) {
            return;
        }

        if ($directory === null) {
            $directory = Path::join(APPLICATION_PATH, 'build/workspace/files');
        } else {
            $applicationPath = Path::join(realpath(APPLICATION_PATH), 'build');
            $basePath        = Path::getLongestCommonBasePath($applicationPath, realpath($directory));
            if ($basePath !== $applicationPath) {
                // do not remove directories outside of APPLICATION_PATH
                return;
            }
        }

        $filesystem = new Filesystem();
        $filesystem->remove($directory);
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
        $filesystem = new Filesystem();
        $filesystem->remove(APPLICATION_PATH . '/build/workspace/tmp');

        parent::tearDown();
    }

    /**
     * TODO adjustConfiguration also makes it configurable - so maybe not needed anymore
     */
    public function makeConfigurationModifiable()
    {
        $config = new Zend_Config([], true);
        Config::set($config->merge(Config::get()));
    }
}
