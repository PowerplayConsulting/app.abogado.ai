<?php

use App\Models\Cuenta;
use App\Models\Etapa;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

function getPrevisualization($e)
{
   $previsualizacion = '';
    if(!empty($e->previsualizacion))
    {
        $r = new Regla($e->previsualizacion);
        $previsualizacion = $r->getExpresionParaOutput($e->etapa_id);
    }
    return $previsualizacion;
}
function getValorDatoSeguimiento($e, $tipo)
{
    $etapas = $e->tramite->etapas;
    $tramite_nro = '';
    foreach ($etapas as $etapa )
    {
        foreach($etapa->datoSeguimientos as $dato) 
        {
            if ($dato->nombre == $tipo) {
                $tramite_nro = $dato->valor == 'null' ? '' : json_decode('"'.str_replace('"','',$dato->valor).'"');
            }
        }
    }
    return $tramite_nro != '' ? $tramite_nro : $e->tramite->proceso->nombre;
}
function getCuenta()
{
    return \Cuenta::cuentaSegunDominio()->toArray();
}

function getTotalUnnasigned()
{
    $c=0;
    if (!Auth::user()->open_id) 
    {
        $grupos = Auth::user()->grupo_usuarios()->pluck('grupo_usuarios_id');
        $cuenta=\Cuenta::cuentaSegunDominio();
        $etapas =  Etapa::select('etapa.*')
        ->whereNull('etapa.usuario_id')
        ->join('tarea', function($q){
            $q->on('etapa.tarea_id','=', 'tarea.id');
        })
        ->join('proceso', function($q){
            $q->on('tarea.proceso_id', '=', 'proceso.id');
        })
        ->where(function($q) use ($grupos){
            $q->where('grupos_usuarios','LIKE','%@@%');
            foreach($grupos as $grupo){
                $q->orWhereRaw('CONCAT(SPACE(1), REPLACE(tarea.grupos_usuarios, ",", " "), SPACE(1)) like "% '.$grupo.' %"');
            }
        })
        ->where(function($q)  use ($cuenta){
            $q->where('cuenta_id',$cuenta->id)
            ->where('proceso.activo', 1);
        })
        ->whereHas('tramite')
        ->get();

        foreach($etapas as $etapa)
        {
            if(puedeVisualizarla($etapa))
            {
                $c++;
            }
        }
    }
    return $c;
}

function getTotalAssigned()
{
    $cuenta=\Cuenta::cuentaSegunDominio();
    return Etapa::where('etapa.usuario_id', Auth::user()->id)->where('etapa.pendiente', 1)
        ->whereHas('tramite', function($q) use ($cuenta){
            $q->whereHas('proceso', function($q) use ($cuenta){
                $q->where('cuenta_id', $cuenta->id);
            });
        })
        ->whereHas('tarea', function($q){
            $q->where('activacion', "si")
            ->orWhere(function($q)
            {
                $q->where('activacion', "entre_fechas")
                ->where('activacion_inicio', '<=', Carbon::now())
                ->where('activacion_fin', '>=', Carbon::now());   
            });
        })
    ->count();
}

function getTotalHistory()
{
    $cuenta=\Cuenta::cuentaSegunDominio();
    return Etapa::where('pendiente', 0)
        ->whereHas('tramite', function($q) use ($cuenta){
            $q->whereHas('proceso', function($q) use ($cuenta){
                $q->where('cuenta_id', $cuenta->id);
            });
        })
        ->where('usuario_id', Auth::user()->id)
        ->count();
}

function linkActive($path)
{
    return Request::path() == $path ? 'active':'';
}

function getUrlSortUnassigned($request, $sortValue)
{
    $path = Request::path();
    $sort = $request->input('sort') == 'asc' ? 'desc':'asc';
    return  "/".$path.'?query='.$request->input('query').'&sortValue='.$sortValue."&sort=".$sort;
}

function getDateFormat($date, $type = 'update')
{
    return $date == null || !$date ? '' : Carbon::parse($date)->format('d-m-Y '.($type == 'update' ? 'H:i:s': ''));
}

function hasFiles($etapas)
{
    foreach ($etapas as $e)      
    {
        if($e->tramite->files->count() > 0)
        {
            return true;
        }
    }
    return false;
}
function getLastTask($etapa)
{

    return $etapa->tramite->etapas()->where('pendiente', 0)->orderBy('id', 'desc')->first() ? 
    getDateFormat($etapa->tramite->etapas()->where('pendiente', 0)->orderBy('id', 'desc')->first()->ended_at) : 'N/A';
}

function replace_unicode_escape_sequence($match) {
    return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
}

function unicode_decode($str) {
    return preg_replace_callback('/\\\\u([0-9a-f]{4})/i', 'replace_unicode_escape_sequence', $str);
}

function puedeVisualizarla($e)
{
    if ($e->tarea->acceso_modo == 'publico' || $e->tarea->acceso_modo == 'anonimo')
    {
        return true;
    }

    if ($e->tarea->acceso_modo == 'claveunica' && Auth::user()->open_id)
    {
        return true;
    }

    if ($e->tarea->acceso_modo == 'registrados' && Auth::user()->registrado)
    {
        return true;
    }
    if ($e->tarea->acceso_modo == 'grupos_usuarios') 
    {
        $r = new Regla($e->tarea->grupos_usuarios);
        $grupos_arr = explode(',', $r->getExpresionParaOutput($e->id));
        foreach (Auth::user()->grupo_usuarios as $g)
        {
            if (in_array($g->id, $grupos_arr))
            {
                return true;
            }
        }
    }
    return false;
}