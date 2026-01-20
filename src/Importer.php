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
use DOMElement;
use DOMNamedNodeMap;
use DOMNode;
use DOMNodeList;
use Exception;
use finfo;
use Opus\Common\Collection;
use Opus\Common\CollectionInterface;
use Opus\Common\Config\FileTypes;
use Opus\Common\DnbInstitute;
use Opus\Common\Document;
use Opus\Common\DocumentInterface;
use Opus\Common\EnrichmentKey;
use Opus\Common\File;
use Opus\Common\Licence;
use Opus\Common\Model\ModelException;
use Opus\Common\Model\NotFoundException;
use Opus\Common\Person;
use Opus\Common\PersonInterface;
use Opus\Common\Security\SecurityException;
use Opus\Common\Series;
use Opus\Common\Subject;
use Opus\Import\Xml\MetadataImportInvalidXmlException;
use Opus\Import\Xml\MetadataImportSkippedDocumentsException;
use Opus\Import\Xml\XmlDocument;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Zend_Log;

use function array_diff;
use function array_key_exists;
use function basename;
use function hash_file;
use function intval;
use function is_readable;
use function pathinfo;
use function sprintf;
use function strcasecmp;
use function strlen;
use function substr;
use function trim;
use function ucfirst;

use const DIRECTORY_SEPARATOR;
use const FILEINFO_MIME_TYPE;
use const PATHINFO_EXTENSION;

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

    /** @var bool */
    private $updateExistingDocuments = true;

    /** @var bool */
    private $filesAdded = false;

    /** @var OutputInterface */
    private $output;

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

            try {
                // TODO post "import" processing before storing!
                $doc->store();
                $this->document = $doc;
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
     * @param DocumentInterface $doc
     *
     * TODO this list needs to be maintained, when model is expanded - better way? Maybe just maintain exceptions?
     */
    protected function resetDocument($doc)
    {
        $fieldsToDelete = array_diff(
            [
                'TitleMain',
                'TitleAbstract',
                'TitleParent',
                'TitleSub',
                'TitleAdditional',
                'Identifier',
                'Note',
                'Enrichment',
                'Licence',
                'Person',
                'Series',
                'Collection',
                'Subject',
                'ThesisPublisher',
                'ThesisGrantor',
                'PublishedDate',
                'PublishedYear',
                'CompletedDate',
                'CompletedYear',
                'ThesisDateAccepted',
                'ThesisYearAccepted',
                'EmbargoDate',
                'ContributingCorporation',
                'CreatingCorporation',
                'Edition',
                'Issue',
                'Language',
                'PageFirst',
                'PageLast',
                'PageNumber',
                'ArticleNumber',
                'PublisherName',
                'PublisherPlace',
                'Type',
                'Volume',
                'BelongsToBibliography',
                'ServerState',
                'ServerDateCreated',
                'ServerDateModified',
                'ServerDatePublished',
                'ServerDateDeleted',
            ],
            $this->fieldsToKeepOnUpdate
        );

        $doc->deleteFields($fieldsToDelete);
    }

    /**
     * @param DOMNamedNodeMap $attributes
     * @param Document        $doc
     *
     * TODO use filter_var?
     * TODO use data model description (configurable, expandable)
     * TODO should not contain code for specific fields - there are/will be other boolean fields
     */
    protected function processAttributes($attributes, $doc)
    {
        foreach ($attributes as $attribute) {
            $method = 'set' . ucfirst($attribute->name);
            $value  = trim($attribute->value);
            if ($attribute->name === 'belongsToBibliography') {
                if ($value === 'true') {
                    $value = '1';
                } elseif ($value === 'false') {
                    $value = '0';
                }
            }
            $doc->$method($value);
        }
    }

    /**
     * @param DOMNodeList       $elements
     * @param DocumentInterface $doc
     * @return void
     */
    protected function processElements($elements, $doc)
    {
        $this->filesAdded = false;

        foreach ($elements as $node) {
            if ($node instanceof DOMElement) {
                switch ($node->tagName) {
                    case 'titlesMain':
                        $this->handleTitleMain($node, $doc);
                        break;
                    case 'titles':
                        $this->handleTitles($node, $doc);
                        break;
                    case 'abstracts':
                        $this->handleAbstracts($node, $doc);
                        break;
                    case 'persons':
                        $this->handlePersons($node, $doc);
                        break;
                    case 'keywords':
                        $this->handleKeywords($node, $doc);
                        break;
                    case 'dnbInstitutions':
                        $this->handleDnbInstitutions($node, $doc);
                        break;
                    case 'identifiers':
                        $this->handleIdentifiers($node, $doc);
                        break;
                    case 'notes':
                        $this->handleNotes($node, $doc);
                        break;
                    case 'collections':
                        $this->handleCollections($node, $doc);
                        break;
                    case 'series':
                        $this->handleSeries($node, $doc);
                        break;
                    case 'enrichments':
                        $this->handleEnrichments($node, $doc);
                        break;
                    case 'licences':
                        $this->handleLicences($node, $doc);
                        break;
                    case 'dates':
                        $this->handleDates($node, $doc);
                        break;
                    case 'files':
                        $this->handleFiles($node, $doc);
                        break;
                    default:
                        break;
                }
            }
        }
    }

    /**
     * @param DOMNode           $node
     * @param DocumentInterface $doc
     */
    protected function handleTitleMain($node, $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $title = $doc->addTitleMain();
                $title->setValue(trim($childNode->textContent));
                $title->setLanguage(trim($childNode->getAttribute('language')));
            }
        }
    }

    /**
     * @param DOMNode           $node
     * @param DocumentInterface $doc
     */
    protected function handleTitles($node, $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $method = 'addTitle' . ucfirst($childNode->getAttribute('type'));
                $title  = $doc->$method();
                $title->setValue(trim($childNode->textContent));
                $title->setLanguage(trim($childNode->getAttribute('language')));
            }
        }
    }

    /**
     * @param DOMNode           $node
     * @param DocumentInterface $doc
     */
    protected function handleAbstracts($node, $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $title = $doc->addTitleAbstract();
                $title->setValue(trim($childNode->textContent));
                $title->setLanguage(trim($childNode->getAttribute('language')));
            }
        }
    }

    /**
     * @param DOMNode           $node
     * @param DocumentInterface $doc
     */
    protected function handlePersons($node, $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $p = Person::new();

                // mandatory fields
                $p->setFirstName(trim($childNode->getAttribute('firstName')));
                $p->setLastName(trim($childNode->getAttribute('lastName')));

                // optional fields
                $optionalFields = ['academicTitle', 'email', 'placeOfBirth', 'dateOfBirth'];
                foreach ($optionalFields as $optionalField) {
                    if ($childNode->hasAttribute($optionalField)) {
                        $method = 'set' . ucfirst($optionalField);
                        $p->$method(trim($childNode->getAttribute($optionalField)));
                    }
                }

                $method = 'addPerson' . ucfirst($childNode->getAttribute('role'));
                $link   = $doc->$method($p);

                if ($childNode->hasAttribute('allowEmailContact') && ($childNode->getAttribute('allowEmailContact') === 'true' || $childNode->getAttribute('allowEmailContact') === '1')) {
                    $link->setAllowEmailContact(true);
                }

                // handling of person identifiers was introduced with OPUS 4.6
                // it is allowed to specify multiple identifiers (of different type) per person
                if ($childNode->hasChildNodes()) {
                    $identifiers = $childNode->childNodes;
                    foreach ($identifiers as $identifier) {
                        if ($identifier instanceof DOMElement && $identifier->tagName === 'identifiers') {
                            $this->handlePersonIdentifiers($identifier, $p);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param DOMNode         $identifiers
     * @param PersonInterface $person
     */
    protected function handlePersonIdentifiers($identifiers, $person)
    {
        $identifiers  = $identifiers->childNodes;
        $idTypesFound = []; // print log message if an identifier type is used more than once
        foreach ($identifiers as $identifier) {
            if ($identifier instanceof DOMElement && $identifier->tagName === 'identifier') {
                $idType = $identifier->getAttribute('type');
                if ($idType === 'intern') {
                    $idType = 'misc';
                }
                if (array_key_exists($idType, $idTypesFound)) {
                    $this->log('could not save more than one identifier of type ' . $idType . ' for person ' . $person->getId());
                    continue; // ignore current identifier
                }
                $idValue    = trim($identifier->textContent);
                $methodName = 'setIdentifier' . ucfirst($idType);
                $person->$methodName($idValue);
                $idTypesFound[$idType] = true; // do not allow further values for this identifier type
            }
        }
    }

    /**
     * @param DOMNode           $node
     * @param DocumentInterface $doc
     */
    protected function handleKeywords($node, $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $s = Subject::new();
                $s->setLanguage(trim($childNode->getAttribute('language')));
                $s->setType($childNode->getAttribute('type'));
                $s->setValue(trim($childNode->textContent));
                $doc->addSubject($s);
            }
        }
    }

    /**
     * @param DOMNode           $node
     * @param DocumentInterface $doc
     */
    protected function handleDnbInstitutions($node, $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $instId   = trim($childNode->getAttribute('id'));
                $instRole = $childNode->getAttribute('role');
                // check if dnbInstitute with given id and role exists
                try {
                    $inst = DnbInstitute::get($instId);

                    // check if dnbInstitute supports given role
                    $method = 'getIs' . ucfirst($instRole);
                    if ($inst->$method()) {
                        $method = 'addThesis' . ucfirst($instRole);
                        $doc->$method($inst);
                    } else {
                        throw new Exception('given role ' . $instRole . ' is not allowed for dnbInstitution id ' . $instId);
                    }
                } catch (NotFoundException $e) {
                    $msg = 'dnbInstitution id ' . $instId . ' does not exist: ' . $e->getMessage();
                    $this->errorMissingObject($msg);
                }
            }
        }
    }

    /**
     * @param string $msg
     * @return void
     * @throws Exception
     */
    protected function errorMissingObject($msg)
    {
        throw new Exception($msg);
    }

    /**
     * @param DOMNode           $node
     * @param DocumentInterface $doc
     */
    protected function handleIdentifiers($node, $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $identifier = $doc->addIdentifier();
                $identifier->setValue(trim($childNode->textContent));
                $identifier->setType($childNode->getAttribute('type'));
            }
        }
    }

    /**
     * @param DOMNode           $node
     * @param DocumentInterface $doc
     */
    protected function handleNotes($node, $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $n = $doc->addNote();
                $n->setMessage(trim($childNode->textContent));
                $n->setVisibility($childNode->getAttribute('visibility'));
            }
        }
    }

    /**
     * @param DOMNode           $node
     * @param DocumentInterface $doc
     */
    protected function handleCollections($node, $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $collectionId = trim($childNode->getAttribute('id'));
                // check if collection with given id exists
                try {
                    $c = Collection::get($collectionId);
                    $doc->addCollection($c);
                } catch (NotFoundException $e) {
                    $msg = 'collection id ' . $collectionId . ' does not exist: ' . $e->getMessage();
                    $this->errorMissingObject($msg);
                }
            }
        }
    }

    /**
     * @param DOMNode           $node
     * @param DocumentInterface $doc
     */
    protected function handleSeries($node, $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $seriesId = trim($childNode->getAttribute('id'));
                // check if document set with given id exists
                try {
                    $series = Series::get($seriesId);
                    $link   = $doc->addSeries($series);
                    $link->setNumber(trim($childNode->getAttribute('number')));
                } catch (NotFoundException $e) {
                    $msg = 'series id ' . $seriesId . ' does not exist: ' . $e->getMessage();
                    $this->errorMissingObject($msg);
                }
            }
        }
    }

    /**
     * Processes the enrichments in the document xml.
     *
     * @param DOMNode           $node
     * @param DocumentInterface $doc
     *
     * TODO add unit test - a bug that prevented the NotFoundException was not automatically detected
     * TODO Enrichment keys do not need to be registered anymore - no need for error message
     */
    protected function handleEnrichments($node, $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $key = trim($childNode->getAttribute('key'));
                // check if enrichment key exists
                try {
                    EnrichmentKey::get($key);
                } catch (NotFoundException $e) {
                    $msg = 'enrichment key ' . $key . ' does not exist: ' . $e->getMessage();
                    $this->errorMissingObject($msg);
                }

                $this->addEnrichment($doc, $key, $childNode->textContent);
            }
        }
    }

    /**
     * Adds an enrichment to the document.
     *
     * @param DocumentInterface $doc
     * @param string            $key   Name of enrichment
     * @param string            $value Value of enrichment
     */
    protected function addEnrichment($doc, $key, $value)
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
     * @param DOMNode           $node
     * @param DocumentInterface $doc
     */
    protected function handleLicences($node, $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $licenceId = trim($childNode->getAttribute('id'));
                try {
                    $link = Licence::get($licenceId);
                    $doc->addLicence($link);
                } catch (NotFoundException $e) {
                    $msg = 'licence id ' . $licenceId . ' does not exist: ' . $e->getMessage();
                    $this->errorMissingObject($msg);
                }
            }
        }
    }

    /**
     * @param DOMNode           $node
     * @param DocumentInterface $doc
     */
    protected function handleDates($node, $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $method = '';
                if ($childNode->hasAttribute('monthDay')) {
                    $method = 'Date';
                } else {
                    $method = 'Year';
                }

                if ($childNode->getAttribute('type') === 'thesisAccepted') {
                    $method = 'setThesis' . $method . 'Accepted';
                } else {
                    $method = 'set' . ucfirst($childNode->getAttribute('type')) . $method;
                }

                $date = trim($childNode->getAttribute('year'));
                if ($childNode->hasAttribute('monthDay')) {
                    // ignore first character of monthDay's attribute value (is always a hyphen)
                    $date .= substr(trim($childNode->getAttribute('monthDay')), 1);
                }

                $doc->$method($date);
            }
        }
    }

    /**
     * Handling of files was introduced with OPUS 4.6.
     *
     * @param DOMNode           $node
     * @param DocumentInterface $doc
     */
    protected function handleFiles($node, $doc)
    {
        if ($this->getImportDir() === null) {
            return;
        }

        $baseDir = trim($node->getAttribute('basedir'));

        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $name = trim($childNode->getAttribute('name'));
                $path = trim($childNode->getAttribute('path'));
                if ($name === '' && $path === '') {
                    $this->log('At least one of the file attributes name or path must be defined!');
                    continue;
                }

                $this->addSingleFile($doc, $name, $baseDir, $path, $childNode);
            }
        }

        $this->setFilesAdded(true);
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

    /**
     * Add a single file to the given Document.
     *
     * @param DocumentInterface $doc the given document
     * @param string            $name Name of the file that should be imported (relative to baseDir)
     * @param string            $baseDir (optional) path of the file that should be imported (relative to the import directory)
     * @param string            $path (optional) path (and name) of the file that should be imported (relative to baseDir)
     * @param null|DOMNode      $childNode (optional) additional metadata of the file (taken from import XML)
     *
     * TODO public or protected - use from outside of Importer for DeepGreen? - design question
     */
    public function addSingleFile($doc, $name, $baseDir = '', $path = '', $childNode = null)
    {
        $output = $this->getOutput();

        $fullPath = $this->importDir;
        if ($baseDir !== '') {
            $fullPath .= $baseDir . DIRECTORY_SEPARATOR;
        }
        $fullPath .= $path !== '' ? $path : $name;

        if (! is_readable($fullPath)) {
            $this->log('Cannot read file ' . $fullPath . ': make sure that it is contained in import package');
            return;
        }

        if (! $this->validMimeType($fullPath)) {
            $this->log('MIME type of file ' . $fullPath . ' is not allowed for import');
            $output->writeln(sprintf('File %s not imported', $name));
            return;
        }

        if ($childNode !== null && ! $this->checksumValidation($childNode, $fullPath)) {
            $this->log('Checksum validation of file ' . $fullPath . ' was not successful: check import package');
            return;
        }

        $file = File::new();
        if ($childNode !== null) {
            $this->handleFileAttributes($childNode, $file);
        }
        if ($file->getLanguage() === null) {
            $file->setLanguage($doc->getLanguage());
        }

        $file->setTempFile($fullPath);
        // allow to overwrite file name (if attribute name was specified)
        $pathName = $name;
        if ($pathName === '') {
            $pathName = $fullPath;
        }
        $file->setPathName(basename($pathName));

        if ($childNode !== null) {
            $comments = $childNode->getElementsByTagName('comment');
            if ($comments->length === 1) {
                $comment = $comments->item(0);
                $file->setComment(trim($comment->textContent));
            }
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
     */
    protected function validMimeType($fullPath)
    {
        $extension     = pathinfo($fullPath, PATHINFO_EXTENSION);
        $finfo         = new finfo(FILEINFO_MIME_TYPE);
        $mimeTypeFound = $finfo->file($fullPath);

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
     *
     * @param DOMNode $childNode
     * @param string  $fullPath
     * @return bool
     */
    protected function checksumValidation($childNode, $fullPath)
    {
        $checksums = $childNode->getElementsByTagName('checksum');
        if ($checksums->length === 0) {
            return true;
        }

        $checksumElement = $checksums->item(0);
        $checksumVal     = trim($checksumElement->textContent);
        $checksumAlgo    = $checksumElement->getAttribute('type');
        $hashValue       = hash_file($checksumAlgo, $fullPath);
        return strcasecmp($checksumVal, $hashValue) === 0;
    }

    /**
     * @param DOMNode $node
     * @param File    $file
     */
    protected function handleFileAttributes($node, $file)
    {
        $attrsToConsider = [
            'language',
            'displayName',
            'visibleInOai',
            'visibleInFrontdoor',
            'sortOrder',
        ];
        foreach ($attrsToConsider as $attribute) {
            $value = trim($node->getAttribute($attribute));
            if ($value !== '') {
                switch ($attribute) {
                    case 'displayName':
                        $attribute = 'label';
                        break;
                    case 'visibleInFrontdoor':
                        $value = $value === 'true' ? true : false;
                        break;
                    case 'visibleInOai':
                        $value = $value === 'true' ? true : false;
                        break;
                    case 'sortOrder':
                        $value = intval($value);
                        break;
                }
                $methodName = 'set' . ucfirst($attribute);
                $file->$methodName($value);
            }
        }
    }

    /**
     * Returns the imported document.
     *
     * @return DocumentInterface
     */
    public function getDocument()
    {
        return $this->document;
    }

    /**
     * @param bool $singleDoc
     * @return $this
     */
    protected function setSingleDocImport($singleDoc)
    {
        $this->singleDocImport = $singleDoc;
        return $this;
    }

    /**
     * @return bool
     */
    protected function isSingleDocImport()
    {
        return $this->singleDocImport;
    }

    /**
     * @param bool $added
     * @return $this
     */
    protected function setFilesAdded($added)
    {
        $this->filesAdded = $added;
        return $this;
    }

    /**
     * @return bool
     */
    protected function isFilesAdded()
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

    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }
}
