<?php

require_once 'HistoricalMarcRecord.php';


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
class MzkMarcRecord extends HistoricalMarcRecord
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
        parent::__construct($data, $oaiID, $source);
    }
    
    public function toSolrArray() {
        $data = parent::toSolrArray();
        
        $field = parent::getField('100');
        if ( !empty ($field)) {
            $data['author'] = parent::getSubfields($field,"abcde");
        }
        
        $field = parent::getField('100');
        if ( !empty ($field)) {
            $data['author-letter'] = parent::getSubfields($field,"a");
        }

        return $data;
    }  

    public function getID()
    {
        if (substr($this->getField('001'), 0, 5) === "mzk03") {
            return substr($this->getField('001'), 5);
        }
        else return $this->getField('001');
    }

    public function checkRecord() {
        if (!parent::checkRecord()) {
            return false;
        }
        $valueArray = $this->getFieldsSubfields(array(array(MarcRecord::GET_NORMAL, '991', array('s'))));
        if (count($valueArray) > 0 && preg_match('/SKRYTO/i', $valueArray[0])) {
            return false;
        }
        $valueArray = $this->getFieldsSubfields(array(array(MarcRecord::GET_NORMAL, '992', array('a'))));
        if (count($valueArray) > 0 && preg_match('/SUPPRESSED/i', $valueArray[0])) {
            return false;
        }
        $valueArray = $this->getFieldsSubfields(array(array(MarcRecord::GET_NORMAL, '990', array('a'))));
        if (count($valueArray) > 0 && preg_match('/AZ/i', $valueArray[0])) {
            return false;
        }
        return true;
    }

} 

