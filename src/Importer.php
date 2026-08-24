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
use Opus\Import\Xml\OpusXmlParser;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Zend_Log;

use function count;
use function strlen;
use function substr;
use function trim;

use const DIRECTORY_SEPARATOR;

/**
 * TODO document loggers
 * TODO use OutputInterface?
 */
class Importer implements ImportErrorHandlerInterface
{
    /** @var Zend_Log|null */
    private $logfile;

    /** @var Zend_Log|null */
    private $logger;

    /** @var string|DOMDocument */
    private $input;

    /** @var bool */
    private $inputIsFile;

    /** @var string */
    private $importDir;

    /**
     * Additional enrichments that will be added to each imported document.
     *
     * This could be for instance a timestamp and other information about the import.
     *
     * TODO can this be moved to a DocumentProcessorInterfact implementing class?
     */
    private ?AdditionalEnrichments $additionalEnrichments = null;

    /** @var CollectionInterface TODO should be DocumentProcessor*/
    private $importCollection;

    /** @var bool */
    private $singleDocImport = false;

    /** @var DocumentInterface Last imported document. Contains the document object if the import was successful. */
    private $document;

    /** @var ImportRules */
    private $importRules;

    /** @var bool TODO pass through to ImportFormat object */
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
     *
     * TODO trim down constructor
     * TODO check if there are unit tests for invalid $xml
     */
    public function __construct($xml, $isFile = false, $logger = null, $logfile = null)
    {
        $this->logger  = $logger;
        $this->logfile = $logfile;

        $this->input       = $xml;
        $this->inputIsFile = $isFile;
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
     */
    public function run()
    {
        $this->importedDocumentIds = [];

        $parser = new OpusXmlParser();
        $parser->setImportPath($this->getImportDir());
        $parser->setErrorHandler($this);

        if ($this->inputIsFile) {
            $parser->parseFile($this->input);
        } elseif ($this->input instanceof DOMDocument) {
            $parser->parseDom($this->input);
        } else {
            $parser->parse($this->input);
        }

        $numOfDocsImported = 0;
        $numOfSkippedDocs  = 0;

        // in case of a single document deposit (via SWORD, ...) we allow to omit
        // the explicit declaration of file elements (within <files>..</files>)
        // and automatically import all files in the root level of the package
        $this->setSingleDocImport($parser->getDocumentCount() === 1);

        do {
            $document = null;

            $this->storeDocument = true;

            try {
                $document = $parser->next();
            } catch (NotFoundException $e) {
                // TODO include lineNo
                $docId = $parser->getCurrentId();
                $oldId = $parser->getCurrentReferenceId();
                $this->log('Could not load document #' . $docId . ' from database: ' . $e->getMessage());
                $this->appendDocIdToRejectList($oldId);
                $numOfSkippedDocs++;
                continue;
            } catch (Exception $e) {
                // TODO include lineNo
                $oldId = $parser->getCurrentReferenceId();
                $this->log('Error while parsing document #' . $oldId . ': ' . $e->getMessage());
                $this->appendDocIdToRejectList($oldId);
                $numOfSkippedDocs++;
            }

            if ($document === null) {
                // import finished
                continue;
            }

            // save oldId for later referencing of the record under consideration
            // according to the latest documentation the value of oldId is not
            // stored as an OPUS identifier
            $oldId = $parser->getCurrentReferenceId();
            if ($oldId !== null) { // oldId is now an optional attribute
                $this->log("Start processing of record #" . $oldId . " ...");
            }

            // TODO set serverState = 'unpublished' as default - where should this go?

            try {
                // Files may already have been added in processElements
                if (! $parser->isFilesAdded()) {
                    $this->processFiles($document);
                }
            } catch (Exception $e) {
                $this->log('Error while processing document #' . $oldId . ': ' . $e->getMessage());
                $this->appendDocIdToRejectList($oldId);
                $numOfSkippedDocs++;
                continue;
            }

            // TODO this should become a DocumentProcessor and configurable
            if ($this->additionalEnrichments !== null) {
                $enrichments = $this->additionalEnrichments->getEnrichments();
                foreach ($enrichments as $key => $value) {
                    $this->addEnrichment($document, $key, $value);
                }
            }

            // TODO should this be handled by Import Rules or a DocumentProcessor
            if ($this->importCollection !== null) {
                $document->addCollection($this->importCollection);
            }

            // TODO this should be handled by a StoreDocument processor */
            // TODO !!! during processing (old OpusXmlCode) this could habe been set to false - recreate behaviour with OpusXmlParser
            if (! $this->storeDocument) {
                $numOfSkippedDocs++;
                $this->appendDocIdToRejectList($oldId);
                continue;
            }

            // TODO this should be a DocumentProcessor in the import pipeline
            $importRules = $this->getImportRules();
            $importRules->apply($document);

            try {
                // TODO post "import" processing before storing!
                $newDocId                    = $document->store();
                $this->document              = $document;
                $this->importedDocumentIds[] = $newDocId;
                $this->postStore($document);
            } catch (Exception $e) {
                $this->log('Error while saving imported document #' . $oldId . ' to database: ' . $e->getMessage());
                $this->appendDocIdToRejectList($oldId);
                $numOfSkippedDocs++;
                continue;
            }

            $numOfDocsImported++;
            $this->log('... OK');
        } while ($document !== null);

        if ($numOfSkippedDocs === 0) {
            $this->log("Import finished successfully. $numOfDocsImported documents were imported.");
        } else {
            $this->log("Import finished. $numOfDocsImported documents were imported. $numOfSkippedDocs documents were skipped.");
            throw new MetadataImportSkippedDocumentsException("$numOfSkippedDocs documents were skipped during import.");
        }
    }

    /**
     * TODO move to processor
     */
    protected function addEnrichment(DocumentInterface $doc, string $key, string $value): void
    {
        if ($value === null || strlen(trim($value)) === 0) {
            // enrichment must have a value
            // TODO log? how to identify the document before storing? improve import for easier monitoring
            return;
        }
        $enrichment = $doc->addEnrichment();
        $enrichment->setKeyName($key);
        $enrichment->setValue(trim($value));
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

    /**
     * @throws Exception
     */
    public function errorMissingObject(string $msg): void
    {
        throw new Exception($msg);
    }

    public function errorUnsupportedMimeType(string $name, string $msg): void
    {
        $this->log($msg);
    }
}
