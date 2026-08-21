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

namespace Opus\Import\Xml;

use DOMDocument;
use DOMElement;
use DOMNamedNodeMap;
use DOMNode;
use DOMNodeList;
use Exception;
use Opus\Common\Collection;
use Opus\Common\DnbInstitute;
use Opus\Common\Document;
use Opus\Common\DocumentInterface;
use Opus\Common\EnrichmentKey;
use Opus\Common\File;
use Opus\Common\Licence;
use Opus\Common\LoggingTrait;
use Opus\Common\Model\NotFoundException;
use Opus\Common\Person;
use Opus\Common\PersonInterface;
use Opus\Common\Series;
use Opus\Common\Subject;
use Opus\Import\AbstractImportFormat;
use Opus\Import\FilesProcessor;
use Opus\Import\ImportErrorHandlerInterface;
use Opus\Import\ImportFormatInterface;

use function array_diff;
use function array_key_exists;
use function basename;
use function count;
use function filter_var;
use function intval;
use function is_readable;
use function strlen;
use function substr;
use function trim;
use function ucfirst;

use const DIRECTORY_SEPARATOR;
use const FILTER_VALIDATE_BOOLEAN;

/**
 * Convert OPUS-XML into Document objects.
 *
 * OPUS-XML is used in multiple places
 * - Console (MetadataImport)
 * - SWORD (SwordImporter)
 * - DeepGreen (JATS->OPUS-XML->Document, SwordImporter)
 *
 * TODO MetadataImport did not support files
 * TODO SWORD supports adding files after parsing XML, but also during parsing
 * TODO consolidate with class Importer (Importer should use OpusXmlImport - sync improvements)
 * TODO support parsing existing DOMDocument? (is allowed in Importer right now)
 * TODO support setting default values like serverState = 'unpublished'
 */
class OpusXmlParser extends AbstractImportFormat
{
    use LoggingTrait;

    /** @var array */
    private $fieldsToKeepOnUpdate = [];

    /** @var bool */
    private $updateExistingDocuments = true;

    /** @var ?string Path to document files */
    private $importPath; // TODO where is this coming from?

    /** @var ?DOMNode[] Array with all XML elements for documents */
    private $documentElements;

    /** @var int Index of current document element. */
    private $currentIndex = 0;

    /** @var ?string Old identifier of document (optional) */
    private $oldId;

    /** @var int ID of existing record TODO probably should be renamed */
    private $currentId = 0;

    /** @var int */
    private $currentLineNo = 0;

    /** @var bool */
    private $filesAdded = 0;

    /** @var ImportErrorHandlerInterface */
    private $errorHandler;

    /** Processor for adding files to Document. TODO should use Interface */
    private ?FilesProcessor $filesProcessor = null;

    /**
     * @throws MetadataImportInvalidXmlException
     */
    public function parseFile(string $path): ImportFormatInterface
    {
        $doc = new XmlDocument();
        $doc->load($path);
        $this->open($doc);
        return $this;
    }

    /**
     * @throws MetadataImportInvalidXmlException
     */
    public function parse(string $data): ImportFormatInterface
    {
        $doc = new XmlDocument();
        $doc->loadXML($data);
        $this->open($doc);
        return $this;
    }

    /**
     * @throws MetadataImportInvalidXmlException
     */
    public function parseDom(DOMDocument $data): ImportFormatInterface
    {
        $doc = new XmlDocument();
        $doc->setDom($data);
        $this->open($doc);
        return $this;
    }

    /**
     * Returns total number of documents.
     */
    public function getDocumentCount(): int
    {
        return count($this->documentElements);
    }

    /**
     * Returns the next DocumentInterface object or null if none is available.
     *
     * @throws NotFoundException
     */
    public function next(): ?DocumentInterface
    {
        $this->currentLineNo = 0;

        if ($this->documentElements === null) {
            return null;
        }

        if (isset($this->documentElements[$this->currentIndex])) {
            $documentElement = $this->documentElements[$this->currentIndex];
            $this->currentIndex++;
            $this->currentLineNo = $documentElement->getLineNo();
            return $this->processDocument($documentElement);
        }

        return null;
    }

    /**
     * @throws MetadataImportInvalidXmlException
     */
    protected function open(XmlDocument $xml)
    {
        $xml->validate();
        $this->documentElements = $xml->getDom()->getElementsByTagName('opusDocument');
        $this->currentIndex     = 0;
    }

    /**
     * Processes DOM for a single document.
     *
     * @throws NotFoundException
     *
     * TODO separate updating documents - What is needed?
     * TODO log update of documents
     */
    protected function processDocument(DOMNode $opusDocumentElement): ?DocumentInterface
    {
        $this->currentId = 0;

        // save oldId for later referencing of the record under consideration
        $oldId       = $opusDocumentElement->getAttribute('oldId');
        $this->oldId = $oldId !== '' ? $oldId : null;
        $opusDocumentElement->removeAttribute('oldId');

        // $this->eventStartProcessingDocument($oldId, $opusDocumentElement->getLineNo());

        $docId = null;

        // Check if docId for update is provided
        if ($opusDocumentElement->hasAttribute('docId') && $this->isUpdateExistingDocuments()) {
            $docId           = intval($opusDocumentElement->getAttribute('docId'));
            $this->currentId = $docId;
            $opusDocumentElement->removeAttribute('docId');
        }

        $doc = $this->createDocument($docId);

        $this->processAttributes($opusDocumentElement->attributes, $doc);
        $this->processElements($opusDocumentElement->childNodes, $doc);

        return $doc;
    }

    /**
     * @throws NotFoundException
     */
    protected function createDocument(?int $docId = null): ?DocumentInterface
    {
        if ($docId !== null) {
            $doc = Document::get($docId);
            $this->resetDocument($doc);
        } else {
            $doc = Document::new();
            // TODO log 'Ingore value of attribute docId'
        }

        return $doc;
    }

    /**
     * Allows certain fields to be kept on update.
     */
    public function setFieldsToKeepOnUpdate(?array $fields): self
    {
        $this->fieldsToKeepOnUpdate = $fields;
        return $this;
    }

    public function getFieldsToKeepOnUpdate(): ?array
    {
        return $this->fieldsToKeepOnUpdate;
    }

    /**
     * TODO refactor this - the metadata modell changes - enrichments
     * TODO should this be here at all or should the update process be outside the XML parser - probably! (later)
     */
    protected function resetDocument(DocumentInterface $doc)
    {
        $fields = [
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

            // 'ServerDateCreated', TODO do not delete ServerDateCreated when updating document (no default in db)
            'ServerDateModified',
            'ServerDatePublished',
            'ServerDateDeleted',
        ];

        $fieldsToDelete = array_diff($fields, $this->fieldsToKeepOnUpdate ?? []);

        $doc->deleteFields($fieldsToDelete);
    }

    /**
     * TODO use data model description (configurable, expandable)
     * TODO should not contain code for specific fields - there are/will be other boolean fields
     */
    protected function processAttributes(DOMNamedNodeMap $attributes, DocumentInterface $doc)
    {
        foreach ($attributes as $attribute) {
            $method = 'set' . ucfirst($attribute->name);
            $value  = trim($attribute->value);

            if ($attribute->name === 'belongsToBibliography') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }

            $doc->$method($value);
        }
    }

    /**
     * TODO convert into a configurable, rule based system?
     */
    protected function processElements(DOMNodeList $elements, DocumentInterface $doc)
    {
        $this->filesAdded = false; // TODO this is from Importer and need to be adapted for general use

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

    protected function handleTitleMain(DOMNode $node, DocumentInterface $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $t = $doc->addTitleMain();
                $t->setValue(trim($childNode->textContent));
                $t->setLanguage(trim($childNode->getAttribute('language')));
            }
        }
    }

    protected function handleTitles(DOMNode $node, DocumentInterface $doc)
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

    protected function handleAbstracts(DOMNode $node, DocumentInterface $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $t = $doc->addTitleAbstract();
                $t->setValue(trim($childNode->textContent));
                $t->setLanguage(trim($childNode->getAttribute('language')));
            }
        }
    }

    protected function handlePersons(DOMNode $node, DocumentInterface $doc)
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

                if (
                    $childNode->hasAttribute('allowEmailContact') && filter_var(
                        $childNode->getAttribute('allowEmailContact'),
                        FILTER_VALIDATE_BOOLEAN
                    )
                ) {
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

    protected function handlePersonIdentifiers(DOMNode $identifiers, PersonInterface $person): void
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

    protected function handleKeywords(DOMNode $node, DocumentInterface $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $s        = Subject::new();
                $language = $childNode->getAttribute('language');
                $s->setLanguage($language ?: 'deu');
                $type = $childNode->getAttribute('type');
                $s->setType($type ?: 'uncontrolled');
                $s->setValue(trim($childNode->textContent));
                $doc->addSubject($s);
            }
        }
    }

    protected function handleDnbInstitutions(DOMNode $node, DocumentInterface $doc)
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
                    if ($inst->$method() === '1') {
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

    protected function handleIdentifiers(DOMNode $node, DocumentInterface $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $i = $doc->addIdentifier();
                $i->setValue(trim($childNode->textContent));
                $i->setType($childNode->getAttribute('type'));
            }
        }
    }

    protected function handleNotes(DOMNode $node, DocumentInterface $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $n = $doc->addNote();
                $n->setMessage(trim($childNode->textContent));
                $n->setVisibility(
                    filter_var($childNode->getAttribute('visibility'), FILTER_VALIDATE_BOOLEAN)
                );
            }
        }
    }

    protected function handleCollections(DOMNode $node, DocumentInterface $doc)
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

    protected function handleSeries(DOMNode $node, DocumentInterface $doc)
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
                    $this->errorMissingObject($msg);
                }
            }
        }
    }

    /**
     * Processes the enrichments in the document xml.
     *
     * TODO add unit test - a bug that prevented the NotFoundException was not automatically detected
     * TODO Enrichment keys do not need to be registered anymore - no need for error message
     */
    protected function handleEnrichments(DOMNode $node, DocumentInterface $doc)
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
     */
    protected function addEnrichment(DocumentInterface $doc, string $key, string $value)
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

    protected function handleLicences(DOMNode $node, DocumentInterface $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $licenceId = trim($childNode->getAttribute('id'));
                try {
                    $l = Licence::get($licenceId);
                    $doc->addLicence($l);
                } catch (NotFoundException $e) {
                    $msg = 'licence id ' . $licenceId . ' does not exist: ' . $e->getMessage();
                    $this->errorMissingObject($msg);
                }
            }
        }
    }

    protected function handleDates(DOMNode $node, DocumentInterface $doc): void
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
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
        if ($this->getImportPath() === null) {
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
                $this->handleFile($doc, $name, $baseDir, $path, $childNode);
            }
        }

        // $this->setFilesAdded(true); TODO is this still needed?
    }

    /**
     * Add a single file to a Document.
     */
    protected function handleFile(
        DocumentInterface $doc,
        string $name,
        ?string $basePath = null,
        ?string $relPath = null,
        ?DOMNode $childNode = null
    ) {
        $fullPath = $this->getImportPath();

        if (null !== $basePath) {
            $fullPath .= $basePath . DIRECTORY_SEPARATOR;
        }
        $fullPath .= $relPath ?? $name;

        // TODO move to FilesProcessor?
        if (! is_readable($fullPath)) {
            $this->log('Cannot read file ' . $fullPath . ': make sure that it is contained in import package');
            return;
        }

        $file = File::new();

        $checksum     = null;
        $checksumAlgo = null;

        if ($childNode !== null) {
            $this->handleFileAttributes($childNode, $file);

            $comments = $childNode->getElementsByTagName('comment');
            if ($comments->length === 1) {
                $comment = $comments->item(0);
                $file->setComment(trim($comment->textContent));
            }

            $checksums = $childNode->getElementsByTagName('checksum');
            if ($checksums->length !== 0) {
                $checksumElement = $checksums->item(0);
                $checksum        = trim($checksumElement->textContent);
                $checksumAlgo    = $checksumElement->getAttribute('type');
            }
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

        $filesProc = $this->getFilesProcessor();

        if (null !== $filesProc) {
            $filesProc->addFile($doc, $file, $checksum, $checksumAlgo);
        }
    }

    protected function handleFileAttributes(DOMNode $node, File $file): void
    {
        // TODO generate list dynamically from model description?
        $attrsToConsider = [
            'language',
            'displayName',
            'visibleInOai',
            'visibleInFrontdoor',
            'sortOrder',
        ];

        foreach ($attrsToConsider as $attribute) {
            $value = trim($node->getAttribute($attribute));

            if ($value === '') {
                continue;
            }

            $fieldName = ucfirst($attribute);

            // TODO use FieldDescriptor to determine handling of field type
            switch ($attribute) {
                case 'displayName':
                    $fieldName = 'Label';
                    break;
                case 'visibleInFrontdoor':
                case 'visibleInOai':
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'sortOrder':
                    $value = intval($value);
                    break;
            }

            $methodName = "set{$fieldName}";
            $file->$methodName($value);
        }
    }

    protected function errorMissingObject(string $msg): void
    {
        $errorHandler = $this->getErrorHandler();

        if (null !== $errorHandler) {
            $errorHandler->errorMissingObject($msg);
        } else {
            throw new Exception($msg);
        }
    }

    /**
     * Returns path to document files.
     */
    public function getImportPath(): ?string
    {
        return $this->importPath;
    }

    /**
     * Set path to document files.
     */
    public function setImportPath(?string $importPath): self
    {
        $this->importPath = $importPath;
        return $this;
    }

    /**
     * Return true, if all files, ignoring MIME type, should be imported.
     */
    public function isImportAllFiles(): bool
    {
        return $this->importAllFiles;
    }

    /**
     * Set true if all files, ignoring MIME type, should be imported.
     */
    public function setImportAllFiles(bool $importAllFiles): self
    {
        $this->importAllFiles = $importAllFiles;
        return $this;
    }

    public function getCurrentId(): int
    {
        return $this->currentId;
    }

    public function getCurrentLineNo(): int
    {
        return $this->currentLineNo;
    }

    public function getCurrentReferenceId(): ?string
    {
        return $this->oldId;
    }

    public function setUpdateExistingDocuments(bool $updateExistingDocuments): self
    {
        $this->updateExistingDocuments = $updateExistingDocuments;
        return $this;
    }

    public function isUpdateExistingDocuments(): bool
    {
        return $this->updateExistingDocuments;
    }

    /**
     * TODO find better name to make clear it is meant for the CURRENT document
     */
    public function isFilesAdded(): bool
    {
        return $this->filesAdded;
    }

    public function setFilesProcessor(?FilesProcessor $filesProcessor): self
    {
        $this->filesProcessor = $filesProcessor;
        return $this;
    }

    public function getFilesProcessor(): ?FilesProcessor
    {
        return $this->filesProcessor;
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
