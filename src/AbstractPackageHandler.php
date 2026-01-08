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
 * @copyright   Copyright (c) 2016, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Import;

use Opus\App\Common\ApplicationException;
use Opus\App\Common\Configuration;
use Opus\Common\LoggingTrait;
use Opus\Import\Extract\PackageExtractorInterface;
use Symfony\Component\Filesystem\Filesystem;

use function mkdir;
use function rtrim;
use function uniqid;

use const DIRECTORY_SEPARATOR;

/**
 * Basic handling of packages for import into OPUS 4.
 *
 * This class should be extended to support specific package formats.
 */
abstract class AbstractPackageHandler implements PackageHandlerInterface
{
    use LoggingTrait;

    /** @var AdditionalEnrichments */
    private $additionalEnrichments;

    /** @var string */
    private $packageType;

    /** @var PackageExtractorInterface */
    private $extractor;

    /** @var bool */
    private $cleanupEnabled = false;

    /**
     * @param AdditionalEnrichments $additionalEnrichments
     */
    public function setAdditionalEnrichments($additionalEnrichments)
    {
        $this->additionalEnrichments = $additionalEnrichments;
    }

    /**
     * Verarbeitet die mit dem SWORD-Request übergebene Paketdatei.
     *
     * @param string $filePath Path to package file
     * @return ImportStatusDocument|null
     *
     * TODO rename "process" or "import"?
     */
    public function handlePackage($filePath)
    {
        try {
            $extractedPath = $this->extractPackage($filePath);

            $statusDoc = $this->processPackage($extractedPath);
        } finally {
            $this->cleanup($extractedPath);
        }
        return $statusDoc;
    }

    /**
     * @param string $filePath
     * @return null|ImportStatusDocument
     *
     * TODO rename processData?
     */
    abstract public function processPackage($filePath);

    /**
     * Erzeugt ein temporäres Verzeichnis, in dem die mit dem SWORD-Request übergebene Datei zwischengespeichert werden
     * kann. Die Methode gibt den absoluten Pfad des Verzeichnisses zurück.
     *
     * @return string Pfad zum temporären Ablageverzeichnis
     * @throws ApplicationException
     */
    protected function createTempDir()
    {
        $tempBaseDir = Configuration::getInstance()->getTempPath();
        $mode        = 0700;
        $prefix      = 'opus4-import-';

        $tempBaseDir = rtrim($tempBaseDir, DIRECTORY_SEPARATOR);

        do {
            $tempDir = $tempBaseDir . DIRECTORY_SEPARATOR . uniqid($prefix);
        } while (! mkdir($tempDir, $mode));

        return $tempDir;
    }

    /**
     * @param string $filePath
     * @return string Path to extracted files
     */
    protected function extractPackage($filePath)
    {
        $extractor = $this->getExtractor();

        if ($extractor === null) {
            // TODO throw exception or support not unpacking package (for handling folder?)
            return null;
        }

        $extractPath = $this->createTempDir();

        $extractor->extract($filePath, $extractPath);

        $this->setCleanupEnabled(true);

        return $extractPath;
    }

    /**
     * @param string $extractedPath
     * @return void
     *
     * TODO it should not be necessary to pass the path here
     */
    protected function cleanup($extractedPath)
    {
        if ($this->isCleanupEnabled() && $extractedPath !== null) {
            $filesystem = new Filesystem();
            $filesystem->remove($extractedPath);
        }
    }

    /**
     * @return PackageExtractorInterface
     */
    public function getExtractor()
    {
        return $this->extractor;
    }

    /**
     * @param PackageExtractorInterface $extractor
     * @return $this
     */
    public function setExtractor($extractor)
    {
        $this->extractor = $extractor;
        return $this;
    }

    /**
     * @return bool
     */
    public function isCleanupEnabled()
    {
        return $this->cleanupEnabled;
    }

    /**
     * @param bool $cleanupEnabled
     * @return $this
     */
    public function setCleanupEnabled($cleanupEnabled)
    {
        $this->cleanupEnabled = $cleanupEnabled;
        return $this;
    }
}
