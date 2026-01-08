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

use function array_key_exists;
use function gmdate;
use function trim;

/**
 * Additional enrichments for imported documents.
 *
 * This class holds OPUS specific enrichments that are associated with every
 * document that is imported via SWORD API.
 *
 * opus.import.user     : name of user (as used in HTTP Basic Auth) that issued
 *                        the SWORD request
 * opus.import.date     : datestamp of import
 * opus.import.file     : name of package file (zip / tar archive) that was used
 *                        as SWORD payload (as specified in HTTP
 *                        Content-Disposition header)
 * opus.import.checksum : md5 checksum of SWORD package (as specified in HTTP
 *                        Content-MD5 header)
 *
 * TODO Does this class make sense?
 */
class AdditionalEnrichments implements ImportEnrichmentInterface
{
    /** @var array */
    private $enrichmentMap;

    public function __construct()
    {
        // TODO are those defaults, especially 'sword', useful here?
        // TODO Should it be GM or local time? It is probably more important what is displayed for users.
        $this->addEnrichment(self::OPUS_IMPORT_DATE, gmdate('c'));
        $this->addEnrichment(self::OPUS_SOURCE, 'sword');
    }

    /**
     * @param string $key
     * @param string $value
     */
    public function addEnrichment($key, $value)
    {
        $this->enrichmentMap[$key] = $value;
    }

    /**
     * @return array
     */
    public function getEnrichments()
    {
        return $this->enrichmentMap;
    }

    /**
     * @param string $value
     */
    public function addUser($value)
    {
        $this->addEnrichment(self::OPUS_IMPORT_USER, trim($value));
    }

    /**
     * @param string $value
     */
    public function addFile($value)
    {
        $this->addEnrichment(self::OPUS_IMPORT_FILE, trim($value));
    }

    /**
     * @param string $value
     */
    public function addChecksum($value)
    {
        $this->addEnrichment(self::OPUS_IMPORT_CHECKSUM, trim($value));
    }

    /**
     * @return null|string
     */
    public function getChecksum()
    {
        return $this->getValue(self::OPUS_IMPORT_CHECKSUM);
    }

    /**
     * @return null|string
     */
    public function getFileName()
    {
        return $this->getValue(self::OPUS_IMPORT_FILE);
    }

    /**
     * @param string $source
     * @return $this
     */
    public function setSource($source)
    {
        $this->addEnrichment(self::OPUS_SOURCE, $source);
        return $this;
    }

    /**
     * @param string $date
     * @return $this
     */
    public function setDate($date)
    {
        $this->addEnrichment(self::OPUS_IMPORT_DATE, $date);
        return $this;
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function getValue($key)
    {
        if (! array_key_exists($key, $this->enrichmentMap)) {
            return null;
        }
        return $this->enrichmentMap[$key];
    }
}
