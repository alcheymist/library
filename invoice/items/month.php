<?php
namespace invoice;

abstract class month extends item_unary{
    //
    //
    public function __construct(record $record) {
        
        parent::__construct($record, 'month','Month');
    }
   
    
}

