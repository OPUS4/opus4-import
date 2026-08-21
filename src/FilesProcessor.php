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
 * @copyright   Copyright (c) 2026, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Import;

use finfo;
use Opus\Common\Config\FileTypes;
use Opus\Common\DocumentInterface;
use Opus\Common\FileInterface;
use Opus\Common\LoggingTrait;

use function array_diff;
use function hash_file;
use function in_array;
use function is_array;
use function pathinfo;
use function scandir;
use function strcasecmp;

use const FILEINFO_MIME_TYPE;
use const PATHINFO_EXTENSION;

/**
 * TODO add unit tests
 * TODO importPath vs. basePath vs. relative Path?
 * TODO importAllFiles
 *
 * TODO An alternative to using the ImportErrorHandlerInterface here, would be
 *      extending a DefaultFilesProcessor with classes for specific contexts.
 */
class FilesProcessor implements DocumentProcessorInterface
{
    use LoggingTrait;

    /** TODO describe purpose  */
    private ?string $importPath = null;

    /** Import all file types (ignore MIME type) */
    private bool $importAllFiles = false;

    /** @var string[]|null */
    private $ignoreFiles = [];

    /** @var ImportErrorHandlerInterface */
    private $errorHandler;

    public function processDocument(DocumentInterface $document): void
    {
        $files = array_diff(scandir($this->getImportPath()), ['..', '.', 'opus.xml']); // TODO opus.xml is format specific
        foreach ($files as $file) {
            if (! in_array($file, $this->ignoreFiles)) {
                $this->addFile($document, $file); // TODO $file is here a string
            }
        }
    }

    /**
     * TODO not needed, or?
     */
    public function addFile(
        DocumentInterface $doc,
        FileInterface $file,
        ?string $checksum = null,
        ?string $checksumAlgo = null
    ): void {
        // TODO $fullPath
        // TOOD $name

        if (! $this->validMimeType($fullPath)) {
            $this->errorUnsupportedMimeType($name, 'MIME type of file ' . $fullPath . ' is not allowed for import');
            return;
        }

        if (! $this->validateChecksum($fullPath, $checksum, $checksumAlgo)) {
            $this->log('Checksum validation of file ' . $fullPath . ' was not successful: check import package');
            return;
        }

        $doc->addFile($file);
    }

    /**
     * Prüft, ob die übergebene Datei überhaupt importiert werden darf.
     * Dazu gibt es in der Konfiguration die Schlüssel filetypes.mimetypes.*
     *
     * @param string $fullPath
     * @return bool
     *
     * TODO move check to file types helper?
     * TODO use Symfony MIME-Type guesser?
     */
    protected function validMimeType($fullPath)
    {
        $extension     = pathinfo($fullPath, PATHINFO_EXTENSION);
        $finfo         = new finfo(FILEINFO_MIME_TYPE);
        $mimeTypeFound = $finfo->file($fullPath);

        if ($this->isImportAllFiles()) {
            // TODO check exclude option
            return true;
        }

        // TODO check "local" configuration
        $fileTypes = new FileTypes();
        return $fileTypes->isValidMimeType($mimeTypeFound, $extension);
    }

    /**
     * Prüft, ob die im Element checksum angegebene Prüfsumme mit der Prüfsumme
     * der zu importierenden Datei übereinstimmt. Liefert das Ergebnis des
     * Vergleichs zurück.
     *
     * Wurde im Import-XML keine Prüfsumme für die Datei angegeben, so liefert
     * die Methode ebenfalls true zurück.
     */
    protected function validateChecksum(string $fullPath, ?string $checksum, ?string $checksumAlgo): bool
    {
        if ($checksum === null || $checksumAlgo === null) {
            return true;
        }
        $hashValue = hash_file($checksumAlgo, $fullPath);
        return strcasecmp($checksum, $hashValue) === 0;
    }

    protected function errorUnsupportedMimeType(string $name, string $msg): void
    {
        if (null !== $this->errorHandler) {
            $this->errorHandler->errorUnsupportedMimeType($name, $msg);
        }
    }

    public function setBaseDir(?string $baseDir): self
    {
        $this->baseDir = $baseDir;
        return $this;
    }

    public function getBaseDir(): ?string
    {
        return $this->baseDir;
    }

    public function setImportAllFiles(bool $importAllFiles): self
    {
        $this->importAllFiles = $importAllFiles;
        return $this;
    }

    public function isImportAllFiles(): bool
    {
        return $this->importAllFiles;
    }

    public function getIgnoredFiles(): array
    {
        return $this->ignoreFiles;
    }

    public function setIgnoredFiles(string|array|null $files): self
    {
        if ($files === null) {
            $this->ignoreFiles = [];
        } elseif (! is_array($files)) {
            $this->ignoreFiles = [$files];
        } else {
            $this->ignoreFiles = $files;
        }
        return $this;
    }

    public function setImportPath(?string $importPath): self
    {
        $this->importPath = $importPath;
        return $this;
    }

    public function getImportPath(): ?string
    {
        return $this->importPath;
    }

    public function setErrorHandler(?ImportErrorHandlerInterface $errorHandler): self
    {
        $this->errorHandler = $errorHandler;
        return $this;
    }

    public function getErrorHandler(): ?ImportErrorHandlerInterface
    {
        return $this->errorHandler;
    }
}
