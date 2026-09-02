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
 * @copyright   Copyright (c) 2008, OPUS 4 development team
 * @license     http://www.gnu.org/licenses/gpl.html General Public License
 */

namespace Opus\Import\Xml;

use DOMDocument;

use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function trim;

use const DIRECTORY_SEPARATOR;
use const LIBXML_ERR_ERROR;
use const LIBXML_ERR_FATAL;
use const LIBXML_ERR_WARNING;

/**
 * A class for loading and validating XML.
 */
class XmlDocument
{
    /** @var DOMDocument|null */
    private $dom;

    /** @var array|null */
    private $errors;

    /** @var string */
    private $schemaPath = __DIR__ . DIRECTORY_SEPARATOR . 'opus-import.xsd';

    /**
     * Construct object optionally with existing DOMDocument.
     */
    public function __construct(?DOMDocument $dom = null)
    {
        if (null !== $dom) {
            $this->dom = $dom;
        }
    }

    /**
     * Loads an XML string
     *
     * @param  string $xml XML string
     * @return DOMDocument
     * @throws MetadataImportInvalidXmlException
     */
    public function loadXml($xml)
    {
        return $this->loadXmlData($xml);
    }

    /**
     * Loads XML from a file
     *
     * TODO Error handling for missing files etc.
     */
    public function load(string $xmlFilePath): DOMDocument
    {
        return $this->loadXmlData($xmlFilePath, true);
    }

    public function setDom(?DOMDocument $xml): self
    {
        $this->dom = $xml;
        return $this;
    }

    public function getDom(): ?DOMDocument
    {
        return $this->dom;
    }

    /**
     * Loads XML from a string or a file
     */
    protected function loadXmlData(string $xml, bool $isFile = false): DOMDocument
    {
        // Enable user error handling
        libxml_clear_errors();
        $useInternalErrors = libxml_use_internal_errors(true);

        $doc = new DOMDocument();
        if ($isFile) {
            // TODO: Error handling for missing files etc.
            $success = $doc->load($xml);
        } else {
            $success = $doc->loadXML($xml);
        }

        $this->errors = libxml_get_errors();

        // Disable user error handling
        libxml_use_internal_errors($useInternalErrors);
        libxml_clear_errors();

        if (! $success) {
            throw new MetadataImportInvalidXmlException($this->getErrorsPrettyPrinted());
        }

        $this->dom = $doc;

        return $doc;
    }

    /**
     * Validate loaded XML against a schema.
     *
     * @throws MetadataImportInvalidXmlException
     */
    public function validate(): void
    {
        // Enable user error handling
        libxml_clear_errors();
        $useInternalErrors = libxml_use_internal_errors(true);

        $success      = $this->dom->schemaValidate($this->getSchemaPath());
        $this->errors = libxml_get_errors();

        // Disable user error handling
        libxml_use_internal_errors($useInternalErrors);
        libxml_clear_errors();

        if (! $success) {
            throw new MetadataImportInvalidXmlException($this->getErrorsPrettyPrinted());
        }
    }

    /**
     * @return array|null
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Formats errors in a readable way.
     */
    public function getErrorsPrettyPrinted(): string
    {
        $errorMsg = '';
        foreach ($this->getErrors() as $error) {
            $errorMsg .= "\non line $error->line ";
            switch ($error->level) {
                case LIBXML_ERR_WARNING:
                    $errorMsg .= "(Warning $error->code): ";
                    break;
                case LIBXML_ERR_ERROR:
                    $errorMsg .= "(Error $error->code): ";
                    break;
                case LIBXML_ERR_FATAL:
                    $errorMsg .= "(Fatal Error $error->code): ";
                    break;
            }
            $errorMsg .= trim($error->message);
        }
        return $errorMsg;
    }

    public function setSchemaPath(?string $schemaPath): self
    {
        $this->schemaPath = $schemaPath;
        return $this;
    }

    public function getSchemaPath(): ?string
    {
        return $this->schemaPath;
    }
}
