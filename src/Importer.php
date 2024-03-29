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
use Zend_Log;

use function array_diff;
use function array_key_exists;
use function basename;
use function hash_file;
use function intval;
use function is_readable;
use function pathinfo;
use function scandir;
use function strcasecmp;
use function strlen;
use function substr;
use function trim;
use function ucfirst;

use const DIRECTORY_SEPARATOR;
use const FILEINFO_MIME_TYPE;
use const PATHINFO_EXTENSION;

/**
 * TODO behavior of this importer changes depending swordContext - It means special handling code in various places.
 *      With every new context, every new use case this code will get more complicated. It would be better if the
 *      different context would be implemented in separate classes that extend a base class providing common
 *      functionality.
 *
 * TODO all those private functions make testing difficult and prevent this class from being extended to customize
 *      the import process - revisit the design of this class
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

    // variables used in SWORD context
    /** @var bool */
    private $swordContext = false;

    /** @var string */
    private $importDir;

    /** @var mixed */
    private $statusDoc;

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

    /**
     * Last imported document.
     *
     * Contains the document object if the import was successful.
     *
     * @var DocumentInterface
     */
    private $document;

    /** @var XmlDocument */
    private $xmlDocument;

    /**
     * @param string        $xml
     * @param bool          $isFile
     * @param null|Zend_Log $logger
     * @param null|string   $logfile
     */
    public function __construct($xml, $isFile = false, $logger = null, $logfile = null)
    {
        $this->logger  = $logger;
        $this->logfile = $logfile;
        if ($isFile) {
            $this->xmlFile = $xml;
        } else {
            $this->xmlString = $xml;
        }

        $this->xmlDocument = new XmlDocument();
    }

    /**
     * @return mixed
     */
    public function getStatusDoc()
    {
        return $this->statusDoc;
    }

    public function enableSwordContext()
    {
        $this->swordContext = true;
        $this->statusDoc    = new ImportStatusDocument();
    }

    /**
     * @param string $imporDir
     */
    public function setImportDir($imporDir)
    {
        $this->importDir = trim($imporDir);
        // always ensure that importDir ends with a directory separator
        if (substr($this->importDir, -1) !== DIRECTORY_SEPARATOR) {
            $this->importDir .= DIRECTORY_SEPARATOR;
        }
    }

    /**
     * @param AdditionalEnrichments $additionalEnrichments
     */
    public function setAdditionalEnrichments($additionalEnrichments)
    {
        $this->additionalEnrichments = $additionalEnrichments;
    }

    /**
     * @param Collection $importCollection
     */
    public function setImportCollection($importCollection)
    {
        $this->importCollection = $importCollection;
    }

    /**
     * @return DocumentInterface
     */
    private function initDocument()
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
        $this->setXml();
        $this->validateXml();

        $numOfDocsImported = 0;
        $numOfSkippedDocs  = 0;

        $opusDocuments = $this->xml->getElementsByTagName('opusDocument');

        // in case of a single document deposit (via SWORD) we allow to omit
        // the explicit declaration of file elements (within <files>..</files>)
        // and automatically import all files in the root level of the SWORD package
        $this->singleDocImport = $opusDocuments->length === 1;

        foreach ($opusDocuments as $opusDocumentElement) {
            // save oldId for later referencing of the record under consideration
            // according to the latest documentation the value of oldId is not
            // stored as an OPUS identifier
            $oldId = $opusDocumentElement->getAttribute('oldId');
            if ($oldId !== '') { // oldId is now an optional attribute
                $opusDocumentElement->removeAttribute('oldId');
                $this->log("Start processing of record #" . $oldId . " ...");
            }

            /*
             * @var Document
             */
            $doc = null;
            if ($opusDocumentElement->hasAttribute('docId')) {
                if ($this->swordContext) {
                    // update of existing documents is not supported in SWORD context
                    // ignore docId and create an empty document instead
                    $opusDocumentElement->removeAttribute('docId');
                    $this->log('Value of attribute docId is ignored in SWORD context');
                    $doc = $this->initDocument();
                } else {
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
                }
            } else {
                // create a new OPUS document and populate it with data
                $doc = $this->initDocument();
            }

            try {
                $this->processAttributes($opusDocumentElement->attributes, $doc);
                $filesElementFound = $this->processElements($opusDocumentElement->childNodes, $doc);
                if ($this->swordContext && $this->singleDocImport && ! $filesElementFound) {
                    // add all files in the root level of the package to the currently
                    // processed document
                    $this->importFilesDirectly($doc);
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

            if ($this->importCollection !== null) {
                $doc->addCollection($this->importCollection);
            }

            try {
                $doc->store();
                $this->document = $doc;
                if ($this->statusDoc !== null) {
                    $this->statusDoc->addDoc($doc);
                }
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
            if (! $this->swordContext) {
                throw new MetadataImportSkippedDocumentsException("$numOfSkippedDocs documents were skipped during import.");
            }
        }
    }

    /**
     * @param string $message
     */
    private function log($message)
    {
        if ($this->logger === null) {
            return;
        }
        $this->logger->debug($message);
    }

    /**
     * Setting the XML from $xmlString or a $xmlFile
     */
    private function setXml()
    {
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
    private function validateXml()
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
    private function appendDocIdToRejectList($docId)
    {
        $this->log('... SKIPPED');
        if ($this->logfile === null) {
            return;
        }
        $this->logfile->log($docId);
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
     */
    private function resetDocument($doc)
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
     */
    private function processAttributes($attributes, $doc)
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
     * @return bool returns true if the import XML definition of the
     *                 currently processed document contains the first level
     *                 element files
     */
    private function processElements($elements, $doc)
    {
        $filesElementPresent = false;

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
                        $filesElementPresent = true;
                        if ($this->importDir !== null) {
                            $baseDir = trim($node->getAttribute('basedir'));
                            $this->handleFiles($node, $doc, $baseDir);
                        }
                        break;
                    default:
                        break;
                }
            }
        }
        return $filesElementPresent;
    }

    /**
     * @param DOMNode           $node
     * @param DocumentInterface $doc
     */
    private function handleTitleMain($node, $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $t = $doc->addTitleMain();
                $t->setValue(trim($childNode->textContent));
                $t->setLanguage(trim($childNode->getAttribute('language')));
            }
        }
    }

    /**
     * @param DOMNode           $node
     * @param DocumentInterface $doc
     */
    private function handleTitles($node, $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $method = 'addTitle' . ucfirst($childNode->getAttribute('type'));
                $t      = $doc->$method();
                $t->setValue(trim($childNode->textContent));
                $t->setLanguage(trim($childNode->getAttribute('language')));
            }
        }
    }

    /**
     * @param DOMNode           $node
     * @param DocumentInterface $doc
     */
    private function handleAbstracts($node, $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $t = $doc->addTitleAbstract();
                $t->setValue(trim($childNode->textContent));
                $t->setLanguage(trim($childNode->getAttribute('language')));
            }
        }
    }

    /**
     * @param DOMNode           $node
     * @param DocumentInterface $doc
     */
    private function handlePersons($node, $doc)
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
    private function handlePersonIdentifiers($identifiers, $person)
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
    private function handleKeywords($node, $doc)
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
    private function handleDnbInstitutions($node, $doc)
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
                    if ($this->swordContext) {
                        $this->log($msg);
                        continue;
                    }
                    throw new Exception($msg);
                }
            }
        }
    }

    /**
     * @param DOMNode           $node
     * @param DocumentInterface $doc
     */
    private function handleIdentifiers($node, $doc)
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
    private function handleNotes($node, $doc)
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
    private function handleCollections($node, $doc)
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
                    if ($this->swordContext) {
                        $this->log($msg);
                        continue;
                    }
                    throw new Exception($msg);
                }
            }
        }
    }

    /**
     * @param DOMNode           $node
     * @param DocumentInterface $doc
     */
    private function handleSeries($node, $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $seriesId = trim($childNode->getAttribute('id'));
                // check if document set with given id exists
                try {
                    $s    = Series::get($seriesId);
                    $link = $doc->addSeries($s);
                    $link->setNumber(trim($childNode->getAttribute('number')));
                } catch (NotFoundException $e) {
                    $msg = 'series id ' . $seriesId . ' does not exist: ' . $e->getMessage();
                    if ($this->swordContext) {
                        $this->log($msg);
                        continue;
                    }
                    throw new Exception($msg);
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
     */
    private function handleEnrichments($node, $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $key = trim($childNode->getAttribute('key'));
                // check if enrichment key exists
                try {
                    EnrichmentKey::get($key);
                } catch (NotFoundException $e) {
                    $msg = 'enrichment key ' . $key . ' does not exist: ' . $e->getMessage();
                    if ($this->swordContext) {
                        $this->log($msg);
                        continue;
                    }
                    throw new Exception($msg);
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
    private function addEnrichment($doc, $key, $value)
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
    private function handleLicences($node, $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $licenceId = trim($childNode->getAttribute('id'));
                try {
                    $l = Licence::get($licenceId);
                    $doc->addLicence($l);
                } catch (NotFoundException $e) {
                    $msg = 'licence id ' . $licenceId . ' does not exist: ' . $e->getMessage();
                    if ($this->swordContext) {
                        $this->log($msg);
                        continue;
                    }
                    throw new Exception($msg);
                }
            }
        }
    }

    /**
     * @param DOMNode           $node
     * @param DocumentInterface $doc
     */
    private function handleDates($node, $doc)
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
     * @param string            $baseDir
     */
    private function handleFiles($node, $doc, $baseDir)
    {
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
    }

    /**
     * Add a single file to the given Document.
     *
     * @param DocumentInterface $doc the given document
     * @param string            $name Name of the file that should be imported (relative to baseDir)
     * @param string            $baseDir (optional) path of the file that should be imported (relative to the import directory)
     * @param string            $path (optional) path (and name) of the file that should be imported (relative to baseDir)
     * @param null|DOMNode      $childNode (optional) additional metadata of the file (taken from import XML)
     */
    private function addSingleFile($doc, $name, $baseDir = '', $path = '', $childNode = null)
    {
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
    private function validMimeType($fullPath)
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
    private function checksumValidation($childNode, $fullPath)
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
    private function handleFileAttributes($node, $file)
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
     * Add all files in the root level of the import package to the given
     * document.
     *
     * @param DocumentInterface $doc document
     */
    private function importFilesDirectly($doc)
    {
        $files = array_diff(scandir($this->importDir), ['..', '.', 'opus.xml']);
        foreach ($files as $file) {
            $this->addSingleFile($doc, $file);
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
}
