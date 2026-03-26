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
 * @copyright   Copyright (c) 2024, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Import\Extract;

use Exception;
use Opus\Common\LoggingTrait;

use function array_map;
use function dirname;
use function file_exists;
use function in_array;
use function is_readable;
use function is_string;
use function pathinfo;
use function strtolower;

use const DIRECTORY_SEPARATOR;
use const PATHINFO_FILENAME;

/**
 * Base class with common functions for package extractors.
 *
 * TODO support arbitrary extraction locations
 * TODO use dynamic (unique) extraction folder?
 * TODO extract to temp folder?
 */
abstract class AbstractPackageExtractor implements PackageExtractorInterface
{
    use LoggingTrait;

    /** @var string[] */
    private $supportedMimeTypes = [];

    /** @var string|null */
    private $extractionDirName;

    /**
     * @return string[]|null
     */
    public function getSupportedMimeTypes()
    {
        return $this->supportedMimeTypes;
    }

    /**
     * @param string|string[]|null $mimeTypes
     * @return $this
     */
    protected function setSupportedMimeTypes($mimeTypes)
    {
        if ($mimeTypes === null) {
            $mimeTypes = [];
        } elseif (is_string($mimeTypes)) {
            $mimeTypes = [$mimeTypes];
        }
        $this->supportedMimeTypes = array_map('strtolower', $mimeTypes);

        return $this;
    }

    /**
     * @param string $mimeType
     * @return bool
     */
    public function isSupportedMimeType($mimeType)
    {
        if ($mimeType === null) {
            return false;
        } else {
            return in_array(strtolower($mimeType), $this->supportedMimeTypes);
        }
    }

    /**
     * @param string      $srcPath Path to ZIP file.
     * @param string|null $targetPath
     * @return string Path to extracted files.
     * @throws Exception
     */
    public function extract($srcPath, $targetPath = null)
    {
        $this->getLogger()->debug("processing file {$srcPath}");

        if (! is_readable($srcPath)) {
            $errMsg = "File {$srcPath} is not readable!";
            $this->getLogger()->err($errMsg);
            throw new Exception($errMsg);
        }

        if ($targetPath === null) {
           // Extract to default directory at same location
            $targetPath = $this->generateTargetPath($srcPath);
        }

        $this->extractFile($srcPath, $targetPath);

        return $targetPath;
    }

    /**
     * @param string $srcPath
     * @param string $targetPath
     * @return void
     */
    abstract public function extractFile($srcPath, $targetPath);

    /**
     * @param string $srcPath
     * @return string
     *
     * TODO support using OPUS 4/system tmp folder
     */
    public function generateTargetPath($srcPath)
    {
        $srcFolder    = dirname($srcPath);
        $targetFolder = pathinfo($srcPath, PATHINFO_FILENAME);
        $basePath     = $srcFolder . DIRECTORY_SEPARATOR . $targetFolder;

        $count = 0;

        do {
            $targetPath = $basePath;
            if ($count > 0) {
                $targetPath .= "_{$count}";
            }
            $count++;
        } while (file_exists($targetPath));

        return $targetPath;
    }
}
