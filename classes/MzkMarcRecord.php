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

require_once 'MarcRecord.php';
require_once 'MetadataUtils.php';
require_once 'Logger.php';

/**
 * MarcRecord Class - local customization for MZK
 *
 * This is a class for processing MARC records.
 *
 * @category DataManagement
 * @package  RecordManager
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/moravianlibrary/RecordManager
 */
class MzkMarcRecord extends MarcRecord
{
    private $SOURCE_DIRECTORY = "/path/to/nowhere/";


    /**
     * Constructor
     *
     * @param string $data   Metadata
     * @param string $oaiID  Record ID received from OAI-PMH (or empty string for file import)
     * @param string $source Source ID
     */
    public function __construct($data, $oaiID, $source)
    {
        parent::__construct($data, $oaiID, $source);
    }

    public function toSolrArray() {
        $data = parent::toSolrArray();

        $data['title_full'] = parent::getFieldSubfields('245',
                array('a','b','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z',
                        '0','1','2','3','4','5','6','7','8','9'));

       $value = parent::getFieldsSubfields( array(
                array(MarcRecord::GET_NORMAL, '245', array('a')),
                array(MarcRecord::GET_NORMAL, '245', array('b')),
                array(MarcRecord::GET_NORMAL, '245', array('n')),
                array(MarcRecord::GET_NORMAL, '245', array('p'))
        ),
                true);
       
        $value = is_array($value) ?  array_pop($value) :  $value;
        if ($value == null) {
            $value = '';
        }
        $data['title_display'] = $value;

        $data['created'] = $this->getCreated();
        $data['title_short'] = $this->getShortTitle();
        $value = parent::getFieldsSubfields( array(
                array(MarcRecord::GET_NORMAL, '260', array('c')),true));
        $value = is_array($value) ? array_pop($value) : $value;
        if ($value == null) {
            $value = '';
        }
        $data['publishDate_display'] = $value;
        $data['mzk_visible_str_ns'] = $this->getVisible();
        $data['fulltext'] = $this->getFullText();


        $data['scale_int_mv'] = '';
        $field = $this->getField('034');
        if ($field) {
            $data['scale_int_mv'] = $this->getSubfield($field, 'b') != null ?  $this->getSubfield($field, 'b') : '';
        }

        $field = parent::getField("Z30");
        $data['callnumber_second'] = parent::getSubfield($field,'9');
        $data['source'] = "MZK";
        $data['nbn'] = $this->getNbn();
        
        $data['publishDateFacet'] = $this->getPublishedDate();
        return $data;
    }


    /**
     *
     * @return string "visible" or "hidden"
     */
    public function getVisible() {
        $value = '';
        $field = $this->getField('MZK');
        if ($field) {
            $value = $this->getSubfield($field, 's') != null ?  $this->getSubfield($field, 's') : '';
        }
        if ($value == "SKRYTO") {
            return "hidden";
        }
         
         
        $field = $this->getField('998');
        if ($field) {
            $value = $this->getSubfield($field, 'a') != null ?  $this->getSubfield($field, 'a') : '';
        }
        if ($value == "AZ") {
            return "hidden";
        }
         
        $field = $this->getField('STA');
        if ($field) {
            $value = $this->getSubfield($field, 'a') != null ?  $this->getSubfield($field, 'a') : '';
        }
        if ($value == "SUPPRESSED" || $value == "DELETED") {
            return "hidden";
        }
         
        return "visible";
    }

    public function getNbn() {
        $nbn = '';
        $field = $this->getField('015');
        if ($field) {
            $nbn = $this->getSubfield($field, 'a') != null ?  $this->getSubfield($field, 'a') : '';
        }
        return $nbn;
    }

    public function getCreated() {
        $fields = parent::getFields("005");
        return reset($fields) ? substr($fields[0], 0, 8) : '';
    }

    public function getShortTitle() {
        $title = parent::getFieldsSubfields( array(
                array(MarcRecord::GET_NORMAL, '245', array('a')),
                true))[0];

        $fixed_title = $title;
        //remove : ] / from the end of title
        do {
            $title = $fixed_title;
            $fixed_title = preg_replace("/[:\]\/]]*$/", "", $title);
        } while ($fixed_title != $title);


        $fixed_title = $title;
        //remove [ from begin of title
        do {
            $title = $fixed_title;
            $fixed_title = preg_replace("/^[\[]/", '', $title);
        } while ($fixed_title != $title);

        return $fixed_title;
         
    }
     
    public function getFullText() {
        $nbn = $this->getNbn();
        $file_content = "";
        if ($nbn == "") {
            return $file_content;
        }

        $path = $this->SOURCE_DIRECTORY.$nbn.".txt";
         
        if (!file_exists($path))
            return $file_content;
         
        $handle = fopen($path,"r");
        if ($handle == false)
            return $file_content;
         
        while (!feof($handle)) {
            $file_content .= fread($handle, 1024);
        }
         
        return $content;
        	
    }

    public function getRelevancy() {
        $relevancy = "default";
        $title = parent::getFieldsSubfields("520a", true, true, false);
         
        //norm validity
        if ($title == "Norma je neplatná") {
            $relevancy =  "invalid_norm";
        }
         
        //newspaper and magazines
        $pse = parent::getFieldsSubfields("PSEq", true, true, false);
        if ($pse == date("y")) {
            $relevancy = "live_periodical";
        }
    }

    /**
     * @return boolean
     */
    public function isRetro() {
        $field = parent::getFieldsSubfields("MZKc", true, true, false);
        if (is_string($field) && substr($field, 0, 3) === "NOV") {
            return false;
        }
        return true;
    }

    /**
     *
     * @return sorted array[int] of published years:
     */
    public function getPublishedDate() {
        $result = array();
        $current_year = date("Y");
        $fields = parent::getFieldsSubfields(array(
                array(MarcRecord::GET_NORMAL, 'Z30', array('a'))));
         
        foreach ($fields as $field) {
            if (preg_match("/\d+/", $field)) {
                $var = intval($field);
                //maybe one day array_unique will become usable
                if (!in_array($var, $result))
                    array_push($result, $var);
            }

            $parts = explode('-', $field);
            if (strpos($field, '-') !== FALSE) {
                if (count($parts) == 2) {
                    if (preg_match("/\d+/", $parts[0]) &&
                    !in_array(intval($parts[0]), $result)) {
                        array_push($result, intval($parts[0]));
                    }
                     
                    if (preg_match("/\d+/", $parts[1]) &&
                    !in_array(intval($parts[1]), $result)) {
                        array_push($result, intval($parts[1]));
                    }
                }
            }
        }
         
        $value = parent::getField('008');
         
        if ($value == '' || strlen($value) < 15) {
            sort($result,SORT_NUMERIC);
            return $result;
        }
         
        $from = substr($value, 7, 4);
        $to = substr($value, 11, 4);

        $from = intval($from);
        $to = intval($to);

        if ($from === false || !is_int($from)) {
            $from = 0;
        }


        if ($to === false || !is_int($to)) {
            $to = 0;
        }


        if ($from == 0 && $to == 0) {
            sort($result,SORT_NUMERIC);
            return $result;
        }
        	
        if ($from == 0){
            $from = $to;
        }
        if ($to == 0){
            $to = $from;
        }

        sort($result,SORT_NUMERIC);
        return $result;
    }


}