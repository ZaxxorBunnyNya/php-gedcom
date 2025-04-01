<?php

namespace Gedcom\Record\Head;

class Dest extends \Gedcom\Record
{
    protected $_dest;
    
    /**
     * Method setDest
     *
     * @param $dest $dest [explicite description]
     *
     * @return self
     */
    public function setDest($dest):self
    {
        $this->_dest = $dest;

        return $this;
    }
    
    /**
     * Method getDest
     *
     * @return string
     */
    public function getDest():string 
    {
        return $this->_dest;
    }

    // Add more properties and methods for sub-tags if needed
}