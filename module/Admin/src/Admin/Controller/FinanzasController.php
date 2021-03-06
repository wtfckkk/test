<?php

namespace Admin\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Session\Container;
use Zend\Validator\File\Size;

use Sistema\Util\FechasSys;

use Sistema\Model\Entity\General\PersonaTable;

use Sistema\Model\Entity\ReclamoTable;
use Sistema\Model\Entity\TipoAsuntoTable;
use Sistema\Model\Entity\CtacteTable;
use Sistema\Model\Entity\LavanderiaTable;
use Sistema\Model\Entity\InfocomgralTable;
use Sistema\Model\Entity\EdificacionTable;
use Sistema\Model\Entity\AscensorTable;
use Sistema\Model\Entity\FondosTable;
use Sistema\Model\Entity\MorososTable;
use Sistema\Model\Entity\TipoMultaTable;
use Sistema\Model\Entity\TrabajadorTable;
use Sistema\Model\Entity\UnidadTable;
use Sistema\Model\Entity\PersonaDetTable;
use Sistema\Model\Entity\VPagogcTable;
use Sistema\Model\Entity\ListaBancoTable;
use Sistema\Model\Entity\GCunidadTable;
use Sistema\Model\Entity\IngresoTable;
use Sistema\Model\Entity\MesesTable;
use Sistema\Model\Entity\MultaTable;
use Sistema\Model\Entity\ProveedorTable;
use Sistema\Model\Entity\TipoServicioTable;
use Sistema\Model\Entity\TipoEgresoTable;
use Sistema\Model\Entity\TipoContratoTable;
use Sistema\Model\Entity\EgresoTable;
use Sistema\Model\Entity\CobroTable;
use Sistema\Model\Entity\VProveedorTable;
use Sistema\Model\Entity\CicloAdminTable;
use Sistema\Model\Entity\TareaTable;
use Sistema\Model\Entity\EgresoTrabajadorTable;
use Sistema\Model\Entity\PartidaMantTable;
use Sistema\Model\Entity\AbonoTable;


use Admin\Form\FinancieraForm;
use Admin\Form\GeneralForm;
use Admin\Form\EdificacionForm;
use Admin\Form\FondoOperForm;
use Admin\Form\FondoResForm;
use Admin\Form\CajaChicaForm;
use Admin\Form\MultasForm;
use Admin\Form\IngresoPagoForm;
use Admin\Form\ProveedorForm;
use Admin\Form\EgresoForm;
use Admin\Form\PagoEgresoForm;
use Admin\Form\PagoTrabajadorForm;
use Admin\Form\CicloForm;
use Admin\Form\AbonoForm;


class FinanzasController extends AbstractActionController

{

    public function indexAction()

    {                                       
        $this->layout('layout/admin');
        $titulo = "Gesti&oacute;n Financiera";                                                

        $valores = array('titulo'=>$titulo);
        //return $this->forward()->dispatch('Admin\Controller\Infocom',array('action'=>'general'));
        return new ViewModel($valores);

    }
    
    public function resumenfinAction()
    {   
        
        //Conectamos con BBDD
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);
        //Abrimos Instancias
        $fon = new FondosTable($this->dbAdapter);
        $mor = new MorososTable($this->dbAdapter);
        $ing = new IngresoTable($this->dbAdapter);
        $egr = new EgresoTable($this->dbAdapter);      
        $cic = new CicloAdminTable($this->dbAdapter);
        $pmt = new PartidaMantTable($this->dbAdapter);
        
        
        //Obtenemos dia de cierre y calculamos restantes        
        $cierre = $cic->getCiclo();        
            if(date('j')>$cierre[0]['dia']){
                $dias_mes = cal_days_in_month(CAL_GREGORIAN, date('n'), date('Y'));
                $dif = ($dias_mes-date('j'))+$cierre[0]['dia']; 
                $mes_cierre =  date('F', strtotime('+1 month')) ;               
            }else{
                 $dif = $cierre[0]['dia']-date('j');    
                 $mes_cierre = date('F');        
            }  
        //Obtenemos datos
        $fondo    = $fon->getFondoOper();
        $fondores = $fon->getFondoRes();
        $cchica   = $fon->getCajaChica();
        $morosos  = $mor->getTotal($this->dbAdapter);        
        $egresos  = $egr->getEgresosPeriodo($this->dbAdapter);
        $egrpend  = $egr->getEgresosPendiente($this->dbAdapter);
        $ingresos = $ing->getIngresosPeriodo($this->dbAdapter);
        $mant     = $pmt->getPartidasMes(date('M'));
        //Armamos Array para la vista
        $datos = array('fondo'=>$fondo[0]['saldo'],
                       'fondores'=>$fondores[0]['saldo'],
                       'cajachica'=>$cchica[0]['saldo'],                       
                       'morosos'=>$morosos[0]['total'],                       
                       'egresos'=>$egresos,
                       'ingresos'=>$ingresos, 
                       'dias_restantes'=>$dif,
                       'pagos_pendientes'=>$egrpend[0]['pagos'], 
                       'mant_periodo'=>count($mant),                      
                        );                         
        $result = new ViewModel($datos);
        $result->setTerminal(true);            
        return $result;
        }    

    public function gastocomunAction()
    {    
        //Conectamos con BBDD  
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);
        $this->dbAdapter2=$this->getServiceLocator()->get('Zend\Db\Adapter');             
        //Abrimos Instancias
        $tra = new TrabajadorTable($this->dbAdapter);
        $tar = new TareaTable($this->dbAdapter);
        $egr = new EgresoTrabajadorTable($this->dbAdapter);
        $cic = new CicloAdminTable($this->dbAdapter);
        $tct = new TipoContratoTable($this->dbAdapter);
        $per = new PersonaTable($this->dbAdapter2);
        
        //Obtenemos ciclo administrativo
        $ciclo = $cic->getCiclo();
        
        //Tabla trabajadores
        $tabla_trabajadores = "";
        //Consultarmos trabajadores
        $trabajadores = $tra->getTrabajadores($this->dbAdapter);                
        //Consultamos estado de pago de trabajador
        $total = 0;     
        for($i=0;$i<count($trabajadores);$i++){
           
            //Verificamos si tiene pagos en el periodo
            $pagos_periodo = $egr->getPagoPeriodo($this->dbAdapter,$trabajadores[$i]['id']);  
            
            //Obtenemos nombre de contrato
            $tipo_contrato = $tct->getTipoId($trabajadores[$i]['id_tipo_contrato']);
                    
            if(count($pagos_periodo)>0){                
                //Pagado
                $estado = "Pagado";
                $span = "success"; 
                $icn = "check";    
                $total += $pagos_periodo[0]['monto'];  
                $monto = "$ ".number_format($pagos_periodo[0]['monto'],"0",".",".");  
                $pf = strtotime($pagos_periodo[0]['fecha_pago']);
                $fecha_pago = date("d-M-Y", $pf);                         
            }
            else{
                //Sin pago
                $estado = "Impago";
                $span = "danger"; 
                $icn = "close";    
                $monto = "";
                $fecha_pago = "";
            }                        
             $tabla_trabajadores=$tabla_trabajadores."<tr>
                            <td align='left'>".$trabajadores[$i]['nombre']."</td>                            
                            <td align='left'>".$trabajadores[$i]['cargo']."</td>
                            <td align='left'>".$tipo_contrato[0]['nombre']."</td>
                            <td align='left'><span class='label label-sm label-".$span."' id='spanbag'>".$estado."&nbsp; <i class='fa fa-".$icn."'></i></span></td>
                            
                            <td align='left'>$ 126.000</td>
                            <td align='left'>".$monto."</td>
                            </tr>";                                               
        }
                
        
        $result = new ViewModel(array(
                            'tabla_trabajadores'=>$tabla_trabajadores,
                            'total_trabajadores'=> number_format($total,"0",".","."),
                            ));
        $result->setTerminal(true);          
        return $result;
    }     

        

      

    public function pagofuncionarioAction()

    {

          $result = new ViewModel();

        

        $result->setTerminal(true);        

        

        return $result;

        

    }   

    public function verfuncionarioAction()

    {

          $result = new ViewModel();

        

        $result->setTerminal(true);        

        

        return $result;

        

    }    

        

    public function editarfuncionarioAction()

    {

          $result = new ViewModel();

        

        $result->setTerminal(true);        

        

        return $result;

        

    } 

    public function quitarfuncionarioAction()

    {

        $result = new ViewModel();

        

        $result->setTerminal(true);        

        

        return $result;

        

    }     

    public function ingresosAction()
    {
        $result = new ViewModel();
        $result->setTerminal(true); 
        
        return $result;    
    } 
    
    public function getingresosAction(){
        
        //Conectamos con BBDD  
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');
        $id_db = $sid->offsetGet('id_db');
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);
        
        //Obtenemos ID UNIDAD desde POST
        $data = $this->getRequest()->getPost();  
        
        //Tablas
        $gcom  = new GCunidadTable($this->dbAdapter);
        $unid  = new UnidadTable($this->dbAdapter);
        $abon  = new AbonoTable($this->dbAdapter);
                        
        //Obtenemos datos y validamos
        $unidad = $unid->getIdUnidad($data['nombre']);        
            //Validamos que exista nombre
            if(count($unidad)<1){
                    $result = new JsonModel(array(
                        'status'=>'nok','desc'=>"Vivienda no existe en el sistema"));                                
                    return $result;
            }
        $gcomun = $gcom->getHistoricoUni($this->dbAdapter,$unidad[0]['id']);
        $saldo  = $gcom->getSaldoUnidad($this->dbAdapter,$unidad[0]['id']); //Abonos restados YA !
        $gcomunsaldo = $gcom->getSaldoGC($this->dbAdapter,$unidad[0]['id']);
        $abono  = $abon->getAbonosActivos($this->dbAdapter,$unidad[0]['id']);
        
            if ($gcomunsaldo[0]['saldo_gc']=='0'){
                $displayAjuste="none";
                $displayPagar="none";                
            }else{
              $displayAjuste="none";
               $displayPagar="block";  
            }
            
            /*
              if ($abono[0]['monto']-$saldo[0]['saldo_total']>$saldo[0]['saldo_total']){
                    $displayAjuste="none";
                    $displayPagar="none";
                }elseif($saldo[0]['saldo_total']=0){
                    $displayPagar="block";
                    $displayAjuste="none";
                }
            */
        
        //Devolvemos meses a la vista
        $meses = array("NA","Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");
        
        $result = new ViewModel(array('meses'=>$meses,
                                      'gcomun'=>$gcomun,
                                      'saldo'=>$saldo[0]['saldo_total'],
                                      'abono'=>$abono,  
                                      'displayPagar'=>$displayPagar,
                                      'displayAjuste'=>$displayAjuste,      
                                      ));
        $result->setTerminal(true);
        return $result;
    }
     public function ajustargcAction()
    {
        //Conectamos con BBDD  
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');
        $id_db = $sid->offsetGet('id_db');
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);
        //Instancias
        $gcom  = new GCunidadTable($this->dbAdapter);
        $abon  = new AbonoTable($this->dbAdapter);
        //Obtenemos Datos desde POST
        $lista = $this->getRequest()->getPost(); 
        //Obtenemos mes a pagar con Abono
        $mes_ajuste = $gcom->getPrimerMesDeuda($this->dbAdapter,$lista['id_unidad']);
        //Pagamos MES con abono
        $gcom->pagoParcialGasto($mes_ajuste[0]['id']);
        //Eliminamos ABONO
        $abon->UsoAbono($lista['id_abono']);
        //Restamos monto de GC al abono, e insertamos nuevo ABONO si corresponde
        $lista['monto_abono'] = ($lista['monto_abono']-$mes_ajuste[0]['monto']);
            if($lista['monto_abono']>0){
               //Insertamos nuevo abono
               $abon->nuevoAbono($lista);
            }
        //Retornamos a la vista
        $desc="Ajuste efectuado exitosamente";
        $result = new JsonModel(array(
                                    'status'=>'ok',
                                    'desc'=>$desc,
                                                                                                                         
                    ));                                
            return $result;                                                                    
    }
    public function egresosAction()
    {
        //Conectamos con BBDD  
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');        
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);
        //Instancias
        $prov  = new ProveedorTable($this->dbAdapter);        
        $tegr  = new TipoEgresoTable($this->dbAdapter);
        $trab  = new TrabajadorTable($this->dbAdapter);                
        $form = new EgresoForm("form");
        
        //Datos
        $proveedores  = $prov->getProveedoresCombo3($this->dbAdapter);
        $tipo_egreso  = $tegr->getTipoEgreso2($this->dbAdapter);
          
        $trabajadores = $trab->getComboTrabajadores($this->dbAdapter);         
        
        $form->get('proveedores')->setAttribute('options' ,$proveedores);
        $form->get('tipo_egreso')->setAttribute('options' ,$tipo_egreso);
        $form->get('trabajadores')->setAttribute('options' ,$trabajadores);
        

        //Retornamos a la vista
        $result = new ViewModel(array('form'=>$form,'tabla'=>$tabla));
        $result->setTerminal(true);              
        return $result;
    } 
    
    public function tablaegresoAction()
    {   
        
        //Conectamos con BBDD  
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');   
        $id_db = $sid->offsetGet('id_db');     
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);
        //Tablas
        $egr   = new EgresoTable($this->dbAdapter);
        $fon   = new FondosTable($this->dbAdapter);
        
        //Cargamos ruta de files asociados
        $ruta = '/files/db/'.$id_db.'/admin/finanzas/egreso/';
        
        //Obtengo egresos del periodo
        $egresos      = $egr->getEgresosPeriodo($this->dbAdapter); 
        
        //Tabla HTML de Egresos 
        $tabla="";   
         for($i=0;$i<count($egresos);$i++){
             $fondo = $fon->getFondoId($egresos[$i]['id_fondo']);
             if($egresos[$i]['cuotas']=="si"){
                $td_cuota = "<a onclick='modalCuotas(".$egresos[$i]['id'].")' data-toggle='modal' data-target='#modalCuotas'>".ucfirst($egresos[$i]['cuotas']);
             }else{
                $td_cuota = ucfirst($egresos[$i]['cuotas']);
             }        
            $pf = strtotime($egresos[$i]['fecha_pago']);
            $mostrarF = date("d-M-Y", $pf);                  
             $tabla=$tabla."<tr>
                            <td align='left'>".$egresos[$i]['id']."</td>
                            <td align='left'>".$mostrarF."</td>
                            <td align='left'>".$fondo[0]['nombre']."</td>
                            <td align='left'>".$egresos[$i]['destino']."</td>
                            <td align='left'><strong>$ ". number_format($egresos[$i]['monto'],"0",".",".")."</strong></td>
                            <td align='left'><a target='_blank': style='cursor: pointer' href='".$ruta.$egresos[$i]['foto']."'><i class='fa fa-file'></i></a></td>
                            <td align='left'>".$td_cuota."</td>
                            <td align='left'><a onclick='borra_egreso(".$egresos[$i]['id'].")'<i class='fa fa-trash'></i> </td>
                            </tr>";
         }  
         
         //Retornamos a la vista
        $result = new ViewModel(array('tabla'=>$tabla));
        $result->setTerminal(true);              
        return $result;
        
        
    }
    
    public function borraegresoAction()
    {
       //Conectamos con BBDD  
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');        
        $this->dbAdapter=$this->getServiceLocator()->get($db_name); 
        //Obtenemos ID desde POST
        $data = $this->getRequest()->getPost(); 
        //Instancias 
        $descuota="";
        $egr = new EgresoTable($this->dbAdapter);
        $cob = new CobroTable($this->dbAdapter);
        $fon   = new FondosTable($this->dbAdapter);
        $monto = 0;
        //Consultamos si existen cuotas para el egreso
        $egreso = $egr->getDatosId($data['id']);
        
        //Validamos que ya no ha sido eliminados       
        if(count($egreso)<1){
            //Retornamos a la vista                                
            $result = new JsonModel(array('status'=>'nok','desc'=>"Egreso ya se elimin&oacute;"));                                
            return $result;  
        }
        
        
        $monto += $egreso[0]['monto'];
         if ($egreso[0]['cuotas']=='si'){
            $suma_cuotas = $cob->getSumaCuotas($this->dbAdapter,$data['id']);
            $monto += $suma_cuotas[0]['monto'];
            $cob->borraEgreso($data['id']);
            $descuota="como tambi&eacute;n sus cuotas";
         }
        //Eliminamos egreso
        $egr->borraEgreso($data['id']);
        //Sumamos monto de egreso y cuotas al fondo
        $fon->sumaFondo($this->dbAdapter,$egreso[0]['id_fondo'],$monto); 
        $fondo = $fon->getFondoId($egreso[0]['id_fondo']);
        
        //Retornamos a la vista                                
        $result = new JsonModel(array('desc'=>"Se ha eliminado egreso exitosamente, Su sumaron $".number_format($monto,"0",".",".")." pesos al ".$fondo[0]['nombre']));                                
        return $result;                   
    }
    
    public function pagoegresoAction()
    {                  
        //Conectamos con BBDD  
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');
        $id_db = $sid->offsetGet('id_db');
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);
        //Obtenemos ID desde POST
        $data = $this->getRequest()->getPost();                 
        //Instancias 
        $prv = new ProveedorTable($this->dbAdapter);
        $fop = new FondosTable($this->dbAdapter);
        $ban = new ListaBancoTable($this->dbAdapter);
        $gcu = new GCunidadTable($this->dbAdapter);
        $uni = new UnidadTable($this->dbAdapter);
        $egr = new EgresoTable($this->dbAdapter);    
        $tsr = new TipoServicioTable($this->dbAdapter);
        $cob = new CobroTable($this->dbAdapter);                  
        //Validamos POST
        if(isset($data['destino'])){
            //Identificamos usuario 
            $data['user_create'] = $sid->offsetGet('id_usuario');         
            //Quitamos puntos del monto
            $data['montototal'] = str_replace(".","",$data['montototal']);
            //Restamos monto de Fondo Origen
            $fop->restaFondo($this->dbAdapter,$data['id_fondo'],$data['montototal']);           
                                    
            //Insertamos egreso en la BBDD            
            $id_egreso = $egr->nuevoEgreso($data);
            $desc = 'Pago ingresado exitosamente';
            //Si existen cuotas,las ingresamos como cobros pendientes
             if($data['cuotas']=="si"){
                $data['id_egreso'] = $id_egreso;    
                $data['fecha_pago'] = "";       //Modificar al crear JOB    
                  for ($i=1;$i<$data['nmro_cuotas'];$i++) {                     
                     //Agregamos datos al array
                     $cuota = 'cuota'.($i+1);
                     $data['valor'] = $data[$cuota];
                     $data['cuota'] = $i+1;
                     $data['fecha_cobro'] = date("Y/m/d", strtotime($data['fecha_cobro']." +1 month"));   
                     $data['desc'] = $data['cuota']."/".$data['nmro_cuotas'];                               
	                 $cob->nuevoCobro($data);      
                     $desc = 'Pago en cuotas ingresado exitosamente';
                  }
                
             }
            //Retornamos a la vista                                
            $result = new JsonModel(array(
                                    'status'=>'ok',
                                    'desc'=>$desc,                                                                                                                         
                    ));                                
            return $result;             
        }          
        //Obtenemos datos de Proveedor
        $proveedor = $prv->getProveedoresNombre($data['nombre_prov']);        
        $servicio  = $tsr->getServicioId($proveedor[0]['id_servicio']);
                                          
        $form   = new PagoEgresoForm("form");               
        //Obtenemos Datos                
        $fondos = $fop->getCombo();
        $bancos = $ban->getDatos();                     

        //Cargamos Formulario                                       
        $form->get('id_tipo_egreso')->setAttribute('value' ,$_POST['tipo_egreso']);       
        $form->get('id_proveedor')->setAttribute('value' ,$proveedor[0]['id']);                
        $form->get('destino')->setAttribute('value',$proveedor[0]['nombre']);
        $form->get('concepto')->setAttribute('value',$servicio[0]['nombre']);
        $form->get('id_fondo')->setAttribute('options' ,$fondos);
        $form->get('id_banco')->setAttribute('options' ,$bancos);        
        //$form->get('origen')->setAttribute('value','8');
        $form->get('observacion')->setAttribute('value',$servicio[0]['categoria']." / ".$servicio[0]['nombre']);        

        $result = new ViewModel(array('form'=>$form));

        $result->setTerminal(true);

        return $result;
    }
    
    public function modalcuotasAction()    
    {
        //Variables BBDD
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);
        
        //Obtenemos ID desde POST
        $data = $this->getRequest()->getPost();
          
        //Instancias 
        $cob = new CobroTable($this->dbAdapter);    
        $egr = new EgresoTable($this->dbAdapter); 
        
        //Obtenemos datos
        $cobros = $cob->getCobroIdEgreso($data['id_egreso']);
        $egreso = $egr->getDatosId($data['id_egreso']);     
           
        //Retornamos a la vista
        $result = new ViewModel(array('cobros'=>$cobros,'egreso'=>$egreso));
        $result->setTerminal(true);
        return $result;        
    }
    
    public function egresofileAction()
    {         
       //Obtenemos id bbdd  
       $sid = new Container('base');
       $id_db = $sid->offsetGet('id_db');
       //Guardamos Registro en Servidor
       $File    = $this->params()->fromFiles('nombrefile');                 
       $adapterFile = new \Zend\File\Transfer\Adapter\Http();
       $nombre = $adapterFile->getFileName($File,false);
       $ruta = $_SERVER['DOCUMENT_ROOT'].'/files/db/'.$id_db.'/admin/finanzas/egreso';
       //Validamos si existe el archivo 
       if (file_exists($ruta."/".$nombre)){
                    $status="nok";
                    $desc="Nombre de Archivo ya existe en el servidor, use otro nombre";
       }else{
                    $status="ok";
                    $adapterFile->setDestination($ruta);
                    $adapterFile->receive($File['name']);
                    $nombre = $adapterFile->getFileName($File,false);
                    $desc="Archivo cargado Exitosamente";
       }

       
       //Retornamos a la vista
       $result = new JsonModel(array('status'=>$status,'desc'=>$desc,'name'=>$nombre));                                            
       return $result;    
        
    }
    
     public function servicioprovAction(){
        
        //Conectamos con BBDD  
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');        
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);
        //Instancias
        $tsr = new TipoServicioTable($this->dbAdapter); 
        $prv = new ProveedorTable($this->dbAdapter);
        //Obtenemos Datos Post
        $data = $this->getRequest()->getPost(); 
        //Obtenemos Datos de Proveedor     
        $proveedor = $prv->getProveedoresNombre($data['nombre_prov']); 
                //Validamos si Proveedor tiene mas servicios y cargamos datos                       
                if(count($proveedor)>1){
                    for($i=0;$i<count($proveedor);$i++){
                        $servicio[$i] = $tsr->getServicioId($proveedor[$i]['id_servicio']);
                    }                                                                                                         
                                    $result = new JsonModel(array(
                                    'flag'=>'si',
                                    'cantidad'=>count($servicio),
                                    'servicio'=>$servicio,                                                                                        
                                    ));                                
                                    return $result;    
                }
                $result = new JsonModel(array(
                                    'flag'=>'no',                                                                                        
                                    ));                                
                                    return $result;            
        
     }
             
    public function proveedoresAction()
    {              
        $result = new ViewModel();
        $result->setTerminal(true);                       
        return $result;

    }

    public function tablaproveedoresAction()
    {
        //conectamos a BBDD
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);
        //Instancias
        $pro = new VProveedorTable($this->dbAdapter);
        //Datos
        $proveedores = $pro->getProveedores(); 
        
                //Damos formato a la fecha
                for($i=0;$i<count($proveedores);$i++){
                    $proveedores[$i]['ultimopago']=date("d-m-Y",strtotime($proveedores[$i]['ultimopago']));
                }
                
        //Tabla HTML de Egresos 
        $tabla="";   
         for($i=0;$i<count($proveedores);$i++){              
            $pf = strtotime($proveedores[$i]['ultimopago']);
            $mostrarF = date("d-M-Y", $pf);                  
             $tabla=$tabla."<tr>
                            <td align='left'>".$proveedores[$i]['nombre']."</td>
                            <td align='left'>".$proveedores[$i]['servicio']."</td>
                            <td align='left'>".$proveedores[$i]['telefono']."</td>
                            <td align='left'>".$mostrarF."</td>
                            <td align='left'><strong><div class=''><a onclick='detalleProv('".$proveedores[$i]['id']."')' style='cursor:pointer;'><i class='fa fa-eye '></i></a> | <i class='fa fa-edit'></i>  | <a onclick='eliminarProv('".$proveedores[$i]['id']."','0')' style='cursor:pointer;'><i class='fa fa-trash'></i></a></div></td>                            
                            </tr>";
         }                                        
        
        $result = new ViewModel(array(
                                    'status'=>'ok',
                                    'tabla'=>$tabla,                                                                                        
                    ));  
        $result->setTerminal(true);                               
        return $result;

    }

    public function nuevoproveedorAction()
    {
        //Conectamos con BBDD
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);
                
            if($this->getRequest()->isPost()){
                //Obtenemos Datos POST
                $data = $this->getRequest()->getPost();
                //Validamos combos
                if ($data['categoria']=='0'){
                        $descripcion = "Debes seleccionar una categoria!";
                        $result = new JsonModel(array('status'=>'nok','descripcion'=>$descripcion,));
                        return $result;
                }
                if ($data['servicio']=='0'){
                        $descripcion = "Debes seleccionar un servicio!";
                        $result = new JsonModel(array('status'=>'nok','descripcion'=>$descripcion,));
                        return $result;
                }
                //Quitamos formato RUT
                $data['rut'] = explode("-",$data['rut']);  
                $data['dv']  = $data['rut'][1];         
                $data['rut'] = str_replace(".","",$data['rut'][0]);
                //Validamos RUT
                $pro = new ProveedorTable($this->dbAdapter); 
                $proveedores = $pro->getProveedoresRut($data['rut']);                                                
                if (count($proveedores)>0){
                        $descripcion = "El proveedor ya existe!";
                        $result = new JsonModel(array('status'=>'nok','descripcion'=>$descripcion,));                                
                        return $result;
                }                                                                                                                   
                //Insertamos Nuevo Proveedor                              
                $id    = $pro->nuevoProveedor($data);
                $nuevo = $pro->getProveedoresId($id);
                //Retornamos a la vista                
                $descripcion = "Proveedor ingresado exitosamente";
                $result = new JsonModel(array(
                                    'status'=>'ok',
                                    'descripcion'=>$descripcion,
                                    'prov'=>$nuevo,                                                                                        
                    ));                                
                return $result;                                    
            }
            else{
                //Instancias
                $form = new ProveedorForm("form");
                $ban  = new ListaBancoTable($this->dbAdapter);
                $srv  = new TipoServicioTable($this->dbAdapter);
                //Obtenemos Datos y cargamos Formulario
                $tipos = $srv->getComboTipo($this->dbAdapter);        
                $bancos = $ban->getDatos();
                //Cargamos combos
                $form->get('categoria')->setAttribute('options' ,$tipos);
                $form->get('id_banco')->setAttribute('options' ,$bancos);
                //Retornamos a la vista
                $result = new ViewModel(array('form'=>$form));
                $result->setTerminal(true);        

                return $result; 
            }
        }  
        
    public function comboservicioAction()
    {
        //Conectamos con BBDD
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);
        //Obtenemos Datos
        $lista = $this->request->getPost();
        $srv = new TipoServicioTable($this->dbAdapter);
        $servicios = $srv->getComboServicio($lista['categoria']);

        $result = new JsonModel($servicios);                                
        return $result;
        
    }
    
    public function nuevoservicioAction()
    {
        //Conectamos con BBDD
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);
        //Instancias
        $form = new ProveedorForm("form");                
        $pro  = new ProveedorTable($this->dbAdapter); 
        $srv  = new TipoServicioTable($this->dbAdapter);
        //Validamos si es POST                
        if($this->getRequest()->isPost()){
            //Obtenemos datos Post
            $lista = $this->request->getPost();        
                //Cargamos combo servicio
                if (isset($lista['combo']))
                {                
                    $servicios = $srv->getComboServicio($lista['servicio']);
                    $result = new JsonModel($servicios);                                
                    return $result;    
                }
                //Validamos combos
                if ($lista['proveedores']=='0'){
                        $descripcion = "Debes seleccionar un Proveedor!";
                        $result = new JsonModel(array('status'=>'nok','descripcion'=>$descripcion,));
                        return $result;
                }
                if ($lista['categoria']=='0'){
                        $descripcion = "Debes seleccionar una categoria!";
                        $result = new JsonModel(array('status'=>'nok','descripcion'=>$descripcion,));
                        return $result;
                }
                if ($lista['servicio']=='0'){
                        $descripcion = "Debes seleccionar un servicio!";
                        $result = new JsonModel(array('status'=>'nok','descripcion'=>$descripcion,));
                        return $result;
                }        
                $proveedor = $pro->getProveedoresId($lista['proveedores']);
                $proveedor[0]['categoria']   = $lista['categoria'];
                $proveedor[0]['servicio']    = $lista['servicio'];
                $proveedor[0]['observacion'] = $lista['observacion'];
                $proveedor[0]['fijo']        = $lista['fijo'];
                //Insertamos el nuevo Servicio al Proveedor                
                $id    = $pro->nuevoProveedor($proveedor[0]);
                $nuevo = $pro->getProveedoresId($id);
                //Retornamos a la vista                
                $descripcion = "Nuevo Servicio ingresado exitosamente";
                $result = new JsonModel(array(
                                    'status'=>'ok',
                                    'descripcion'=>$descripcion,
                                    'prov'=>$nuevo,                                                                                        
                    ));                                
                return $result;                                             
        }                                                                                        
        //Obtenemos datos de BBDD        
        $proveedores = $pro->getProveedoresCombo($this->dbAdapter);     
        $tipos       = $srv->getComboTipo($this->dbAdapter);
        //Cargamos Formulario 
        $form->get('proveedores')->setAttribute('options' ,$proveedores);
        $form->get('categoria')->setAttribute('options' ,$tipos);
        //Retornamos a la vista
        $result = new ViewModel(array('form'=>$form));
        $result->setTerminal(true);        

        return $result;
        
    }
    
    public function eliminarproveedorAction()
    {    
        //Conectamos con BBDD  
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);
        //Instancias
        $prv = new ProveedorTable($this->dbAdapter);
        //Obtenemos id de Proveedor
        $data = $this->getRequest()->getPost();
        //Actualizamos Proveedor en BBDD
        $prv->eliminarProveedor($data['id']);
        //Preparamos respuesta a la vista
        $descripcion = "Se ha eliminado correctamente el Proveedor";
        
         
        $result = new JsonModel(array(
                                    'status'=>'ok',
                                    'descripcion'=>$descripcion,                                                                                         
                    ));                                

        return $result;                                         
    }
    
    public function detalleproveedorAction()
    {    
        //Conectamos con BBDD  
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);
        //Instancias
        $prv = new ProveedorTable($this->dbAdapter);        
        $tps = new TipoServicioTable($this->dbAdapter);
        $vpr = new VProveedorTable($this->dbAdapter);        
        //Obtenemos id de Proveedor
        $data = $this->getRequest()->getPost();
        //Consultamos Proveedor y sus servicios en BBDD
        $prov = $prv->getProveedoresId($data['id']); 
        $serv = $tps->getServicioId($prov[0]['id_servicio']);
        $vpro = $vpr->getProveedoresId($prov[0]['id']);
        
        $prov[0]['servicio'] = $serv[0]['nombre'];
        $prov[0]['categoria'] = $serv[0]['categoria'];
        $prov['0']['rut'] = number_format($prov['0']['rut'],-3,"",".")."-".ucfirst($prov['0']['dv']);
        $prov['0']['monto'] = $vpro[0]['monto'];
        $prov['0']['fecha'] = date("d-m-Y",strtotime($vpro[0]['ultimopago']));
                          
        $result = new JsonModel($prov);                                

        return $result;                                         
    }
       
    public function pagogcAction()
    {        
        //Conectamos con BBDD  
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);
        //Instancias 
        $gc = new VPagogcTable($this->dbAdapter);        
        //Traemos datos
        $lista = $gc->getDatos(); 
        //Matriculamos estado       
        for ($i=0;$i<count($lista);$i++){            
            if ($lista[$i]['saldo']>0){
                $lista[$i]['estado'] = "Moroso";                
            }else{
                $lista[$i]['estado'] = "Al dia";
            }
            $lista[$i]['saldo'] = number_format($lista[$i]['saldo'],"0",".",".");
            $lista[$i]['multa'] = number_format($lista[$i]['multa'],"0",".",".");
        }
               
        $result = new JsonModel(array(
                                    'status'=>'ok',
                                    'pagogc'=>$lista,                                                     
                    ));                                

        return $result;
    }

    public function modalpagoAction()
    {
        //Variables BBDD
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);

        //Instancias
        $fop = new FondosTable($this->dbAdapter);
        $ban = new ListaBancoTable($this->dbAdapter);
        $gcu = new GCunidadTable($this->dbAdapter);
        $uni = new UnidadTable($this->dbAdapter);
        $ing = new IngresoTable($this->dbAdapter);
        $mul = new MultaTable($this->dbAdapter);
        $abo = new AbonoTable($this->dbAdapter);            

        $form   = new IngresoPagoForm("form");               

        //Obtenemos Datos        

        $data   = $this->getRequest()->getPost(); 
        $fondos = $fop->getCombo();
        $unidad = $uni->getDatosId($data["id_unidad"]);            
        $deuda  = $gcu->getNoPagado($unidad[0]['id']);
        $multa  = $mul->getMultaUnidad($unidad[0]['id']);    
        $abono  = $abo->getAbonosUnidad($unidad[0]['id']);    
        $listab  = $ban->getDatos();
        $total = 0;      
        
        if($data['forma']=="t"){
           // $displayparcial= "none";
            $displayparcial= "block"; 
        }else{
          //  $displaytotal= "none";
          $displaytotal= "block"; 
        }
             
        if (count($deuda)>0){
                for($i=0;$i<count($deuda);$i++){
                    $total = $total + $deuda[$i]["monto"];
                    }
        }
        if (count($multa)>0){
                for($i=0;$i<count($multa);$i++){
                    $total = $total + $multa[$i]["monto"];
                }
        }
        if (count($abono)>0){
                for($i=0;$i<count($abono);$i++){
                    $total = $total - $abono[$i]["monto"];
                }
        }
        $total = number_format($total,"0",".",".");

      //   $multa = number_format($data["multa"],"0",".",".");         

        //Cargamos Formulario                                       

        $form->get('banco')->setAttribute('options' ,$listab);  
        //$form->get('origen')->setAttribute('value' ,$unidad[0]['nombre']);
        $form->get('id_fondo')->setAttribute('options' ,$fondos);
        $form->get('id_fondo')->setAttribute('value','2');

        //Retornamos a la Vista                                                        
        $meses = array("NA","Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");
        $result = new ViewModel(array(
                        'form'=>$form,
                        'deuda'=>$deuda,
                        'total'=>$total,
                        'multa'=>$multa,
                        'abono'=>$abono,
                        'displaytotal'=>$displaytotal,
                        'displayparcial'=>$displayparcial,
                        'meses'=>$meses
                        ));
        $result->setTerminal(true);
        return $result;
    }
    
    public function pagargcAction()
    {
        //Variables BBDD
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);

        //Cargamos Clases 
        $ing = new IngresoTable($this->dbAdapter);
        $gcu = new GCunidadTable($this->dbAdapter);
        $mul = new MultaTable($this->dbAdapter); 
        $uni = new UnidadTable($this->dbAdapter);
        $fon = new FondosTable($this->dbAdapter);
        $abn = new AbonoTable($this->dbAdapter);               

        //Obtenemos Datos          
        $lista = $this->request->getPost();        
        $lista['user_create'] = $sid->offsetGet('id_usuario');
        $id_uni = $uni->getIdUnidad($lista["origen"]); 
        $lista['monto'] = str_replace(".","",$lista['monto']) ;

        //Insertamos Datos           
            if ($lista['tipo_pago']=='total'){
                if(count($lista['abono'])>0){
                    $pagarabono = array_keys($lista['abono']);
                        for ($i=0;$i<count($pagarabono);$i++){
                            $abn->UsoAbono($pagarabono[$i]);                                                                          
                        }                  
                    } 
                $mul->pagoMultaTotal($this->dbAdapter,$id_uni[0]['id']);
                $gcu->pagoTotalGasto($this->dbAdapter,$id_uni[0]['id']);                                  
            }else{        
                if(count($lista['mes'])>0){
                    $pagar = array_keys($lista['mes']);
                        for ($i=0;$i<count($pagar);$i++){
                            $gcu->pagoParcialGasto($pagar[$i]);                                                                                                      
                        } 
                    }
                if(count($lista['multa'])>0){
                    $pagarm = array_keys($lista['multa']);
                        for ($i=0;$i<count($pagarm);$i++){
                            $mul->pagoMulta($pagarm[$i]);                                                                          
                        }                  
                    }
                if(count($lista['abono'])>0){
                    $pagarabono = array_keys($lista['abono']);
                        for ($i=0;$i<count($pagarabono);$i++){
                            $abn->UsoAbono($pagarabono[$i]);                                                                          
                        }                  
                    }    
                }
          //Registramos ingreso a la BBDD                
          $ing->nuevoIngreso($lista);             
          //Actualizamos fondo correspondiente
          $fon->sumaFondo($this->dbAdapter,$lista["id_fondo"],$lista["monto"]);  
                                                                            
         //Enviamos a la Vista                                                      
         $descripcion = "Se ha registrado correctamente el ingreso";              
         $result = new JsonModel(array(
                                 'desc'=>$descripcion,
                    ));                                                    
         return $result;  
    }
    
     public function modalpagotrabajadorAction()
    {
        //Variables BBDD
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);

        //Instancias
        $fop = new FondosTable($this->dbAdapter);
        $ban = new ListaBancoTable($this->dbAdapter);               

        $form   = new PagoTrabajadorForm("form");               

        //Obtenemos Datos        
        $data   = $this->getRequest()->getPost(); 
        $fondos = $fop->getCombo();               
        $listab  = $ban->getDatos();        

        //Cargamos Formulario
        $form->get('id_trabajador')->setAttribute('value' ,$data["id_trabajador"]);        
        $form->get('id_tipo_egreso')->setAttribute('value' ,$data["id_tipo_egreso"]);
        $form->get('montototal')->setAttribute('disabled' ,'true');                                        
        $form->get('id_banco')->setAttribute('options' ,$listab);  
        $form->get('trabajador')->setAttribute('value' ,$data["nombre_trabajador"]);
        $form->get('id_fondo')->setAttribute('options' ,$fondos);
        $form->get('id_fondo')->setAttribute('value','2');
        $form->get('id_fondo')->setAttribute('disabled','true');
        $form->get('observacion')->setAttribute('value' ,utf8_encode("Remuneración Personal"));
        $form->get('concepto')->setAttribute('value' ,"Remuneraciones");
        $form->get('cuotas')->setAttribute('value' ,"no");

        //Retornamos a la Vista                                                                
        $result = new ViewModel(array(
                        'form'=>$form,                        
                        ));
        $result->setTerminal(true);
        return $result;
    }
    
    public function egresotrabajadorfileAction()
    {         
       //Obtenemos id bbdd  
       $sid = new Container('base');
       $id_db = $sid->offsetGet('id_db');
       //Guardamos Registro en Servidor
       $File    = $this->params()->fromFiles('nombrefile');                 
       $adapterFile = new \Zend\File\Transfer\Adapter\Http();
       $nombre = $adapterFile->getFileName($File,false);
       $ruta = $_SERVER['DOCUMENT_ROOT'].'/files/db/'.$id_db.'/admin/finanzas/egreso';
       //Validamos si existe el archivo 
       if (file_exists($ruta."/".$nombre)){
                    $status="nok";
                    $desc="Nombre de Archivo ya existe en el servidor, use otro nombre";
       }else{
                    $status="ok";
                    $adapterFile->setDestination($ruta);
                    $adapterFile->receive($File['name']);
                    $nombre = $adapterFile->getFileName($File,false);
                    $desc="Archivo cargado Exitosamente";
       }
       
       //Retornamos a la vista
       $result = new JsonModel(array('status'=>$status,'desc'=>$desc,'name'=>$nombre));                                            
       return $result;    
        
    }

     public function pagartrabajadorAction()
    {
        //Variables BBDD
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);

        //Cargamos Clases 
        $egt = new EgresoTrabajadorTable($this->dbAdapter);
        $egr = new EgresoTable($this->dbAdapter); 
        $fop = new FondosTable($this->dbAdapter);                  

        //Obtenemos Datos          
        $lista = $this->request->getPost();        
        $lista['user_create'] = $sid->offsetGet('id_usuario');        
        $lista['destino'] = $lista['trabajador'];   
        //Quitamos formato a Montos
        $lista['montototal'] = str_replace(".","",$lista['montototal']) ;
        $lista['leysocial'] = str_replace(".","",$lista['leysocial']) ;
        $lista['sueldo'] = str_replace(".","",$lista['sueldo']) ;              
        //Registramos Egreso 
        $lista['id_egreso'] = $egr->nuevoEgreso($lista);
        //Restamos monto de Fondo Origen
        $fop->restaFondo($this->dbAdapter,$lista['id_fondo'],$lista['montototal']); 
        //Insertamos en tabla Egreso Trabajador
        $egt->nuevoEgresoTrabajador($lista);
                                          
         //Enviamos a la Vista                                                      
         $descripcion = "Se ha registrado correctamente el egreso";              
         $result = new JsonModel(array(
                                 'desc'=>$descripcion,
                    ));                                                    
         return $result;  
    }

    public function modalabonoAction()
    {        
        //Obtenemos Datos POST          
        $data = $this->request->getPost();
        //Variables BBDD
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);                        
        //Instancias        
        $uni = new UnidadTable($this->dbAdapter);
        $ban = new ListaBancoTable($this->dbAdapter);
        $form   = new AbonoForm("form"); 
        
        //Consultamos info de unidad
        $unidad = $uni->getDatosId($data['id_unidad']);
        $bancos = $ban->getDatos(); 
        
        //Cargamos Formulario
        $form->get('nombre_unidad')->setAttribute('value' ,$unidad[0]['nombre']);
        $form->get('id_unidad')->setAttribute('value' ,$unidad[0]['id']);
        $form->get('banco_abono')->setAttribute('options' ,$bancos);  
        
        //Retornamos a la vista
        $result = new ViewModel(array("form"=>$form));
        $result->setTerminal(true);
        return $result;
    }
    
    public function pagarabonoAction()
    {
        //Variables BBDD
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);

        //Cargamos Clases         
        $abn = new AbonoTable($this->dbAdapter);
        $fon = new FondosTable($this->dbAdapter);                                            
        
        //Obtenemos Datos          
        $lista = $this->request->getPost();
        
        // Identificamos usuario
        $lista['user_create'] = $sid->offsetGet('id_usuario');  
        
        //Quitamos puntos del monto
        $lista['monto_abono'] = str_replace(".","",$lista['monto_abono']);
                               
        //Ingresamos Abono
        $abn->nuevoAbono($lista); 
                     
        //Retornamos a la vista
        $desc = "Abono ingresado correctamente para vivienda ".$lista['nombre_unidad'];
        $result = new JsonModel(array('status'=>'ok','desc'=>$desc));                                
        return $result;           
    }
    
  //////////////////////////////////////////////////////////////Financiera FONDO OPERACIONAL    
    public function guardarcicloAction()
    {  
        //Conectamos a la BBDD del condominio                                         
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);
        //Obtenemos datos post                                              
        $lista = $this->request->getPost();
        //Instancia
        $cicl = new CicloAdminTable($this->dbAdapter);
        //Guardamos Dia y Hora de cierre administrativo
        $cicl->updateCierreMes($lista['dia'],$lista['hora']);                
        //Devolvemos a la vista
        $descripcion = "Ciclo Administrativo guardado exitosamente";
        $result = new JsonModel(array('status'=>'ok','descripcion'=>$descripcion));                                
        return $result;   

            
        
    }                                                    
    public function parametrosfinAction()
    {                                                                     
        //Conectamos a la BBDD del condominio                                          
        $sid = new Container('base');
        $db_name = $sid->offsetGet('dbNombre');
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);

        //Instancias
        $fon   = new FondosTable($this->dbAdapter);
        $ban   = new ListaBancoTable($this->dbAdapter);
        $cic   = new CicloAdminTable($this->dbAdapter);
        $form  = new FondoOperForm("form");
        $form2 = new FondoResForm("form");
        $form3 = new CajachicaForm("form");
        $form4 = new MultasForm("form");
        $form5 = new CicloForm("form");                
                        
        //Obtenemos Datos
        $lista  = $fon->getDatos();
        $oper   = $fon->getFondoOper();
        $rese   = $fon->getFondoRes();
        $listab = $ban->getDatos();        
        $cierre = $cic->getCiclo();     
        
        //Calculamos dia de cierre                        
            if(date('j')>$cierre[0]['dia']){
                $dias_mes = cal_days_in_month(CAL_GREGORIAN, date('n'), date('Y'));
                $dif = ($dias_mes-date('j'))+$cierre[0]['dia']; 
                $mes_cierre =  date('F', strtotime('+1 month')) ;               
            }else{
                 $dif = $cierre[0]['dia']-date('j');    
                 $mes_cierre = date('F');        
            }
        //Fecha mes administrativo
        $descr_cierre = $cierre[0]['dia']." de ".$mes_cierre." a las ".substr($cierre[0]['hora'],0,-3)." Hrs";   

        //cargamos id banco        
        $form->get('idbanco')->setValue($oper[0]['id_banco']);
            // Validamos si existen fondos y desabilitamos inputs
            if  (count($oper)>0)
            {
                $form->get('saldo')->setAttribute('disabled','disabled');
                $form->get('cheque')->setAttribute('disabled','disabled');                 
            }  
            if  (count($rese)>0)
            {
                $form2->get('saldo')->setAttribute('disabled','disabled'); 
            }  

              $form->get('id_pk')->setAttribute('value' ,$oper[0]['id']);
              $form->get('numero')->setAttribute('value' ,$oper[0]['numero']);
              $form->get('rut')->setAttribute('value' ,$oper[0]['rut']."-".$oper[0]['dv']);              
              $form->get('titular')->setAttribute('value' ,$oper[0]['titular']);
              $form->get('banco')->setAttribute('options' ,$listab);                        
              $form->get('correo')->setAttribute('value' ,$oper[0]['correo']);
              $form->get('detalle')->setAttribute('value' ,$oper[0]['detalle']);
              $form->get('saldo')->setAttribute('value' ,number_format($oper[0]['saldo'],"0",".","."));
              $form->get('cheque')->setAttribute('value' ,$oper[0]['ultimo_cheque']);                                                            
              
              $form2->get('banco')->setAttribute('options' ,$listab);
              //Cargamos datos de Ciclo Administrativo
              $form5->get('dia')->setAttribute('value' ,$cierre[0]['dia']);
              $form5->get('hora')->setAttribute('value' ,$cierre[0]['hora']);   
              
              
              
        //Return a la vista            

        $result = new ViewModel(array(

                            'form'=>$form,
                            'form2'=>$form2,
                            'form3'=>$form3,
                            'form4'=>$form4,
                            'form5'=>$form5,
                            'descr_cierre' =>$descr_cierre,
                            'url'=>$this->getRequest()->getBaseurl()));

        $result->setTerminal(true);        

        return $result;       
    }
    
    public function operacionalfondoAction()

    {                                                          
            //Obtenemos datos post                                              
            $lista = $this->request->getPost();
            //Variables de BBDD   
            $sid = new Container('base');             
            $db_name = $sid->offsetGet('dbNombre');
            $this->dbAdapter=$this->getServiceLocator()->get($db_name);
            //Instancias                 
            $cuenta = new FondosTable($this->dbAdapter);  
            //Cargamos datos    
            $lista['saldo'] = str_replace('.','',$lista['saldo']);
            //Quitamos formato RUT
            $lista['rut'] = explode("-",$lista['rut']);  
            $lista['dv']  = $lista['rut'][1];         
            $lista['rut'] = str_replace(".","",$lista['rut'][0]); 
            // Validamos si existe                      
                if($lista['id_pk']>0){                       
                    $cuenta->guardarFondo($lista['id_pk'], $lista);   
                    $descripcion ="Edici&oacute;n de Fondo Operacional exitosa";                           
                    }
                    else{                                     
                        $cuenta->nuevoFondo($lista);  
                        $descripcion ="Fondo Operacional ingresado exitosamente al sistema"; 
                } 

            $oper = $cuenta->getFondoOper($this->dbAdapter);
            $idbanco = $oper[0]['banco'];  
            $saldo   = $oper[0]['saldo'];                     
            $result = new JsonModel(array(

                                    'status'=>'ok',
                                    'descripcion'=>$descripcion,
                                    'idbanco'=>$idbanco,
                                    'saldo'=>$saldo,

                    ));                                



             return $result;                                   

    } 

//////////////////////////////////////////////////////////////Financiera FONDO RESERVA                                   

    public function fondoreservaAction()

    {                                                                     
        //Conectamos a la BBDD        
        $sid = new Container('base');                                      
        $db_name = $sid->offsetGet('dbNombre');
        $this->dbAdapter=$this->getServiceLocator()->get($db_name);

        //Instancias
        $fon   = new FondosTable($this->dbAdapter);
        $ban   = new ListaBancoTable($this->dbAdapter);
        $form2 = new FondoResForm("form");

        //Obtenemos Datos
        $fondo  = $fon->getFondoRes();
        $listab  = $ban->getDatos();    
                                            
        //Cargamos Datos
                $id_pk   =  $fondo[0]['id'];
                $numero  =  $fondo[0]['numero'];
                $rut     =  $fondo[0]['rut']."-".$fondo[0]['dv'];
                $titular =  $fondo[0]['titular'];                        
                $email   =  $fondo[0]['correo'];
                $cuota   =  $fondo[0]['cuota_reserva'];
                $id_banco=  $fondo[0]['id_banco'];
                $detalle =  $fondo[0]['detalle'];
                $saldo   =  number_format($fondo[0]['saldo'],"0",".",".");                   
           

       $result = new JsonModel(array(

                                    'status'=>'ok',
                                    'id_pk'=>$id_pk,
                                    'titular'=>$titular,
                                    'rut'=>$rut,
                                    'numero'=>$numero,
                                    'id_banco'=>$id_banco,
                                    'email'=>$email,
                                    'cuota'=>$cuota,
                                    'detalle'=>$detalle,
                                    'saldo'=>$saldo,                                                  

                    ));                                



        return $result;          

    }

        public function guardarfondoresAction()

    {                                               

            //Obtenemos datos post                                              
            $lista = $this->request->getPost();
            // Actualizamos datos en la tabla    
            $sid = new Container('base');             
            $db_name = $sid->offsetGet('dbNombre');
            $this->dbAdapter=$this->getServiceLocator()->get($db_name);           
            $cuenta = new FondosTable($this->dbAdapter); 
            //Quitamos formato RUT
            $lista['rut'] = explode("-",$lista['rut']);  
            $lista['dv']  = $lista['rut'][1];         
            $lista['rut'] = str_replace(".","",$lista['rut'][0]);
            //Quitamos . de saldo
            $lista['saldo'] = str_replace('.','',$lista['saldo']);              
            // Validamos si es Insert o Update                     
                if($lista['id_pk']>0){        
                    $cuenta->guardarFondo($lista['id_pk'], $lista);   
                    $descripcion ="Edici&oacute;n de Fondo de Reserva exitosa";                           
                }else{                                     
                    $cuenta->nuevoFondo($lista);  
                    $descripcion ="Fondo de Reserva creado exitosamente en el sistema"; 
                }                            
                
                $result = new JsonModel(array(
                                    'status'=>'ok',
                                    'descripcion'=>$descripcion,                                                      
                    ));                                

             return $result;                                     
        }

//////////////////////////////////////////////////////////////Financiera CAJA CHICA       

           public function  cajachicaAction()
    {       
           $sid = new Container('base');
           $db_name = $sid->offsetGet('dbNombre');
           $this->dbAdapter=$this->getServiceLocator()->get($db_name);
           $caja = new CajachicaTable($this->dbAdapter);
           $lista = $caja->getDatos();                                                                                              
           $result = new JsonModel(array(
                                    'status'=>'ok',
                                    'caja'=>$lista,
                ));
           return $result;                    
    }   

    public function guardarcajaAction()

    {                                               
            //Obtenemos datos post                                              
            $lista = $this->request->getPost();
            // Actualizamos datos en la tabla    
            $sid = new Container('base');             
            $db_name = $sid->offsetGet('dbNombre');
            $this->dbAdapter=$this->getServiceLocator()->get($db_name);           
            $caja = new CajachicaTable($this->dbAdapter);   
            
             // Validamos si es Insert o Update                      
            $id_pk = $lista['id_pk'];
            if($id_pk > 0){        
            $caja->guardarCaja($id_pk, $lista);   
            $descripcion ="Edici&oacute;n de Caja Chica exitosa";                           
            }else{                                     
            $caja->nuevaCaja($lista);  
            $descripcion ="Caja Chica creada exitosamente en el sistema"; 
            }                            
            $result = new JsonModel(array(
                                    'status'=>'ok',
                                    'descripcion'=>$descripcion,                                                      
                    ));                                

             return $result;                                     
        }             
        
//////////////////////////////////////////////////////////////Financiera MULTAS

 public function  multaAction()
    {       
           $sid = new Container('base');
           $db_name = $sid->offsetGet('dbNombre');
           $this->dbAdapter=$this->getServiceLocator()->get($db_name);
           $multa = new TipoMultaTable($this->dbAdapter);
           $lista = $multa->getDatos();                                                                                              
           $result = new JsonModel(array(
                                    'status'=>'ok',
                                    'multa'=>$lista,
                ));
           return $result;                    
    }   
    public function guardarmultaAction()
    {                                               
            //Obtenemos datos post                                              
            $lista = $this->request->getPost();
            // Actualizamos datos en la tabla    
            $sid = new Container('base');             
            $db_name = $sid->offsetGet('dbNombre');
            $this->dbAdapter=$this->getServiceLocator()->get($db_name);           
            $multa = new TipoMultaTable($this->dbAdapter);   
            
             // Validamos si es Insert o Update                      
            $id_pk = $lista['id_pk'];
            if($id_pk > 0){        
            $multa->guardarMulta($id_pk, $lista);   
            $descripcion ="Edici&oacute;n de Multas e Intereses exitosa";                           
            }else{                                     
            $multa->nuevaMulta($lista);  
            $descripcion ="Multas e Intereses ingresados OK al sistema"; 
            }                            
            $result = new JsonModel(array(
                                    'status'=>'ok',
                                    'descripcion'=>$descripcion,                                                      

                    ));                                

             return $result;                                     
        }                                   
}