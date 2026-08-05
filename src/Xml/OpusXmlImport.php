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
use Opus\Common\Licence;
use Opus\Common\LoggingTrait;
use Opus\Common\Model\NotFoundException;
use Opus\Common\Person;
use Opus\Common\Series;
use Opus\Common\Subject;

use function array_diff;
use function substr;
use function trim;
use function ucfirst;

/**
 * Convert OPUS-XML into Document object.
 *
 * OPUS-XML is used in multiple places
 * - MetadataImport
 * - SWORD
 * - DeepGreen (JATS->OPUS-XML->Document)
 *
 * TODO should this only process a single document at a time?
 * TODO callback for every document?
 */
class OpusXmlImport
{
    use LoggingTrait;

    /** @var DOMDocument */
    private $xml;

    /** @var string */
    private $xmlString;

    /** @var array */
    private $fieldsToKeepOnUpdate = [];

    /** @var XmlDocument */
    private $xmlDocument;

    /** @var int */
    private $importedDocumentsCount;

    /** @var int */
    private $skippedDocumentsCount;

    /**
     * TODO cleanup variable names, $xml
     * TODO use a storeDocument function that can be overwritten
     * TODO track skipped documents/errors
     *
     * @throws MetadataImportInvalidXmlException
     */
    public function import(string $xml)
    {
        $this->xmlDocument = new XmlDocument();

        $this->xml = $this->loadXml($xml);
        $this->xmlDocument->validate();

        $this->importedDocumentsCount = 0;
        $this->skippedDocumentsCount  = 0;

        foreach ($this->xml->getElementsByTagName('opusDocument') as $opusDocumentElement) {
            $doc = $this->processDocument($opusDocumentElement);

            if (null !== $doc) {
                $numOfDocsImported++;
                $docId = $doc->getId(); // TODO document should not always be stored - what about additional processing (rules)?
                $output->writeln("... Document {$docId} imported successfully"); // TODO mention oldId?
            } else {
                $this->skippedDocumentsCount++;
            }
        }
    }

    /**
     * TODO separate updating documents - What is needed?
     */
    protected function processDocument(DOMNode $opusDocumentElement): ?DocumentInterface
    {
        // save oldId for later referencing of the record under consideration
        $oldId = $opusDocumentElement->getAttribute('oldId');
        $opusDocumentElement->removeAttribute('oldId');

        // TODO there is not always an old ID
        $output->writeln("Start processing of record #{$oldId} ...");

        // @var DocumentInterface
        $doc = null;

        if ($opusDocumentElement->hasAttribute('docId')) {
            // perform metadata update on given document
            $docId = intval($opusDocumentElement->getAttribute('docId'));

            try {
                $doc   = $this->getDocument($docId);
            } catch (NotFoundException $e) {
                $output->writeln("<error>Could not load document #{$docId} from database: " . $e->getMessage() . "</error>");
                $this->appendDocIdToRejectList($oldId);
                return null;
            }

            // TODO process document not found
            $opusDocumentElement->removeAttribute('docId');
            $this->resetDocument($doc);
        } else {
            // create new document
            $doc = $this->getDocument();
        }

        try {
            $this->processAttributes($opusDocumentElement->attributes, $doc);
            $this->processElements($opusDocumentElement->childNodes, $doc);
        } catch (Exception $e) {
            $output->writeln("<error>Error processing document #{$oldId}: " . $e->getMessage() . "</error>");
            $this->appendDocIdToRejectList($oldId);
            return null;
        }

        try {
            $docId = $doc->store();
        } catch (Exception $e) {
            $output->writeln("<error>Error saving imported document #{$oldId}: " . $e->getMessage() . "</error>");
            $this->appendDocIdToRejectList($oldId);
            return null;
        }
    }

    /**
     * @throws NotFoundException
     */
    protected function getDocument(?int $docId = null): ?DocumentInterface
    {
        if ($docId !== null) {
            return Document::get($docId);
        } else {
            return Document::new();
        }
    }

    /**
     * @return DOMDocument
     *
     * TODO review exception handling
     */
    protected function loadXml(string $xml)
    {
        try {
            return $this->xmlDocument->loadXML($xml);
        } catch (MetadataImportInvalidXmlException $exception) {
            throw new MetadataImportInvalidXmlException('XML is not well-formed.');
        }
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

    protected function resetDocument(DocumentInterface $doc)
    {
        $fieldsToDelete = array_diff([
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
        ], $this->fieldsToKeepOnUpdate);

        $doc->deleteFields($fieldsToDelete);
    }

    protected function processAttributes(DOMNamedNodeMap $attributes, DocumentInterface $doc)
    {
        foreach ($attributes as $attribute) {
            $method = 'set' . ucfirst($attribute->name);
            $value  = trim($attribute->value);
            // TODO use filtervar for BOOLEAN here
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
     * TODO convert into a configurable, rule based system?
     */
    protected function processElements(DOMNodeList $elements, DocumentInterface $doc)
    {
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

                if ($childNode->hasAttribute('allowEmailContact')
                    && ($childNode->getAttribute('allowEmailContact') === 'true'
                        || $childNode->getAttribute('allowEmailContact') === '1')) {
                    $link->setAllowEmailContact(true);
                }
            }
        }
    }

    protected function handleKeywords(DOMNode $node, DocumentInterface $doc)
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
                    throw new Exception('dnbInstitution id ' . $instId . ' does not exist: ' . $e->getMessage());
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
                $n->setVisibility($childNode->getAttribute('visibility'));
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
                    throw new Exception('collection id ' . $collectionId . ' does not exist: ' . $e->getMessage());
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
                    throw new Exception('series id ' . $seriesId . ' does not exist: ' . $e->getMessage());
                }
            }
        }
    }

    protected function handleEnrichments(DOMNode $node, DocumentInterface $doc)
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $key = trim($childNode->getAttribute('key'));
                // check if enrichment key exists
                try {
                    EnrichmentKey::get($key);
                } catch (NotFoundException $e) {
                    throw new Exception('enrichment key ' . $key . ' does not exist: ' . $e->getMessage());
                }

                $e = $doc->addEnrichment();
                $e->setKeyName($key);
                $e->setValue(trim($childNode->textContent));
            }
        }
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
                    throw new Exception('licence id ' . $licenceId . ' does not exist: ' . $e->getMessage());
                }
            }
        }
    }

    protected function handleDates(DOMNode $node, DocumentInterface $doc): void
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
}
