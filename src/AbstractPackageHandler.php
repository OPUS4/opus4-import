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

use Exception;
use Opus\App\Common\ApplicationException;
use Opus\App\Common\Configuration;
use Opus\Import\Extract\PackageExtractor;
use Opus\Import\Extract\TarPackageExtractor;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function file_put_contents;
use function is_readable;
use function md5;
use function mkdir;
use function rand;
use function rmdir;
use function time;
use function unlink;

use const DIRECTORY_SEPARATOR;

/**
 * Basic handling of packages for import into OPUS 4.
 *
 * This class should be extended to support specific package formats.
 */
class AbstractPackageHandler implements PackageHandlerInterface
{
    /** @var AdditionalEnrichments */
    private $additionalEnrichments;

    /** @var string */
    private $packageType;

    /**
     * @param string $contentType
     * @throws Exception
     */
    public function __construct($contentType)
    {
        $this->setPackageType($contentType);
    }

    /**
     * @param AdditionalEnrichments $additionalEnrichments
     */
    public function setAdditionalEnrichments($additionalEnrichments)
    {
        $this->additionalEnrichments = $additionalEnrichments;
    }

    /**
     * @param string $contentType
     * @throws Exception
     */
    private function setPackageType($contentType)
    {
        if ($contentType === null || $contentType === false) {
            throw new Exception('Content-Type header is required');
        }

        $extractor = PackageExtractor::getExtractor($contentType);

        if ($extractor === null) {
            throw new Exception("Content-Type '{$contentType}' is currently not supported");
        }

        $this->packageType = $contentType;
    }

    /**
     * Verarbeitet die mit dem SWORD-Request übergebene Paketdatei.
     *
     * @param string $filePath Path to package file
     * @return ImportStatusDocument|null
     */
    public function handlePackage($filePath)
    {
        $packageReader = $this->getPackageReader();
        if ($packageReader === null) {
            // TODO improve error handling
            return null;
        }

        $tmpDirName = null;
        try {
            $tmpDirName = $this->createTmpDir($payload);
            $this->savePackage($payload, $tmpDirName);

            $statusDoc = $packageReader->readPackage($tmpDirName);
        } finally {
            // TODO copy file before cleanup if error occured
            if ($tmpDirName !== null) {
                $this->cleanupTmpDir($tmpDirName);
            }
        }
        return $statusDoc;
    }

    /**
     * Entfernt das zuvor erzeugte temporäre Verzeichnis für die Extraktion des Paketinhalts.
     * Das Verzeichnis enthält Dateien und ein Unterverzeichnis. Daher ist ein rekursives Löschen
     * erforderlich.
     *
     * @param string $tmpDirName
     */
    private function cleanupTmpDir($tmpDirName)
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
        rmdir($tmpDirName);
    }

    /**
     * Liefert in Abhängigkeit vom zu verarbeitenden Pakettyp ein passendes Objekt zum Einlesen des Pakets zurück.
     * Liefert null zurück, wenn der Pakettyp nicht verarbeitet werden kann.
     *
     * @return OpusPackageHandler
     *
     * TODO make types configurable and remove explicit TAR/ZIP declarations in this class (use factory class?)
     */
    private function getPackageReader()
    {
        $extractor = PackageExtractor::getExtractor($this->packageType);

        if ($extractor === null) {
            throw new Exception("Content-Type '{$this->packageType}' is currently not supported");
        }
        // TODO $packageReader->setAdditionalEnrichments($this->additionalEnrichments);
        return $extractor;
    }

    /**
     * Speichert die übergebene Payload als Datei im übergebenen Verzeichnis ab.
     *
     * @param string $payload
     * @param string $tmpDir
     *
     * TODO save package into import folder (no longer temporary file)
     */
    private function savePackage($payload, $tmpDir)
    {
        $tmpFileName = $tmpDir . DIRECTORY_SEPARATOR . 'package.' . $this->packageType;
        file_put_contents($tmpFileName, $payload);
    }

    /**
     * Erzeugt ein temporäres Verzeichnis, in dem die mit dem SWORD-Request übergebene Datei zwischengespeichert werden
     * kann. Die Methode gibt den absoluten Pfad des Verzeichnisses zurück.
     *
     * @param string $payload der Inhalt des SWORD-Packages
     * @return string absoluter Pfad des temporären Ablageverzeichnisses
     * @throws ApplicationException
     */
    private function createTmpDir($payload)
    {
        $baseDirName = Configuration::getInstance()->getTempPath()
            . DIRECTORY_SEPARATOR . md5($payload) . '-' . time() . '-' . rand(10000, 99999);
        $suffix      = 0;
        $dirName     = "$baseDirName-$suffix";
        while (is_readable($dirName)) {
            // add another suffix to make file name unique (even if collision events are not very likely)
            $suffix++;
            $dirName = "$baseDirName-$suffix";
        }
        mkdir($dirName);
        return $dirName;
    }
}
