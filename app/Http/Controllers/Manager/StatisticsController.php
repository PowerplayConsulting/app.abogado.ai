<?php

namespace App\Http\Controllers\Manager;

use App\Helpers\Doctrine;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Doctrine_Query;
use Doctrine_Core;

class StatisticsController extends Controller
{
    public function index()
    {
        return redirect()->route('manager.statistics.accounts');
    }

    public function accounts($cuenta_id = null, $proceso_id = null)
    {
        if (!$cuenta_id) {

            //Seleccionamos los tramites que se han avanzado o tienen datos
            $tramites_arr = Doctrine_Query::create()
                ->from('Tramite t, t.Etapas e, e.DatosSeguimiento d')
                ->select('t.id')
                ->where('t.updated_at > DATE_SUB(NOW(),INTERVAL 30 DAY)')
                ->having('COUNT(e.id) > 0')//Mostramos solo los que se han avanzado o tienen datos
                ->groupBy('t.id')
                ->execute(array(), Doctrine_Core::HYDRATE_SINGLE_SCALAR);

            $data['ntramites'] = count($tramites_arr);

            $cuentas = Doctrine_Query::create()
                ->from('Cuenta c, c.Procesos.Tramites t')
                ->select('c.*, COUNT(t.id) as ntramites')
                ->whereIn('t.id', empty($tramites_arr) ? array(-1) : $tramites_arr)
                ->groupBy('c.id')
                ->execute();

            $procesos_cuentas = Doctrine_Query::create()
                ->from('Proceso p, p.Cuenta c')
                ->select('COUNT(p.id) as num_procesos')
                ->where('p.id = 1 AND c.id', $cuenta_id)
                ->groupBy('c.id')
                ->execute();   

            $procesos_activos = Doctrine_Query::create()
                ->from('Proceso p, p.Cuenta c')
                ->select('p.id')
                ->where('p.activo=1')
                ->groupBy('p.id')
                ->execute();    

            $data['procesos_activos'] = count($procesos_activos);    

            $data['procesos_cuentas'] = count($procesos_cuentas);    

            $data['cuentas'] = $cuentas;

            $data['title'] = 'Cuentas';

            $data['content'] = view('manager.statistics.accounts', $data);
        } else if (!$proceso_id) {
            $cuenta = Doctrine::getTable('Cuenta')->find($cuenta_id);

            //Seleccionamos los tramites que se han avanzado o tienen datos
            $tramites_arr = Doctrine_Query::create()
                ->from('Tramite t, t.Etapas e, e.DatosSeguimiento d, t.Proceso.Cuenta c')
                ->where('c.id = ?', $cuenta_id)
                ->andWhere('t.updated_at > DATE_SUB(NOW(),INTERVAL 30 DAY)')
                ->select('t.id')
                ->having('COUNT(e.id) > 0')//Mostramos solo los que se han avanzado o tienen datos
                ->groupBy('t.id')
                ->execute(array(), Doctrine_Core::HYDRATE_SINGLE_SCALAR);


            $data['ntramites'] = count($tramites_arr);


            $procesos = Doctrine_Query::create()
                ->from('Proceso p, p.Tramites t')
                ->select('p.*, COUNT(t.id) as ntramites')
                ->whereIn('t.id', $tramites_arr)
                ->groupBy('p.id')
                ->execute();

                

            $data['procesos'] = $procesos;

            $data['title'] = $cuenta->nombre;

            $data['content'] = view('manager.statistics.process', $data);
        } else {
            $data['proceso'] = Doctrine::getTable('Proceso')->find($proceso_id);

            $tramites = Doctrine_Query::create()
                ->from('Tramite t, t.Proceso p, t.Etapas e, e.DatosSeguimiento d')
                ->where('p.activo=1 AND p.id = ?', $proceso_id)
                ->andWhere('t.updated_at > DATE_SUB(NOW(),INTERVAL 30 DAY)')
                ->orderBy('t.updated_at DESC')
                ->having('COUNT(e.id) > 0')//Mostramos solo los que se han avanzado o tienen datos
                ->groupBy('t.id')
                ->execute();

            $data['tramites'] = $tramites;

            $data['title'] = $data['proceso']->nombre;

            $data['content'] = view('manager.statistics.procedures', $data);
        }

        return view('layouts.manager.app', $data);
    }

}