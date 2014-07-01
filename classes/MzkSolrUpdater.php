<?php
require_once 'SolrUpdater.php';

class MzkSolrUpdater extends SolrUpdater {
    
    /* specifies relative global priority of sources
     * first is source has highest priority
     */
    protected $globalPriority = array();

    protected $ignoredFields = array();
    
    protected $fieldPriority = array();

    /* each field will be part of merged record
    */
    protected $lastSourceKeys = array();


    public function __construct($db, $basePath, $log, $verbose, $portal = '') 
    {
        parent::__construct($db, $basePath, $log, $verbose);
        global $configArray;
        
        
        if (isset($configArray['Merge Records']) && isset($configArray['Merge Records'][$portal])) {
            $ini = parse_ini_file($configArray['Merge Records'][$portal]);
            if (!isset($ini['global'])) {
                throw new \Exception("No global priority defined in $portal");
            }
            $this->globalPriority = explode(',', $ini['global']);

            if (isset($ini['ignored_fields'])) {
               $this->ignoredFields = explode(',', $ini['ignored_fields']);
            }

            foreach ($ini as $currentField => $value) {
                if ($currentField == 'global' || $currentField == 'ignored_fields') continue;
                $this->fieldPriority[$currentField] = explode(',', $value);
            }
           

        } else {
            $this->log->log('__construct', "No merging settings found", LOGGER::INFO);
        }
        
    }


     /**
     * Overrides mergeRecords from parent class
     * 
     * @param string[] $merged Merged (base) record
     * @param string[] $add    Record to merge into $merged
     * 
     * @return string[] Resulting merged record
     */
    protected function mergeRecords($merged, $add)
    {
        //handle first record
        if (empty($merged)) {
            $this->lastSourceKeys = array();
            $merged = parent::mergeRecords($merged, $add);
            $source = $this->getInstitution($add);
            $sourceKeys = array();
            foreach(array_keys($merged) as $current) {
                $sourceKeys[$current] = $source;
            }
            $this->lastSourceKeys = $sourceKeys;
            foreach ($this->ignoredFields as $ignored) {
                unset($merged[$ignored]);
            }
            return $merged;
        }
        
        //handle adding records
        $merged['local_ids_str_mv'][] = $add['id'];
        foreach ($add as $currentField => $value) {
            if ( !in_array($currentField, $this->ignoredFields) 
             && array_key_exists($currentField, $add) 
             && $this->checkFieldPriority($currentField, $merged, $add)) {
             
                $currentField = $this->extractFieldName($currentField);
                $this->lastSourceKeys[$currentField] = $this->getInstitution($add);
                
                if (substr($currentField, -3, 3) == '_mv' || in_array($currentField, $this->mergedFields)) {   
              
                    if (!isset($merged[$currentField])) {
                        $merged[$currentField] = $value;
                    }
                    if (!is_array($merged[$currentField])) {
                        $merged[$currentField] = array($merged[$currentField]);
                    }
                    if (!is_array($value)) {
                        $value = array($value);
                    }
                    $merged[$currentField] = array_values(array_merge($merged[$currentField], $value));
                } else {
                    $merged[$currentField] = $add[$currentField];
                }
            }
        }
        return $merged;
    }

    /**
    * @return boolean - true if $field in $add record has higher priority, false otherwise
    */
    protected function checkFieldPriority($field, &$merged, &$add) {

        //return false if there is nothing to add
        if (!isset($add[$field]) || empty($add[$field])) {
            return false;
        }

        $formerPriority = array_key_exists($field, $this->lastSourceKeys) ? $this->lastSourceKeys[$field] : '';
        $addPriority = $this->getInstitution($add);

        foreach ($this->getFieldPriorityArray($field) as $currentPriority) {
            
            if ($this->evaluateExpresion($currentPriority, $merged, $add)) {
                if ($formerPriority == $currentPriority) {
                    return false;
                }
                
                if ($addPriority == $currentPriority) {
                    return true;
                }
            }
        }
        return true;
    }
    
    /**
    * @return array of priorities for given field
    */
    protected function getFieldPriorityArray($field) {
         return array_key_exists($this->extractFieldName($field), $this->fieldPriority) ? $this->fieldPriority[$field] : $this->globalPriority;
    }

    protected function extractFieldName($field) {
        $parts = explode(':', $field);
        return (count($parts) == 1) ? $field : $parts[0]; 
    }
    
    /**
    * @return true if expression should be used
    */
    protected function evaluateExpresion($field, &$merged, &$add) {
        $parts = explode(':', $field);
        if (count($parts) == 1) { 
            return true;
        }

        if (!method_exists($this, $parts[1])) {
            $this->log->log('evalueateExpresion', "Calling undefined method ". $parts[1], LOGGER::WARRNING);
        }
        return call_user_func_array(array($this,$parts[1]), array($field, $merged, $add));

    }

    protected function getInstitution($record) {
        return $record['recordtype']; 
    }

    protected function dummyEvaluate($field, &$merged, &$add) {
        return false;
    }
}
