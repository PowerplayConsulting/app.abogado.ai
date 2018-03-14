<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Helpers\Doctrine;
use Doctrine_Query;

class ProcessController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $cuenta_id = Auth::user()->cuenta_id;
        $data['procesos'] = Doctrine_Query::create()
            ->from('Proceso p, p.Cuenta c')
            ->where('p.activo=1 AND p.estado!="arch" AND c.id = ? 
                AND ((SELECT COUNT(proc.id) FROM Proceso proc WHERE proc.cuenta_id = ? AND (proc.root = p.id OR proc.root = p.root) AND proc.estado = "draft") = 0 
                OR p.estado = "draft")
                ', array($cuenta_id, $cuenta_id))
            ->orderBy('p.nombre asc')
            ->execute();

        $data['procesos_eliminados'] = Doctrine_Query::create()
            ->from('Proceso p, p.Cuenta c')
            ->where('p.activo=0 AND p.estado!="arch" AND c.id = ?', Auth::user()->cuenta_id)
            ->orderBy('p.nombre asc')
            ->execute();

        $cuenta = Doctrine::getTable('Cuenta')->find(Auth::user()->cuenta_id);
        $editar = true;

        if ($cuenta->ambiente == 'prod') {
            $cuenta_dev = $cuenta->getAmbienteDev($cuenta->id);
            if (count($cuenta_dev) > 0) {
                $editar = false;
            }
        }
        $data['editar_proceso'] = $editar;
        $data['title'] = 'Listado de Procesos';

        return view('backend.process.index', $data);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $proceso = new \Proceso();
        $proceso->nombre = 'Proceso';
        $proceso->cuenta_id = Auth::user()->cuenta_id;
        $proceso->estado = 'draft';
        $proceso->version = 0;

        $proceso->save();

        return redirect()->route('backend.procesos.edit', [$proceso->id]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * @param Request $request
     * @param $proceso_id
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception
     */
    public function destroy(Request $request, $proceso_id)
    {
        Log::info('eliminar ($proceso_id [' . $proceso_id . '])');

        $request->validate(['descripcion' => 'required']);

        $proceso = Doctrine::getTable('Proceso')->find($proceso_id);

        if ($proceso->cuenta_id != Auth::user()->cuenta_id) {
            echo 'Usuario no tiene permisos para eliminar este proceso';
            exit;
        }

        $fecha = new \DateTime();

        // Auditar
        $registro_auditoria = new \AuditoriaOperaciones();
        $registro_auditoria->fecha = $fecha->format("Y-m-d H:i:s");
        $registro_auditoria->operacion = 'Eliminación de Proceso';
        $registro_auditoria->motivo = $request->input('descripcion');
        $usuario = Auth::user();
        $registro_auditoria->usuario = $usuario->nombre . ' ' . $usuario->apellidos . ' <' . $usuario->email . '>';
        $registro_auditoria->proceso = $proceso->nombre;
        $registro_auditoria->cuenta_id = Auth::user()->cuenta_id;

        // Detalles
        $proceso_array['proceso'] = $proceso->toArray(false);

        $registro_auditoria->detalles = json_encode($proceso_array);
        $registro_auditoria->save();

        if ($proceso->estado != 'public') {
            $proceso->delete();
        } else {
            $proceso->delete_logico($proceso_id);
        }

        $request->session()->flash('success', 'Proceso eliminado con éxito.');

        return redirect()->route('backend.procesos.index');
    }

    /**
     * @param $proceso_id
     */
    public function edit($proceso_id)
    {

        Log::info('editar ($proceso_id [' . $proceso_id . '])');

        $proceso = Doctrine::getTable('Proceso')->find($proceso_id);

        Log::debug('$proceso->estado [' . $proceso->estado . '])');

        // Verificar si es draft o un proceso publicado
        if ($proceso->estado == 'public') { //no es draft
            //Se crea Draft
            Log::info("Creando Draft para proceso id " . $proceso_id);
            $proceso = $this->crearDraft($proceso);
        } elseif ($proceso->estado == 'arch') {
            $root = $proceso_id;

            Log::info("Editando proceso id " . $proceso_id);

            if (isset($proceso->root) && strlen($proceso->root) > 0) {
                $root = $proceso->root;
            }
            $proceso_draft = $proceso->findDraftProceso($root, Auth::user()->cuenta_id);

            Log::info("Se obtiene draft con id " . $proceso_draft->id);

            if (isset($proceso_draft) && $proceso_draft->id > 0) {
                $proceso_draft->estado = 'arch';
                $proceso_draft->save();
            }
            $proceso->estado = 'draft';
            $proceso->save();
        }
        Log::debug('$proceso->activo [' . $proceso->activo . '])');

        if ($proceso->cuenta_id != Auth::user()->cuenta_id || $proceso->activo != true) {
            echo 'Usuario no tiene permisos para editar este proceso';
            exit;
        }

        $procesosArchivados = $proceso->findProcesosArchivados($proceso->root);
        $data['procesos_arch'] = $procesosArchivados;

        $data['proceso'] = $proceso;

        $data['proceso_id'] = $proceso_id;

        $data['title'] = 'Modelador';
        $data['content'] = 'backend/procesos/editar';

        return view('backend.process.edit', $data);
    }

    /**
     * @param $proceso_id
     * @throws \Doctrine_Query_Exception
     */
    public function activar(Request $request, $proceso_id)
    {
        Log::info('activar ($proceso_id [' . $proceso_id . '])');

        $request->validate(['descripcipn' => 'required']);

        $respuesta = new stdClass();

        $proceso = Doctrine::getTable('Proceso')->find($proceso_id);

        if ($proceso->cuenta_id != Auth::user()->cuenta_id) {
            Log::debug('Usuario no tiene permisos para activar este proceso');
            echo 'Usuario no tiene permisos para activar este proceso';
            exit;
        }
        $fecha = new \DateTime();

        // Auditar
        $registro_auditoria = new AuditoriaOperaciones();
        $registro_auditoria->fecha = $fecha->format("Y-m-d H:i:s");
        $registro_auditoria->operacion = 'Activación de Proceso';
        $registro_auditoria->motivo = $request->input('descripcion');
        $usuario = Auth::user();
        $registro_auditoria->usuario = $usuario->nombre . ' ' . $usuario->apellidos . ' <' . $usuario->email . '>';
        $registro_auditoria->proceso = $proceso->nombre;
        $registro_auditoria->cuenta_id = Auth::user()->cuenta_id;

        // Detalles
        $proceso_array['proceso'] = $proceso->toArray(false);

        $registro_auditoria->detalles = json_encode($proceso_array);
        $registro_auditoria->save();
        Log::debug('$registro_auditoria->usuario: ' . $registro_auditoria->usuario);

        $q = Doctrine_Query::create()
            ->update('Proceso')
            ->set('activo', 1)
            ->where("id = ?", $proceso_id);
        $q->execute();

        return redirect()->route('backend.procesos.index');

    }

    /**
     * @param $proceso_id
     */
    public function ajax_editar($proceso_id)
    {
        $proceso = Doctrine::getTable('Proceso')->find($proceso_id);
        $categorias = Doctrine::getTable('Categoria')->findAll();

        if ($proceso->cuenta_id != Auth::user()->cuenta_id) {
            echo 'Usuario no tiene permisos para editar este proceso';
            exit;
        }

        $data['proceso'] = $proceso;
        $data['categorias'] = $categorias;

        return view('backend.process.ajax_editar', $data);
    }

    /**
     * @param $proceso_id
     */
    public function editar_form(Request $request, $proceso_id)
    {
        $proceso = Doctrine::getTable('Proceso')->find($proceso_id);

        if ($proceso->cuenta_id != Auth::user()->cuenta_id) {
            echo 'Usuario no tiene permisos para editar este proceso';
            exit;
        }

        $request->validate(['nombre' => 'required']);

        $proceso->nombre = $request->input('nombre');
        $proceso->width = $request->input('width');
        $proceso->height = $request->input('height');
        $proceso->categoria_id = $request->input('categoria');
        $proceso->icon_ref = $request->input('logo');
        $proceso->destacado = $request->has('destacado') ? 1 : 0;
        $proceso->save();

        return response()->json([
            'validacion' => true,
            'redirect' => route('backend.procesos.edit', [$proceso->id]),
        ]);
    }

    /**
     * @param $proceso_id
     * @param $tarea_identificador
     */
    public function ajax_crear_tarea(Request $request, $proceso_id, $tarea_identificador)
    {
        $proceso = Doctrine::getTable('Proceso')->find($proceso_id);

        if ($proceso->cuenta_id != Auth::user()->cuenta_id) {
            echo 'Usuario no tiene permisos para crear esta tarea.';
            exit;
        }

        $tarea = new \Tarea();
        $tarea->proceso_id = $proceso->id;
        $tarea->identificador = $tarea_identificador;
        $tarea->nombre = $request->input('nombre');
        $tarea->posx = $request->input('posx');
        $tarea->posy = $request->input('posy');
        $tarea->save();

    }

    /**
     * @param $proceso_id
     * @param $tarea_identificador
     */
    public function ajax_editar_tarea($proceso_id, $tarea_identificador)
    {
        $tarea = Doctrine::getTable('Tarea')->findOneByProcesoIdAndIdentificador($proceso_id, $tarea_identificador);
        $proceso = Doctrine::getTable('Proceso')->find($proceso_id);
        if ($tarea->Proceso->cuenta_id != Auth::user()->cuenta_id) {
            echo 'Usuario no tiene permisos para editar esta tarea.';
            exit;
        }
        $data['proceso_id'] = $proceso_id;
        $data['tarea'] = $tarea;
        $data['formularios'] = Doctrine::getTable('Formulario')->findByProcesoId($proceso_id);
        $data['acciones'] = Doctrine::getTable('Accion')->findByProcesoId($proceso_id);
        $data['proceso'] = $proceso;
        $data['variablesFormularios'] = Doctrine::getTable('Proceso')->findVariblesFormularios($proceso_id, $tarea['id']);
        $data['variablesProcesos'] = Doctrine::getTable('Proceso')->findVariblesProcesos($proceso_id);

        $cuentas = Doctrine::getTable('Cuenta')->findAll();

        $index = 0;
        foreach ($cuentas as $cuenta) {
            if ($tarea->Proceso->cuenta_id == $cuenta->id) {
                unset($cuentas[$index]);
                break;
            }
            $index++;
        }

        $data['cuentas'] = $cuentas;

        $proceso_cuenta = new \ProcesoCuenta();
        $data['cuentas_con_permiso'] = $proceso_cuenta->findCuentasProcesos($proceso_id);

        return view('backend.process.ajax_editar_tarea', $data);
    }

    /**
     * @param $tarea_id
     */
    public function editar_tarea_form(Request $request, $tarea_id)
    {
        $tarea = Doctrine::getTable('Tarea')->find($tarea_id);

        if ($tarea->Proceso->cuenta_id != Auth::user()->cuenta_id) {
            echo 'Usuario no tiene permisos para editar esta tarea.';
            exit;
        }

        $request->validate(['nombre' => 'required']);

        if ($request->has('vencimiento')) {
            $request->validate(['vencimiento_valor' => 'required|is_natural_no_zero']);
            //$this->form_validation->set_rules('vencimiento_valor', 'Valor de Vencimiento', 'required|is_natural_no_zero');
            if ($request->has('vencimiento_notificar')) {
                $request->validate([
                    'vencimiento_notificar_dias' => 'required|is_natural_no_zero',
                    'vencimiento_notificar_email' => 'required',
                ]);
                //$this->form_validation->set_rules('vencimiento_notificar_dias', 'Días para notificar vencimiento', 'required|is_natural_no_zero');
                //$this->form_validation->set_rules('vencimiento_notificar_email', 'Correo electronico para notificar vencimiento', 'required');
            }
        }

        Doctrine::getTable('Proceso')
            ->updateVaribleExposed($request->input('varForm'), $request->input('varPro'), $tarea->Proceso->id, $tarea_id);

        $proceso_cuenta = new \ProcesoCuenta();
        $proceso_cuenta->deleteCuentasConPermiso($tarea->Proceso->id);
        $cuentas_con_permiso = $request->input('cuentas_con_permiso');

        if (isset($cuentas_con_permiso) && count($cuentas_con_permiso) > 0) {
            foreach ($cuentas_con_permiso as $id_cuenta) {
                $proceso_cuenta = new \ProcesoCuenta();
                $proceso_cuenta->id_proceso = $tarea->Proceso->id;
                $proceso_cuenta->id_cuenta_origen = $tarea->Proceso->cuenta_id;
                $proceso_cuenta->id_cuenta_destino = $id_cuenta;
                $proceso_cuenta->save();
            }
        }

        $tarea->nombre = $request->input('nombre');
        $tarea->inicial = $request->has('inicial') ? $request->input('inicial') : 0;
        $tarea->final = $request->has('final') ? $request->input('final') : 0;
        $tarea->asignacion = $request->input('asignacion');
        $tarea->asignacion_usuario = $request->input('asignacion_usuario');
        $tarea->asignacion_notificar = $request->has('asignacion_notificar') ? $request->has('asignacion_notificar') : 0;
        $tarea->setGruposUsuariosFromArray($request->input('grupos_usuarios'));
        $tarea->setPasosFromArray($request->input('pasos', false));
        $tarea->setEventosExternosFromArray($request->input('eventos_externos', false));
        $tarea->setEventosFromArray($request->input('eventos', false));
        $tarea->paso_confirmacion = $request->input('paso_confirmacion');
        $tarea->almacenar_usuario = $request->has('almacenar_usuario') ? $request->input('almacenar_usuario') : 0;
        $tarea->almacenar_usuario_variable = $request->input('almacenar_usuario_variable');
        $tarea->acceso_modo = $request->input('acceso_modo');
        $tarea->activacion = $request->input('activacion');
        $tarea->activacion_inicio = strtotime($request->input('activacion_inicio'));
        $tarea->activacion_fin = strtotime($request->input('activacion_fin'));
        $tarea->vencimiento = $request->has('vencimiento') ? $request->input('vencimiento') : 0;
        $tarea->vencimiento_valor = $request->input('vencimiento_valor');
        $tarea->vencimiento_unidad = $request->input('vencimiento_unidad');
        $tarea->vencimiento_habiles = $request->has('vencimiento_habiles') ? $request->input('vencimiento_habiles') : 0;
        $tarea->vencimiento_notificar = $request->has('vencimiento_notificar') ? $request->input('vencimiento_notificar') : 0;
        $tarea->vencimiento_notificar_dias = $request->input('vencimiento_notificar_dias');
        $tarea->vencimiento_notificar_email = $request->input('vencimiento_notificar_email');
        $tarea->previsualizacion = $request->input('previsualizacion');
        $tarea->externa = $request->has('externa') ? $request->input('externa') : 0;
        $tarea->exponer_tramite = $request->input('exponer_tramite');
        $tarea->save();

        return response()->json([
            'validacion' => true,
            'redirect' => route('backend.procesos.edit', $tarea->Proceso->id),
        ]);
    }

    /**
     * @param $tarea_id
     */
    public function eliminar_tarea($tarea_id)
    {
        $tarea = Doctrine::getTable('Tarea')->find($tarea_id);

        if ($tarea->Proceso->cuenta_id != Auth::user()->cuenta_id) {
            echo 'Usuario no tiene permisos para eliminar esta tarea.';
            exit;
        }

        $proceso = $tarea->Proceso;

        $fecha = new \DateTime();

        // Auditar
        $registro_auditoria = new \AuditoriaOperaciones();
        $registro_auditoria->fecha = $fecha->format("Y-m-d H:i:s");
        $registro_auditoria->operacion = 'Eliminación de Tarea';
        $usuario = Auth::user();
        $registro_auditoria->usuario = $usuario->nombre . ' ' . $usuario->apellidos . ' <' . $usuario->email . '>';
        $registro_auditoria->proceso = $proceso->nombre;
        $registro_auditoria->cuenta_id = Auth::user()->cuenta_id;

        // Detalles
        $tarea_array['proceso'] = $proceso->toArray(false);

        $tarea_array['tarea'] = $tarea->toArray(false);
        unset($tarea_array['tarea']['posx']);
        unset($tarea_array['tarea']['posy']);
        unset($tarea_array['tarea']['proceso_id']);

        $registro_auditoria->detalles = json_encode($tarea_array);
        $registro_auditoria->save();

        $tarea->delete();

        return redirect()->route('backend.procesos.edit', [$proceso->id]);
    }

    /**
     * @param $proceso_id
     */
    public function ajax_crear_conexion(Request $request, $proceso_id)
    {

        $proceso = Doctrine::getTable('Proceso')->find($proceso_id);
        $tarea_origen = Doctrine::getTable('Tarea')->findOneByProcesoIdAndIdentificador($proceso_id, $request->input('tarea_id_origen'));
        $tarea_destino = Doctrine::getTable('Tarea')->findOneByProcesoIdAndIdentificador($proceso_id, $request->input('tarea_id_destino'));

        if ($proceso->cuenta_id != Auth::user()->cuenta_id) {
            echo 'Usuario no tiene permisos para crear esta conexion.';
            exit;
        }

        if ($tarea_origen->Proceso->cuenta_id != Auth::user()->cuenta_id) {
            echo 'Usuario no tiene permisos para crear esta conexion.';
            exit;
        }

        if ($tarea_destino->Proceso->cuenta_id != Auth::user()->cuenta_id) {
            echo 'Usuario no tiene permisos para crear esta conexion.';
            exit;
        }

        // El tipo solamente se setea en la primera conexion creada para esa tarea.
        $tipo = $request->input('tipo');
        if ($tarea_origen->ConexionesOrigen->count())
            $tipo = $tarea_origen->ConexionesOrigen[0]->tipo;

        $conexion = new \Conexion();
        $conexion->tarea_id_origen = $tarea_origen->id;
        $conexion->tarea_id_destino = $tarea_destino->id;
        $conexion->tipo = $tipo;
        $conexion->save();
    }

    /**
     * @param $proceso_id
     * @param $tarea_origen_identificador
     * @param null $union
     * @throws \Doctrine_Query_Exception
     */
    public function ajax_editar_conexiones($proceso_id, $tarea_origen_identificador, $union = null)
    {
        if (!is_null($union)) {
            $conexiones = Doctrine_Query::create()
                ->from('Conexion c, c.TareaDestino t')
                ->where('t.proceso_id=? AND t.identificador=?', array($proceso_id, $tarea_origen_identificador))
                ->execute();
        } else {
            $conexiones = Doctrine_Query::create()
                ->from('Conexion c, c.TareaOrigen t')
                ->where('t.proceso_id=? AND t.identificador=?', array($proceso_id, $tarea_origen_identificador))
                ->execute();
        }

        if ($conexiones[0]->TareaOrigen->Proceso->cuenta_id != Auth::user()->cuenta_id) {
            echo 'Usuario no tiene permisos para editar estas conexiones.';
            exit;
        }

        $data['proceso_id'] = $proceso_id;
        $data['conexiones'] = $conexiones;

        return view('backend.process.ajax_editar_conexiones', $data);
    }

    /**
     * @param $tarea_id
     */
    public function editar_conexiones_form($tarea_id)
    {

        Log::debug('method: editar_conexiones_form(' . $tarea_id . ')');

        $tarea = Doctrine::getTable('Tarea')->find($tarea_id);

        if ($tarea->Proceso->cuenta_id != Auth::user()->cuenta_id) {
            echo 'Usuario no tiene permisos para editar estas conexiones.';
            exit;
        }

        $this->form_validation->set_rules('conexiones', 'Conexiones', 'required');

        $respuesta = new stdClass();
        if ($this->form_validation->run() == TRUE) {

            $tarea->setConexionesFromArray($request->input('conexiones', false));
            $tarea->save();

            $respuesta->validacion = TRUE;
            $respuesta->redirect = site_url('backend/procesos/editar/' . $tarea->Proceso->id);

        } else {
            $respuesta->validacion = FALSE;
            $respuesta->errores = validation_errors();
        }

        echo json_encode($respuesta);
    }

    /**
     * @param $tarea_id
     */
    public function eliminar_conexiones($tarea_id)
    {
        $tarea = Doctrine::getTable('Tarea')->find($tarea_id);

        if ($tarea->Proceso->cuenta_id != Auth::user()->cuenta_id) {
            echo 'Usuario no tiene permisos para eliminar esta conexion.';
            exit;
        }

        $proceso = $tarea->Proceso;
        $tarea->ConexionesOrigen->delete();

        redirect('backend/procesos/editar/' . $proceso->id);
    }

    /**
     * @param $proceso_id
     */
    public function ajax_editar_modelo(Request $request, $proceso_id)
    {
        $proceso = Doctrine::getTable('Proceso')->find($proceso_id);

        if ($proceso->cuenta_id != Auth::user()->cuenta_id) {
            echo 'Usuario no tiene permisos para editar este proceso';
            exit;
        }

        $modelo = $request->input('modelo');

        $proceso->updateModelFromJSON($modelo);
    }

    /**
     * @param $proceso_id
     */
    public function export($proceso_id)
    {

        $proceso = Doctrine::getTable('Proceso')->find($proceso_id);

        $json = $proceso->exportComplete();

        header("Content-Disposition: attachment; filename=\"" . mb_convert_case(str_replace(' ', '-', $proceso->nombre), MB_CASE_LOWER) . ".simple\"");
        header('Content-Type: application/json');
        echo $json;

    }

    /**
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Exception
     */
    public function import(Request $request)
    {
        $file_path = $_FILES['archivo']['tmp_name'];

        if ($file_path) {
            $input = file_get_contents($_FILES['archivo']['tmp_name']);
            $proceso = \Proceso::importComplete($input, TRUE);

            if (!$proceso) {
                return redirect()->route('backend.procesos.index');
            }

            $proceso->save();

            Log::info("Migrando configuraciones de seguridad");
            $this->migrarSeguridadAcciones($proceso);
            Log::info("Migrando configuraciones de suscriptores");
            $this->migrarSuscriptores($proceso);

            $this->migrarEventosExternos($proceso);

            Log::info("Fin migración de proceso");
        }

        $request->session()->flash('success', 'Importado con éxito.');

        return redirect($_SERVER['HTTP_REFERER']);
    }

    /**
     * @param $proceso_draft_id
     */
    public function publicar($proceso_draft_id)
    {

        Log::info("ID Draft: " . $proceso_draft_id);

        $proceso_draft = Doctrine::getTable('Proceso')->find($proceso_draft_id);

        Log::info("Root Draft: " . $proceso_draft->root);

        Log::info("Auth::user()->cuenta_id: " . Auth::user()->cuenta_id);


        $activo = $proceso_draft->findIdProcesoActivo($proceso_draft->root, Auth::user()->cuenta_id);

        Log::info("Recuperado activo: [" . $activo->id . "]");

        if (strlen($activo->id) > 0) { // Existe proceso activo
            Log::info("Existe Proceso Activo");

            $activo->estado = 'arch';
            $activo->save();
            Log::info("Save() Estado arch Proceso Activo");
        } else {
            $proceso_draft->root = $proceso_draft->id;
        }

        $proceso_draft->estado = 'public';
        $proceso_draft->save();

        $cuenta = Doctrine::getTable('Cuenta')->find(Auth::user()->cuenta_id);

        if ($cuenta->ambiente == 'dev') {

            Log::info("Cuenta DEV");

            $fecha = new \DateTime();
            Log::info("Posterior a DateTime");

            Log::info("Previo proceso_draft->findIdProcesoActivo(proceso_draft->root [" . $proceso_draft->root . "], cuenta->vinculo_produccion [" . $cuenta->vinculo_produccion . "])");
            $proc_produccion = $proceso_draft->findIdProcesoActivo($proceso_draft->root, $cuenta->vinculo_produccion);
            Log::info("Posterior proceso_draft->findIdProcesoActivo");

            Log::info("proc_produccion->id [" . $proc_produccion->id . "]");
            Log::info("strlen proc_produccion->id [" . strlen($proc_produccion->id) . "]");

            if (strlen($proc_produccion->id) > 0) { // Existe proceso productivo

                // Auditar
                $registro_auditoria = new \AuditoriaOperaciones();
                $registro_auditoria->fecha = $fecha->format("Y-m-d H:i:s");
                $registro_auditoria->operacion = 'Eliminación de Proceso en cuenta productiva';
                $registro_auditoria->motivo = "Publicación de nueva versión";
                $usuario = Auth::user();
                $registro_auditoria->usuario = $usuario->nombre . ' ' . $usuario->apellidos . ' <' . $usuario->email . '>';
                $registro_auditoria->proceso = $proc_produccion->nombre;
                $registro_auditoria->cuenta_id = $cuenta->vinculo_produccion;
                // Detalles
                $proceso_array['proceso'] = $proc_produccion->toArray(false);
                $registro_auditoria->detalles = json_encode($proceso_array);
                $registro_auditoria->save();

                $proc_produccion->delete();
            }

            // Auditar
            Log::info("Inicio Auditoria");

            $registro_auditoria = new \AuditoriaOperaciones();
            $registro_auditoria->fecha = $fecha->format("Y-m-d H:i:s");
            $registro_auditoria->operacion = 'Publicación de Proceso a cuenta productiva';
            $registro_auditoria->motivo = "Publicación de nueva versión";
            $usuario = Auth::user();
            $registro_auditoria->usuario = $usuario->nombre . ' ' . $usuario->apellidos . ' <' . $usuario->email . '>';
            $registro_auditoria->proceso = $proceso_draft->nombre;
            $registro_auditoria->cuenta_id = $cuenta->id;
            // Detalles

            $proceso_array['proceso'] = $proceso_draft->toArray(false);
            $registro_auditoria->detalles = json_encode($proceso_array);
            $registro_auditoria->save();
            Log::info("Fin Auditoria");


            Log::debug('previo a Proceso::importComplete($proceso_draft->exportComplete());');
            $proceso = \Proceso::importComplete($proceso_draft->exportComplete(), false);
            Log::debug('$cuenta->vinculo_produccion [' . $cuenta->vinculo_produccion);
            $proceso->cuenta_id = $cuenta->vinculo_produccion;
            $proceso->save();
            Log::debug('post a $proceso->save();');

            $this->migrarSeguridadAcciones($proceso);
            $this->migrarSuscriptores($proceso);
            $this->migrarEventosExternos($proceso);

            $this->migrarGrupos($proceso, $cuenta);

        }

        Log::info("Proceso actualizado");

        return response()->json([
            'validacion' => true,
            'redirect' => route('backend.procesos.index')
        ]);
    }

    /**
     * @param $proceso
     * @param $cuenta
     */
    private function migrarGrupos($proceso, $cuenta)
    {
        //asignar grupos de usuario de producción por cada tarea
        Log::info("Revisando grupos para proceso id " . $proceso->id);
        $tareas = $proceso->getTareasProceso($proceso->id);

        foreach ($tareas as $tarea) {
            $idUsuarios = $tarea->grupos_usuarios;
            if (strlen($idUsuarios) > 0) {
                $ids = explode(",", $idUsuarios);
                if (count($ids) > 0) {
                    $ids_prod = "";
                    foreach ($ids as $id) {
                        $grupo = Doctrine::getTable("GrupoUsuarios")->find($id);

                        if (!false) {
                            continue;
                        }

                        Log::info("Revisando grupo: " . $grupo->nombre);
                        $grupo_prod = $grupo->existeGrupo($cuenta->vinculo_produccion);
                        if (isset($grupo_prod)) {
                            Log::info("Existe en produccion");
                            Log::info("Nombre: " . $grupo_prod->nombre);
                            if (strlen($ids_prod) > 0) {
                                $ids_prod .= "," . $grupo_prod->id;
                            } else {
                                $ids_prod = $grupo_prod->id;
                            }
                        } else {
                            Log::info("No existe en produccion");
                            $grupo_usuarios = new \GrupoUsuarios();
                            $grupo_usuarios->nombre = $grupo->nombre;
                            $grupo_usuarios->cuenta_id = $cuenta->vinculo_produccion;
                            $grupo_usuarios->save();
                            Log::info("Se crea grupo en produccion");
                            Log::info("Grupo creado: " . $grupo_usuarios->id);
                            if (strlen($ids_prod) > 0) {
                                $ids_prod .= "," . $grupo_usuarios->id;
                            } else {
                                $ids_prod = $grupo_usuarios->id;
                            }
                        }
                    }
                    Log::info("id grupos prod: " . $ids_prod);
                    if (strlen($ids_prod) > 0) {
                        $tarea->grupos_usuarios = $ids_prod;
                        $tarea->save();
                    }
                }
            }
        }
    }

    /**
     * @param $proceso
     */
    private function migrarEventosExternos($proceso)
    {
        Log::info("Revisando seguridad para proceso id " . $proceso->id);
        $tareas = $proceso->Tareas;
        foreach ($tareas as $tarea) {
            foreach ($tarea->Eventos as $evento) {
                if (isset($evento->evento_externo_id) && strlen($evento->evento_externo_id) > 0) {
                    $evento->evento_externo_id = $tarea->EventosExternos[$evento->evento_externo_id]->id;
                    $evento->save();
                }
            }
        }
    }

    /**
     * @param $proceso
     */
    private function migrarSeguridadAcciones($proceso)
    {
        Log::info("Revisando seguridad para proceso id " . $proceso->id);
        $acciones = $proceso->Acciones;
        foreach ($acciones as $accion) {
            if ($accion->tipo == 'rest' || $accion->tipo == 'soap' || $accion->tipo == 'callback') {
                if (isset($accion->extra->idSeguridad) && strlen($accion->extra->idSeguridad) > 0) {
                    $extra_accion = $accion->extra;
                    $extra_accion->idSeguridad = $proceso->Admseguridad[$accion->extra->idSeguridad]->id;
                    $accion->extra = $extra_accion;
                    Log::info("Guardando accion id " . $accion->id);
                    $accion->save();
                }
            } elseif ($accion->tipo == 'iniciar_tramite') {
                if (isset($accion->extra->tareaRetornoSel) && strlen($accion->extra->tareaRetornoSel) > 0) {
                    $extra_accion = $accion->extra;
                    $extra_accion->tareaRetornoSel = $proceso->Tareas[$accion->extra->tareaRetornoSel]->id;
                    $accion->extra = $extra_accion;
                    Log::info("Guardando accion id " . $accion->id);
                    $accion->save();
                }
            }
        }
    }

    /**
     * @param $proceso
     */
    private function migrarSuscriptores($proceso)
    {
        Log::info("Revisando suscriptores para proceso id " . $proceso->id);

        $suscriptores = $proceso->Suscriptores;
        foreach ($suscriptores as $suscriptor) {
            if (isset($suscriptor->extra->idSeguridad) && strlen($suscriptor->extra->idSeguridad) > 0) {
                $extra_suscriptor = $suscriptor->extra;
                $extra_suscriptor->idSeguridad = $proceso->Admseguridad[$suscriptor->extra->idSeguridad]->id;//$new_seguridad->id;
                $suscriptor->extra = $extra_suscriptor;
                Log::info("Guardando suscriptor id " . $suscriptor->id);
                $suscriptor->save();
            }
        }

        $acciones = $proceso->Acciones;
        foreach ($acciones as $accion) {
            if ($accion->tipo == 'webhook') {
                if (isset($accion->extra->suscriptorSel) && count($accion->extra->suscriptorSel) > 0) {
                    $suscriptores_seleccionados = array();
                    foreach ($accion->extra->suscriptorSel as $suscriptor) {
                        $suscriptores_seleccionados[] = $proceso->Suscriptores[$suscriptor]->id;//$new_suscriptor->id;
                    }
                    $extra_accion = $accion->extra;
                    $extra_accion->suscriptorSel = $suscriptores_seleccionados;
                    $accion->extra = $extra_accion;
                    Log::info("Guardando accion id " . $accion->id);
                    $accion->save();
                }
            }
        }
    }

    /**
     * @param $proceso
     * @return mixed
     */
    private function crearDraft($proceso)
    {

        $proceso_id = $proceso->id;

        Log::info("Buscando si proceso ya tiene draft creado");

        $root = $proceso_id;
        if (isset($proceso->root) && strlen($proceso->root) > 0) {
            $root = $proceso->root;
        }

        Log::info("Buscando draft con root: " . $root);

        $draft = $proceso->findDraftProceso($root, Auth::user()->cuenta_id);

        Log::info("Draft: *" . $draft->id . "*");
        //Log::info("Draft2: ".$draft[0]->id);

        if (strlen($draft->id) == 0) { //No existe draft
            Log::info("Draft no existe");
            $proceso = \Proceso::importComplete($proceso->exportComplete());

            Log::info("Buscando última version");
            $max_version = $proceso->findMaxVersion($root, Auth::user()->cuenta_id);
            Log::info("Ultima version recuperada. " . $max_version);

            $proceso->version = $max_version + 1;
            $proceso->estado = 'draft';

            if (!isset($proceso->root) || strlen($proceso->root) == 0) {
                $proceso->root = $proceso_id;
            }

            $proceso->save();

            $this->migrarSeguridadAcciones($proceso);
            $this->migrarSuscriptores($proceso);
            $this->migrarEventosExternos($proceso);

        } else {
            Log::info("Redirigiendo a edición de Draft con id: " . $draft->id);
            $proceso = $draft;//Doctrine::getTable('Proceso')->find($draft[0]["id"]);
        }

        return $proceso;

    }

    /**
     * @param $proceso_id
     */
    public function ajax_auditar_eliminar_proceso($proceso_id)
    {
        if (!in_array('super', explode(",", Auth::user()->rol)))
            show_error('No tiene permisos', 401);

        $proceso = Doctrine::getTable("Proceso")->find($proceso_id);
        $data['proceso'] = $proceso;

        return view('backend.process.ajax_auditar_eliminar_proceso', $data);
    }

    /**
     * @param $proceso_id
     */
    public function ajax_auditar_activar_proceso($proceso_id)
    {
        if (!in_array('super', explode(",", Auth::user()->rol))) {
            return abort(401);
        }

        $proceso = Doctrine::getTable("Proceso")->find($proceso_id);
        $data['proceso'] = $proceso;

        return view('backend.process.ajax_auditar_activar_proceso', $data);
    }

    /**
     * @param $proceso_id
     * @throws \Doctrine_Query_Exception
     */
    public function getJSONFromModelDraw($proceso_id)
    {
        $proceso = Doctrine::getTable("Proceso")->find($proceso_id);
        $modelo = new \stdClass();
        $modelo->nombre = $proceso->nombre;
        $modelo->elements = array();
        $modelo->connections = array();

        $tareas = Doctrine::getTable('Tarea')->findByProcesoId($proceso_id);
        foreach ($tareas as $t) {
            $element = new \stdClass();
            $element->id = $t->identificador;
            $element->name = $t->nombre;
            $element->left = $t->posx;
            $element->top = $t->posy;
            $element->start = $t->inicial;
            $element->stop = $t->final;
            $modelo->elements[] = clone $element;
        }
        //$conexiones1=  Doctrine_Query::create()->from('Conexion c, c.TareaOrigen.Proceso p')->where('p.id = ?',$proceso_id);
        $conexiones = Doctrine_Query::create()
            ->from('Conexion c, c.TareaOrigen.Proceso p')
            ->where('p.id = ?', $proceso_id)
            ->execute();
        //echo $conexiones1->getSqlQuery();
        foreach ($conexiones as $c) {
            //$conexion->id=$c->identificador;
            $conexion = new \stdClass();
            $conexion->source = $c->TareaOrigen->identificador;
            $conexion->target = $c->TareaDestino->identificador;
            $conexion->tipo = $c->tipo;
            $modelo->connections[] = clone $conexion;
        }
        //print_r(json_encode($modelo));
        //exit;
        echo json_encode($modelo);
    }

    /**
     *
     */
    public function seleccionar_icono()
    {
        $DS = DIRECTORY_SEPARATOR;
        $directory = resource_path('assets/img/icon');
        $html = '';
        $error = '';
        $hideButton = true;
        $isImage = false;

        if (file_exists($directory)) {
            $icons = @scandir($directory);
            if ($icons !== FALSE) {
                if (count($icons) > 0) {
                    $hideButton = false;
                    foreach ($icons as $icon) {
                        if ($this->isImage($directory . $DS . $icon)) {
                            $isImage = true;
                            $html .= '<div class="item"><a class="sel-icono" href="javascript:;" rel="' . $icon . '"><img src="' . asset('img/icon/' . $icon) . '" alt="' . $icon . '" title="' . $icon . '"></a></div>';
                        }
                    }

                    if (!$isImage) {
                        $error .= '<div class="alert alert-error"><a class="close" data-dismiss="alert">×</a>No hay &iacuteconos en la carpeta "assets/img/icon"</div>';
                    }
                } else {
                    $error .= '<div class="alert alert-error"><a class="close" data-dismiss="alert">×</a>No hay &iacuteconos en la carpeta "assets/img/icon"</div>';
                }
            } else {
                $error .= '<div class="alert alert-error"><a class="close" data-dismiss="alert">×</a>No se pudo leer la carpeta "assets/img/icon"</div>';
            }
        } else {
            $error .= '<div class="alert alert-error"><a class="close" data-dismiss="alert">×</a>La carpeta "assets/img/icon" no existe</div>';
        }

        $data['hideButton'] = $hideButton;
        $data['iconos'] = $html;
        $data['error'] = $error;

        return view('backend.process.seleccionar_icono', $data);
    }

    /**
     * @param $image
     * @return bool
     */
    private function isImage($image)
    {
        return @is_array(getimagesize($image));
    }

    /**
     * @param $proceso_id
     */
    public function ajax_publicar_proceso($proceso_id)
    {
        if (!in_array('super', explode(",", Auth::user()->rol)))
            abort(401);

        $proceso = Doctrine::getTable("Proceso")->find($proceso_id);
        $data['proceso'] = $proceso;

        return view('backend.process.ajax_publicar_proceso', $data);
    }

}
