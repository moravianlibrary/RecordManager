<?php

require_once 'CistBrnoMarcRecord.php';
/**
 * MarcRecord Class - local customization for cistbrno
 *
 * This is a class for processing MARC records.
 *
 * @category DataManagement
 * @package  RecordManager
 * @author   Michal Merta <merta.m@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/moravianlibrary/RecordManager
 */
class KjmMarcRecord extends CistBrnoMarcRecord
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

    public function toSolrArray() 
    {
        $data = parent::toSolrArray();
        
        $data['institution'] = $this->getHierarchicalInstitutions('993', 'l');
        return $data;
    }
    
    public function getFormat() {
    	$prefix = substr($this->getID(), 0, 2);
    	if (strcasecmp($prefix, 'CD') ===  0) {
    		$field = $this->getField('245');
    		if ($field && ($subfield = $this->getSubfield($field, 'h'))) {
    			switch ($subfield) {
    				case 'videozáznam' : return 'kjm_video';
    				case 'elektronický zdroj': return 'kjm_electronic_resources';
    				case 'zvukový' : return 'kjm_audio';
    			}
    		}
    	}
    	return $this->unifyFormats(array($prefix));
    }
    
    public function checkRecord() {
    	return substr($this->getID(), 0, 2) === 'VS' ? false : true;
    }

    public function getHierarchicalInstitutions($field, $subfield) 
    {
        $institution = $this->getInstitution();
        $depth = 0;
        $instArray = array();
        $instArray[] = $depth++ . '/' .$institution . '/';

        $fld = $this->getFields('993');
        foreach ($fld as $current) {
            $subfld = $this->getSubfield($current, 'l');
            if (!preg_match('/.*\$~.*/', $subfld)) {
                continue;
            }
            $subfld = preg_split('/\$~/', $subfld);
            $id = $subfld[0];

            if (!$id) {
                global $logger;
                $logger->log('getHierarchicalInstitutions', 'Field ' .  $field . $subfield . ' empty for record ' . $this->getID(), LOGGER::WARNING ) ;
                $id = 0;
            }

            if (preg_match('/02.*/', $id)) {
                $id = '02';
            } elseif (preg_match('/0.*/', $id)) {
                $id = '0';
            } elseif (preg_match('/16.*/', $id)) {
                $id = '16';
            } elseif (preg_match('/30.*/', $id)) {
                $id = '30';
            } elseif (preg_match('/40.*/', $id)) {
                $id = '40';
            }

            if ($this->settings['institution_hierarchy']) {
                $instMapping = parse_ini_file($this->settings['institution_hierarchy']);
                if (isset($instMapping[$id])) {
                    $instArray[] = $depth . '/' . $institution . '/' . $instMapping[$id] . '/';
                }
            }
        }
        return $instArray;

    }
    
    public function parseXML($xml)
    {
       $document = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOENT);
       if ($document === false) {
             throw new Exception('MarcRecord: failed to parse from XML');
       }

       $query = $document->leader;
       if ($query) {
           $this->fields['000'] = $query->__toString();
       } else {
           $this->fields['000'] = '';
       }

       $query = $document->controlfield;
       foreach ($query as $field) {
           $this->fields[$field->attributes()->tag->__toString()] = $field->__toString();
       }

       $query = $document->datafield;
       foreach ($query as $field) {
           $newField = array(
              'i1' => str_pad($field->attributes()->ind1 ? $field->attributes()->ind1->__toString() : '' , 1),
              'i2' => str_pad($field->attributes()->ind2 ? $field->attributes()->ind2->__toString() : '' , 1)
           );
           $subfieldQuery = $field->subfield;
           foreach ($subfieldQuery as $subfield) {
               $newField['s'][] = array($subfield->attributes()->code->__toString() => $subfield->__toString());
           }
           $tag = $field->attributes()->tag->__toString();
           if (substr($tag, 0, 2) != '00') {
               $this->fields[$tag][] = $newField;
           }
       }
    }
   
    public function toISO2709()
    {
        global $configArray;

        $leader = str_pad(substr($this->fields['000'], 0, 24), 24);

        $directory = '';
        $data = '';
        $datapos = 0;
        foreach ($this->fields as $tag => $fields) {
            if ($tag == '000') {
                continue;
            }
            if (strlen($tag) != 3) {
                error_log("Invalid field tag: '$tag', id " . $this->getField('001'));
                continue;
            }
            if (!is_array($fields)) $fields = array($fields);
            foreach ($fields as $field) {
                $fieldStr = '';
                if (is_array($field)) {
                    $fieldStr = $field['i1'] . $field['i2'];
                    if (isset($field['s']) && is_array($field['s'])) {
                        foreach ($field['s'] as $subfield) {
                            $subfieldCode = key($subfield);
                            $fieldStr .= MARCRecord::SUBFIELD_INDICATOR . $subfieldCode . current($subfield);
                        }
                    }
                } else {
                    // Additional normalization here so that we don't break ISO2709 directory in SolrUpdater
                    $fieldStr = MetadataUtils::normalizeUnicode($field);
                }
                $fieldStr .= MARCRecord::END_OF_FIELD;
                $len = strlen($fieldStr);
                if ($len > 9999) {
                    return '';
                }
                if ($datapos > 99999) {
                    return '';
                }
                $directory .= $tag . str_pad($len, 4, '0', STR_PAD_LEFT) . str_pad($datapos, 5, '0', STR_PAD_LEFT);
                $datapos += $len;
                $data .= $fieldStr;
            }
        }
        $directory .= MARCRecord::END_OF_FIELD;
        $data .= MARCRecord::END_OF_RECORD;
        $dataStart = strlen($leader) + strlen($directory);
        $recordLen = $dataStart + strlen($data);
        if ($recordLen > 99999) {
            return '';
        }

        $leader = str_pad($recordLen, 5, '0', STR_PAD_LEFT)
            . substr($leader, 5, 7)
            . str_pad($dataStart, 5, '0', STR_PAD_LEFT)
            . substr($leader, 17);
        return $leader . $directory . $data;
    }
}
