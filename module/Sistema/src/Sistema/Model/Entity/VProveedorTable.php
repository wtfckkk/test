<?php
namespace Sistema\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use Zend\Db\ResultSet\ResultSet;

class VProveedorTable extends TableGateway

{
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)

    {
       return parent::__construct('view_w_proveedor', $adapter, $databaseSchema,$selectResultPrototype);
    }
    
    public function getProveedores()
    {        
        $datos = $this->select();
        $recorre = $datos->toArray();
        return $recorre;

    }
    public function getProveedoresId($id)
    {        
        $datos = $this->select(array("id"=>$id));
        $recorre = $datos->toArray();
        return $recorre;

    }
}