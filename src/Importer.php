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

use DOMDocument;
use Exception;
use Opus\Common\CollectionInterface;
use Opus\Common\Document;
use Opus\Common\DocumentInterface;
use Opus\Common\Model\ModelException;
use Opus\Common\Model\NotFoundException;
use Opus\Common\Security\SecurityException;
use Opus\Import\Xml\MetadataImportInvalidXmlException;
use Opus\Import\Xml\MetadataImportSkippedDocumentsException;
use Opus\Import\Xml\XmlDocument;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Zend_Log;

use function count;
use function substr;
use function trim;

use const DIRECTORY_SEPARATOR;

/**
 * TODO document loggers
 * TODO use OutputInterface?
 */
class Importer
{
    /** @var Zend_Log|null */
    private $logfile;

    /** @var Zend_Log|null */
    private $logger;

    /** @var DOMDocument */
    private $xml;

    /** @var string */
    private $xmlFile;

    /** @var string */
    private $xmlString;

    /** @var array */
    private $fieldsToKeepOnUpdate = [];

    /** @var string */
    private $importDir;

    /**
     * Additional enrichments that will be added to each imported document.
     *
     * This could be for instance a timestamp and other information about the import.
     *
     * @var AdditionalEnrichments
     */
    private $additionalEnrichments;

    /** @var CollectionInterface */
    private $importCollection;

    /** @var bool */
    private $singleDocImport = false;

    /** @var DocumentInterface Last imported document. Contains the document object if the import was successful. */
    private $document;

    /** @var XmlDocument */
    private $xmlDocument;

    /** @var ImportRules */
    private $importRules;

    /** @var bool */
    private $updateExistingDocuments = true;

    /** @var bool */
    private $filesAdded = false;

    /** @var OutputInterface */
    private $output;

    /** @var int[] */
    private $importedDocumentIds;

    /** @var bool */
    protected $storeDocument = true;

    /** @var bool  */
    private $importAllFiles = false;

    /**
     * @param string|DOMDocument $xml
     * @param bool               $isFile
     * @param null|Zend_Log      $logger
     * @param null|string        $logfile
     */
    public function __construct($xml, $isFile = false, $logger = null, $logfile = null)
    {
        $this->logger  = $logger;
        $this->logfile = $logfile;

        $this->xmlDocument = new XmlDocument();

        if ($isFile) {
            $this->xmlFile = $xml;
        } elseif ($xml instanceof DOMDocument) {
            $this->xml = $xml;
            $this->xmlDocument->setXml($xml);
        } else {
            $this->xmlString = $xml;
        }
    }

    /**
     * @param string $path
     * @return $this
     */
    public function setImportDir($path)
    {
        $this->importDir = trim($path);
        // always ensure that importDir ends with a directory separator
        if (substr($this->importDir, -1) !== DIRECTORY_SEPARATOR) {
            $this->importDir .= DIRECTORY_SEPARATOR;
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getImportDir()
    {
        return $this->importDir;
    }

    /**
     * @param AdditionalEnrichments $additionalEnrichments
     *
     * TODO generalize? explain concept, additional enrichments before/after Importer
     */
    public function setAdditionalEnrichments($additionalEnrichments)
    {
        $this->additionalEnrichments = $additionalEnrichments;
    }

    /**
     * @param CollectionInterface $importCollection
     * TODO use Import Rules?
     */
    public function setImportCollection($importCollection)
    {
        $this->importCollection = $importCollection;
    }

    /**
     * @return DocumentInterface
     */
    protected function initDocument()
    {
        $doc = Document::new();
        // since OPUS 4.5 attribute serverState is optional: if no attribute
        // value is given we set server state to unpublished
        $doc->setServerState('unpublished');
        return $doc;
    }

    /**
     * @throws MetadataImportInvalidXmlException
     * @throws MetadataImportSkippedDocumentsException
     * @throws ModelException
     * @throws SecurityException
     *
     * TODO break up processing
     */
    public function run()
    {
        $this->importedDocumentIds = [];

        $this->loadXml();
        $this->validateXml();

        $numOfDocsImported = 0;
        $numOfSkippedDocs  = 0;

        $opusDocuments = $this->xml->getElementsByTagName('opusDocument');

        // in case of a single document deposit (via SWORD, ...) we allow to omit
        // the explicit declaration of file elements (within <files>..</files>)
        // and automatically import all files in the root level of the package
        $this->setSingleDocImport($opusDocuments->length === 1);

        foreach ($opusDocuments as $opusDocumentElement) {
            $this->storeDocument = true;

            // save oldId for later referencing of the record under consideration
            // according to the latest documentation the value of oldId is not
            // stored as an OPUS identifier
            $oldId = $opusDocumentElement->getAttribute('oldId');
            if ($oldId !== '') { // oldId is now an optional attribute
                $opusDocumentElement->removeAttribute('oldId');
                $this->log("Start processing of record #" . $oldId . " ...");
            }

            // @var DocumentInterface
            $doc = null;

            // TODO move creation of Document object into separata function
            if ($opusDocumentElement->hasAttribute('docId') && $this->isUpdateExistingDocuments()) {
                // perform metadata update on given document
                // please note that existing files that are already associated
                // with the given document are not deleted or updated
                $docId = $opusDocumentElement->getAttribute('docId');
                try {
                    $doc = Document::get($docId);
                    $opusDocumentElement->removeAttribute('docId');
                } catch (NotFoundException $e) {
                    $this->log('Could not load document #' . $docId . ' from database: ' . $e->getMessage());
                    $this->appendDocIdToRejectList($oldId);
                    $numOfSkippedDocs++;
                    continue;
                }

                $this->resetDocument($doc);
            } else {
                // ignore docId and create an empty document instead
                // TODO necessary? error if docId not present?
                $opusDocumentElement->removeAttribute('docId');

                $this->log('Ignore value of attribute docId');

                // create a new OPUS document and populate it with data
                $doc = $this->initDocument();
            }

            try {
                $this->processAttributes($opusDocumentElement->attributes, $doc);
                $this->processElements($opusDocumentElement->childNodes, $doc);

                // Files may already have been added in processElements
                if (! $this->isFilesAdded()) {
                    $this->processFiles($doc);
                }
            } catch (Exception $e) {
                $this->log('Error while processing document #' . $oldId . ': ' . $e->getMessage());
                $this->appendDocIdToRejectList($oldId);
                $numOfSkippedDocs++;
                continue;
            }

            if ($this->additionalEnrichments !== null) {
                $enrichments = $this->additionalEnrichments->getEnrichments();
                foreach ($enrichments as $key => $value) {
                    $this->addEnrichment($doc, $key, $value);
                }
            }

            // TODO should this be handled by Import Rules?
            if ($this->importCollection !== null) {
                $doc->addCollection($this->importCollection);
            }

            if (! $this->storeDocument) {
                $numOfSkippedDocs++;
                $this->appendDocIdToRejectList($oldId);
                continue;
            }

            $importRules = $this->getImportRules();
            $importRules->apply($doc);

            try {
                // TODO post "import" processing before storing!
                $newDocId                    = $doc->store();
                $this->document              = $doc;
                $this->importedDocumentIds[] = $newDocId;
                $this->postStore($doc);
            } catch (Exception $e) {
                $this->log('Error while saving imported document #' . $oldId . ' to database: ' . $e->getMessage());
                $this->appendDocIdToRejectList($oldId);
                $numOfSkippedDocs++;
                continue;
            }

            $numOfDocsImported++;
            $this->log('... OK');
        }

        if ($numOfSkippedDocs === 0) {
            $this->log("Import finished successfully. $numOfDocsImported documents were imported.");
        } else {
            $this->log("Import finished. $numOfDocsImported documents were imported. $numOfSkippedDocs documents were skipped.");
            throw new MetadataImportSkippedDocumentsException("$numOfSkippedDocs documents were skipped during import.");
        }
    }

    /**
     * TODO convert into store function, that actually does the storing?
     *
     * @param DocumentInterface $doc
     */
    protected function postStore($doc): void
    {
    }

    /**
     * @param bool $enabled
     * @return $this
     */
    public function setUpdateExistingDocuments($enabled)
    {
        $this->updateExistingDocuments = $enabled;
        return $this;
    }

    /**
     * @return bool
     */
    public function isUpdateExistingDocuments()
    {
        return $this->updateExistingDocuments;
    }

    /**
     * @param string $message
     */
    protected function log($message)
    {
        if ($this->logger === null) {
            return;
        }
        $this->logger->debug($message);
    }

    /**
     * Loading XML from $xmlString or a $xmlFile
     */
    protected function loadXml()
    {
        if ($this->xml !== null) {
            return;
        }

        $this->log("Load XML ...");

        try {
            if ($this->xmlFile !== null) {
                $this->xml = $this->xmlDocument->load($this->xmlFile);
            } else {
                $this->xml = $this->xmlDocument->loadXML($this->xmlString);
            }

            $this->log('Loading Result: OK');
        } catch (MetadataImportInvalidXmlException $exception) {
            $this->log("... ERROR: Cannot load XML document: make sure it is well-formed."
                . $this->xmlDocument->getErrorsPrettyPrinted());
            throw new MetadataImportInvalidXmlException('XML is not well-formed.');
        }
    }

    /**
     * Validates the XML
     */
    protected function validateXml()
    {
        $this->log("Validate XML ...");

        try {
            $this->xmlDocument->validate();
            $this->log('Validation Result: OK');
        } catch (MetadataImportInvalidXmlException $exception) {
            $this->log("... ERROR: Cannot load XML document: make sure it is well-formed."
                . $this->xmlDocument->getErrorsPrettyPrinted());
            throw $exception;
        }
    }

    /**
     * @param int $docId
     */
    protected function appendDocIdToRejectList($docId)
    {
        $this->log('... SKIPPED');
        if ($this->logfile === null) {
            return;
        }
        $this->logfile->log($docId, Zend_Log::ERR);
    }

    /**
     * Allows certain fields to be kept on update.
     *
     * @param array $fields DescriptionArray of fields to keep on update
     */
    public function keepFieldsOnUpdate($fields)
    {
        $this->fieldsToKeepOnUpdate = $fields;
    }

    /**
     * Basic Importer handles files in processElements function.
     *
     * @param DocumentInterface $doc
     * @return void
     */
    protected function processFiles($doc)
    {
    }

    public function getDocument(): DocumentInterface
    {
        return $this->document;
    }

    /**
     * @return ImportRules
     */
    public function getImportRules()
    {
        if ($this->importRules === null) {
            $this->importRules = new ImportRules();
            $this->importRules->init();
        }

        return $this->importRules;
    }

    protected function setSingleDocImport(bool $singleDoc): self
    {
        $this->singleDocImport = $singleDoc;
        return $this;
    }

    protected function isSingleDocImport(): bool
    {
        return $this->singleDocImport;
    }

    protected function setFilesAdded(bool $added): self
    {
        $this->filesAdded = $added;
        return $this;
    }

    protected function isFilesAdded(): bool
    {
        return $this->filesAdded;
    }

    public function getOutput(): OutputInterface
    {
        if ($this->output === null) {
            $this->output = new NullOutput();
        }

        return $this->output;
    }

    public function setOutput(OutputInterface $output): self
    {
        $this->output = $output;
        return $this;
    }

    public function getDocumentIds(): int|array
    {
        if ($this->importedDocumentIds === null) {
            return [];
        } elseif (count($this->importedDocumentIds) === 1) {
            return $this->importedDocumentIds[0];
        }

        return $this->importedDocumentIds;
    }

    public function isImportAllFiles(): bool
    {
        return $this->importAllFiles;
    }

    public function setImportAllFiles(bool $importAllFiles): self
    {
        $this->importAllFiles = $importAllFiles;
        return $this;
    }
}
