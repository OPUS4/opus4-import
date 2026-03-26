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
use Opus\Common\Log;
use Opus\Common\Model\ModelException;
use Opus\Common\Security\SecurityException;
use Opus\Import\Sword\ImportCollection;
use Opus\Import\Xml\MetadataImportInvalidXmlException;
use Opus\Import\Xml\MetadataImportSkippedDocumentsException;
use Zend_Exception;

use function file_get_contents;
use function is_readable;
use function trim;

use const DIRECTORY_SEPARATOR;

/**
 * Reads an OPUS import package containing one or more documents and imports
 * the documents.
 *
 * Currently ZIP and TAR files are supported by extending classes.
 *
 * TODO use OutputInterface
 */
class OpusPackageHandler extends AbstractPackageHandler
{
    // TODO make configurable with opus.xml as default if no configuration exists
    const METADATA_FILENAME = 'opus.xml';

    /** @var AdditionalEnrichments */
    private $additionalEnrichments; // TODO additional enrichments should be something configurable/extendable

    /**
     * Sets additional enrichments that will be added to every imported document.
     *
     * @param AdditionalEnrichments $additionalEnrichments
     */
    public function setAdditionalEnrichments($additionalEnrichments)
    {
        $this->additionalEnrichments = $additionalEnrichments;
    }

    /**
     * Verarbeitet das XML-Metadatendokument, dessen Inhalt in $xml übergeben wird.
     * Zugehörige Volltextdateien werden aus dem Verzeichnis $dirName gelesen.
     *
     * @param string $xml Ausgelesener Inhalt der XML-Metadatendatei
     * @param string $dirName Pfad zum Extraktionsverzeichnis
     * @return ImportStatusDocument Statusdokument mit Informationen zum Ergebnis des Imports
     * @throws MetadataImportInvalidXmlException
     * @throws MetadataImportSkippedDocumentsException
     * @throws ModelException
     * @throws SecurityException
     * @throws Zend_Exception
     *
     * TODO get rid of enableSwordContext, instead use new SwordImporter class (use inheritance)
     */
    private function processOpusXML($xml, $dirName)
    {
        $importer = new SwordImporter($xml, false, $this->getLogger());
        $importer->setImportDir($dirName);

        $importer->setAdditionalEnrichments($this->additionalEnrichments);
        $importCollection = new ImportCollection();

        $importer->setImportCollection($importCollection->getCollection());

        $importer->run();

        // TODO ImportStatusDocument is SWORD specific - separate package import from SWORD
        return $importer->getStatusDoc();
    }

    /**
     * @return Log
     * @throws Zend_Exception
     */
    public function getLogger()
    {
        return Log::get();
    }

    /**
     * Prozessiert den Paketinhalt und liefert ein Status-Dokument zurück.
     * Liefert null, wenn das Paket kein Metadaten-Dokument enthält oder
     * dieses leer ist.
     *
     * @param string $filePath Pfad zum Extraktionsverzeichnis
     * @return null|ImportStatusDocument
     * @throws Zend_Exception
     */
    public function processPackage($filePath)
    {
        $metadataXml = $this->getMetadataXml($filePath);

        if ($metadataXml === null) {
            // TODO What should be returned/thrown?
            return null;
        }

        return $this->processOpusXML($metadataXml, $filePath);
    }

    /**
     * @param string $dirName
     * @return string|null
     * @throws Zend_Exception
     */
    protected function getMetadataXml($dirName)
    {
        $metadataFile = $dirName . DIRECTORY_SEPARATOR . self::METADATA_FILENAME;
        if (! is_readable($metadataFile)) {
            $this->getLogger()->err('missing metadata file ' . $metadataFile);
            return null;
        }

        $content = file_get_contents($metadataFile);
        if ($content === false || trim($content) === '') {
            $this->getLogger()->err('could not get non-empty content from metadata file ' . $metadataFile);
            return null;
        }

        return $content;
    }
}
