<?php
/*
    EmailLabs_Sync is used to synchronize EmailLabs mailing lists.
    Copyright (C) 2010 Marko Sanković

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Holds EmailLabs parsed record results in associative array.
 *
 * @category   EmailLabs
 * @package    EmailLabs_Result
 */
class EmailLabs_Result_Record extends EmailLabs_Result_Data
{
    /**
     * Parse RECORD XML result.
     *
     * @return EmailLabs_Result_Record
     */
    public function parse()
    {
        // If error occured parse general data
        if($this->isError()) {
            parent::parse();
            return $this;
        }
        
        // Parse all RECORD elements
        $records = array();
        foreach($this->getXml()->RECORD as $record) {
            if($record->count() > 0) {
                // For each child DATA
                $children = $record->children();
                $data = array();
                foreach ($children as $child) {
                    // Get attributes
                    $attributes = array();
                    foreach ($child->attributes() as $key => $value) {
                        $attributes[$key] = $value->__toString();
                    }
                    // Special array types: extra, demographic, option
                    $value = $child->__toString();
                    if($attributes['type'] == 'extra') $data['extra'][$attributes['id']] = $value;
                    else if($attributes['type'] == 'demographic') $data['demographic'][$attributes['id']] = $value;
                    else if($attributes['type'] == 'option') $data['option'][$attributes['id']] = $value;
                    // Type only
                    else $data[$attributes['type']] = $value;
                }
                $records[] = $data;
            }
        }
        // Set data
        $this->_data = $records;
        return $this;
    }
    
}
