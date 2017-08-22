<?php
/**
 * MarcRecord Class
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2011-2013
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category DataManagement
 * @package  RecordManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/KDK-Alli/RecordManager
 */

require_once __DIR__.'/../PortalsCommonMarcRecord.php';
require_once __DIR__.'/../MetadataUtils.php';
require_once __DIR__.'/../Logger.php';

/**
 * MarcRecord Class - local customization for MZK
 *
 * This is a class for processing MARC records.
 *
 * @category DataManagement
 * @package  RecordManager
 * @author   Michal Merta
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/moravianlibrary/RecordManager
 */
class HistoricalMarcRecord extends PortalsCommonMarcRecord
{

    /**
     * Constructor
     *
     * @param string $data   Metadata
     * @param string $oaiID  Record ID received from OAI-PMH (or empty string for file import)
     * @param string $source Source ID
     */
    public function __construct($data, $oaiID, $source)
    {
        global $configArray;
        if (!$configArray['HF']['format_unification_array']) {
            throw new Exception("No format unification for HF");
        }
        
        parent::__construct($data, $oaiID, $source);
    }
    
    public function toSolrArray() {
        $data = parent::toSolrArray();
        
        if (isset($data['url']) && is_array($data['url'])
                && isset($data['url'][0])) {
            $data['status_str_mv'] = 'online';
        } else {
            $data['status_str_mv'] = 'offline';
        }
        
        $field = parent::getField('260');
        $pubYear = null;
        if ($field) {
            $year = parent::getSubfield($field, 'c');
            $matches = array();
            if ($year && preg_match('/(\d{4})/', $year, $matches)) {
                 $pubYear = $matches[1];
                 $data['publishDate_display'] = $pubYear;
            }
            
        }
        
        $field = parent::getField('245');
        if ($field) {
            $subField = parent::getSubfield($field, 'h');
            if (isset($subField) && is_string($subField)) {
                if (preg_match('/rukopis/i', $subField)) {
                    $data['format'] = array('hf_manuscripts');
                }
                if (preg_match('/mikrodokument/i', $subField)) {
                    $data['format'] = array('hf_microforms');
                }
            }
        }
        
        if ($pubYear != null && $pubYear <= 1500) {
            if ($pubYear > 1440) {
                $data['format'] = 
                    is_array($data['format']) && in_array('hf_manuscripts', $data['format']) ? 
                        array('hf_manuscripts') : array('hf_incunable');
            } else {
                $data['format'] = array('hf_manuscripts');
            }
        }

        return $data;
    }  
    
    protected function unifyFormats($formats) {
        global $configArray;
        global $logger;
        $unificationArray = &$configArray['HF']['format_unification_array'];
        $unified = array();
        foreach ($formats as $format) {
            if (!$unificationArray[$format]) {
                $logger->log('unifyHFFormats', "No mapping found for: $format \t". $this->getID(), Logger::WARNING);
                $unified[] = 'unmapped_' . $format;
            } else {
                $unified = array_merge($unified, explode(',', $unificationArray[$format]));
            }
        }
        return array_unique($unified);
    }
} 

