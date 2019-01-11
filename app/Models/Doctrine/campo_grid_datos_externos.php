<?php
require_once('campo.php');

use Illuminate\Http\Request;
use App\Helpers\Doctrine;

class CampoGridDatosExternos extends Campo
{
    private $javascript;

    public $requiere_datos = false;
    private $cell_text_max_length_default = 50;

    protected function display($modo, $dato, $etapa_id = false)
    {
        if ($etapa_id) {
            $etapa = Doctrine::getTable('Etapa')->find($etapa_id);
            $regla = new Regla($this->valor_default);
            $valor_default = $regla->getExpresionParaOutput($etapa->id);
        } else {
            $valor_default = $this->valor_default;
        }

        $columns = $this->extra->columns;
        $eliminable = 'false';
        // FIXME: Comparar contra el tipo correcto
        if(isset($this->extra->eliminable) && ($this->extra->eliminable === "true" || $this->extra->eliminable === true || $this->extra->eliminable == 1)){
            $eliminable = true;
        }else{
            $eliminable = false;
        }

        $botones = [];

        if(isset($this->extra->agregable) && $this->extra->agregable == 'true'){
            $botones[] = '<button type="button" class="btn btn-outline-secondary" onclick="open_add_modal('.$this->id.')">Agregar</button>';
        }
        if(isset($this->extra->eliminable) && $this->extra->eliminable == 'true'){
            $botones[] = '<button type="button" class="btn btn-outline-secondary" style="" onclick="grilla_datos_externos_eliminar('.$this->id.')">Eliminar</button>';
        }

        if(isset($this->extra->validable) && $this->extra->validable && isset($this->extra->validate_url) && ! is_null($this->extra->validate_url)){
            $botones[] = '<button type="button" class="btn btn-outline-secondary" onclick="grilla_datos_externos_validar('.$this->id.',\''.$this->extra->validate_url.'\')">Validar</button>';
        }

        if( isset($this->extra->buttons_position) && $this->extra->buttons_position === 'bottom' ){
            $botones_position = $this->extra->buttons_position;
        }else{
            $botones_position = 'right_side';
        }

        $display_modal = '
        <div class="modal fade modalgrid" id="addToTableModal_'.$this->id.'" tabindex="-1" role="dialog" aria-labelledby="addToTableModal'.$this->id.'Label" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addToTableModal'.$this->id.'Label">Nuevo registro</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body" id="modal-body-'.$this->id.'">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="modal_agregar_a_grilla( '.$this->id.')">Agregar</button>
                    </div>
                </div>
            </div>
        </div>
        ';

        $display = '<label class="control-label" for="' . $this->id . '">' . $this->etiqueta . (!in_array('required', $this->validacion) ? ' (Opcional)' : '') . '</label>';
        $display .= '<input type="hidden" name="'.$this->nombre.'" id="'.$this->id.'">';
        $display .= '<div class="controls grid-Cls">
                        <div data-id="' . $this->id . '" >
                            <div class="container">
                                <div class="row">
                                    <div class="">
                                    <table class="table table-hover table-bordered" id="grilla-'.$this->id.'" data-grilla_id="'.$this->id.'">

                                    </table>
                                    </div>
                                    <div class="col-auto colautogrid" style="transform:translateY(+50%);">
                                    <!-- Al lado -->
                                        '.($botones_position == "right_side" ? implode("<br /><br />", $botones) : '').'
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="">
                                        '.($botones_position == "bottom" ? implode("\n", $botones) : '').'
                                    </div>
                                    <div class="">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
        $grilla_con_datos = '';

        // $display .= '<input type="hidden" name="' . $this->nombre . '" value=\'' . ($dato ? json_encode($dato->valor) : $valor_default) . '\' />';
        if ($this->ayuda)
            $display .= '<span class="help-block">' . $this->ayuda . '</span>';

        $data_array = array();
        if ($this->valor_default) {
            if($etapa_id) {
                $etapa = Doctrine::getTable('Etapa')->find($etapa_id);
                $regla = new Regla($this->valor_default);
                $data = $regla->getExpresionParaOutput($etapa->id);
                $data = json_decode($data,true);
                $data_array = array();
                if(count($data)){
                    $contador = 0;
                    foreach($data as $d){
                        $arreglo_tmp = array_values($d);
                        array_push($data_array, $arreglo_tmp);
                    }
                }
            }
        }

        $valor_default = json_decode($valor_default, true);
        $data = (isset($valor_default) && ! is_null($valor_default) && is_array($valor_default) ) ? $valor_default: [];
        $data = json_encode($data);
        $cell_max_length = (isset($this->extra->cell_text_max_length) ? $this->extra->cell_text_max_length: $this->cell_text_max_length_default);

        $display .='
        <script>
                $(document).ready(function(){
                    var data = '.$data.';
                    var is_array = '.(count($data) > 0 ? "Array.isArray(data[0])" : "false" ).';
                    var columns = '.json_encode($columns).';
                    grillas_datatable['.$this->id.'] = {};
                    grillas_datatable['.$this->id.'].validate_url = "'.(isset($this->extra->validate_url) ? $this->extra->validate_url : '' ).'";
                    grillas_datatable['.$this->id.'].validate_method = "'.(isset($this->extra->method) ? $this->extra->method : '' ).'";
                    grillas_datatable['.$this->id.'].eliminable = '.($eliminable ? 'true': 'false').';
                    grillas_datatable['.$this->id.'].columns_length = columns.length;
                    if('.($eliminable ? 'true': 'false').'){
                        grillas_datatable['.$this->id.'].columns_length++;
                    }

                    init_tables('.$this->id.', "'.$modo.'",columns,'.$cell_max_length.',is_array);
                    if(data.length > 0){
                        if(is_array){
                            grilla_populate_arrays('.$this->id.', data);
                        }else{
                            grilla_populate_objects('.$this->id.', data);
                        }
                    }else{
                        grillas_datatable['.$this->id.'].table.draw(true);
                    }
                });
            </script>
        ';
        $display .= $display_modal;
        return $display;
    }

    public function backendExtraFields()
    {

        $columns = array();
        if (isset($this->extra->columns))
            $columns = $this->extra->columns;

        $agregable = false;
        if(isset($this->extra->agregable) && $this->extra->agregable == 'true'){
            $agregable = true;
        }

        $eliminable = false;
        if(isset($this->extra->eliminable) && $this->extra->eliminable == 'true'){
            $eliminable = true;
        }

        $checked = true;
        if(isset($this->extra->validable)&& !$this->extra->validable){
            $checked = false;
        }

        $precarga = isset($this->extra->precarga) ? $this->extra->precarga : null;

        $hidden_arr = ['<input type="hidden" name="extra[validable]" value="'.($checked ? 'true': 'false').'" />'];
        $hidden_arr[] = '<input type="hidden" name="extra[agregable]" value="'.($agregable ? 'true': 'false').'" />';
        $hidden_arr[] = '<input type="hidden" name="extra[eliminable]" value="'.($eliminable ? 'true': 'false').'"/>';
        $hidden_arr[] = '<input type="hidden" name="extra[importable]" value="true"/>';
        $output = implode("\n", $hidden_arr);

        $column_template_html = "<tr>
                    <td><input type='text' name='extra[columns][{{column_pos}}][header]' class='form-control' value='{{header}}' /></td>
                    <td><select class='form-control' name='extra[columns][{{column_pos}}][type]' >
                            <option {{text_selected}}>text</option>
                            <option {{numeric_selected}}>numeric</option>
                        </select></td>
                        <td><input class='form-control' type='checkbox' {{is_input_checked}} onclick='return cambiar_estado_entrada(this, {{column_pos}});'>
                        <input type='hidden' name='extra[columns][{{column_pos}}][is_input]' value='{{is_input}}' />
                        </td><td>
                        <input class='form-control' type='input' name='extra[columns][{{column_pos}}][modal_add_text]' value='{{modal_add_text}}'/>
                        </td><td>
                        <input class='form-control' type='input' name='extra[columns][{{column_pos}}][object_field_name]' value='{{object_field_name}}'/>
                        </td><td>
                        <input class='form-control' type='checkbox' onclick='return cambiar_exportable(this,{{column_pos}});' {{is_exportable_checked}}>
                        <input type='hidden' name='extra[columns][{{column_pos}}][is_exportable]' value='{{is_exportable}}' />
                        </td><td><button type='button' class='btn btn-outline-secondary eliminar'><i class='material-icons'>close</i> Eliminar</button></td>
                        </tr>";

        $column_template_html = str_replace("\n", "", $column_template_html);

        if( isset($this->extra->cell_text_max_length) && ! is_null($this->extra->cell_text_max_length)){
            $cell_text_max_length = $this->extra->cell_text_max_length;
        }else{
            $cell_text_max_length = 50;
        }

        $buttons_position = (isset($this->extra->buttons_position)? $this->extra->buttons_position : 'bottom');

        $output .= '
            <br />
            <!--<div class="form-group">
                <label for="grilla_data_precarga">Variable de precarga</label>
                <input class="form-control" type="text" name="extra[precarga]" placeholder="@@data" id="grilla_data_precarga" value="'.$precarga.'"/>
            </div>
            <div class="form-group">
                <label for="grilla_validable">Es validable</label>
                <input class="form-control" type="checkbox" id="grilla_validable" onclick="toggleValidable(this)" '.($checked ? "checked": "").' />
            </div>
            <div class="input-group">
            <label for="grilla_datos_externos_validate_method">Metodo</label>
                <select name="extra[validate_method]" id="grilla_datos_externos_validate_method">
                    <option value="POST">POST</option>
                    <option value="PUT">PUT</option>
                </select>
            </div>
            <div class="input-group">
                <label for="grilla_datos_externos_validate_url">URL</label>
                <input class="form-control" type="text" name="extra[validate_url]" placeholder="http://foo.com/bar" id="grilla_datos_externos_validate_url"/>
            </div>-->
            <div class="input-group controls">
                <label class="controls-label-inputt" for="cella_datos_externos_table_text_max_length">Largo m&aacute;ximo del texto en las celdas:&nbsp;</label>
                <input class="form-control col-1" type="text" name="extra[cell_text_max_length]" id="cella_datos_externos_table_text_max_length" value="'.$cell_text_max_length.'"/>
            </div>
            <div class="input-group controls">
                <label for="grilla_agregable">Se puede Agregar</label>
                <input class="controls-inputchk" type="checkbox" id="grilla_agregable" onclick="toggleAgregable(this)" '.($agregable ? "checked": "").'/>
            </div>
            <div class="input-group controls">
                <label for="grilla_eliminable">Se puede eliminar</label>
                <input class="controls-inputchk" type="checkbox" id="grilla_eliminable" onclick="toggleEliminable(this)" '.($eliminable ? 'checked': "").'/>
            </div>
            <div class="input-group controls">
                <label class="controls-label-inputt" for="grilla_datos_externos_posicion_botones">Posicion botones</label>
                <select class="form-control col-2" name="extra[buttons_position]">
                    <option value="bottom" '.($buttons_position == "bottom" ? 'selected=selected': '').'>Abajo</option>
                    <option value="right_side" '.($buttons_position == "right_side" ? 'selected=selected': '').'>Al lado</option>
                </select>
            </div>
            <!--
                        <div class="input-group">
                            <label for="grilla_datos_externos_import_posicion">Importable</label>
                            <input type="checkbox" onclick="(this)" checked />
                        </div>
            -->
            <div class="columnas">
                <script type="text/javascript">
                    var column_template = "'.$column_template_html.'";

                    $(document).ready(function(){
                        $("#formEditarCampo .columnas .nuevo").click(function(){
                            var pos=$("#formEditarCampo .columnas table tbody tr").length;
                            var new_col = column_template.replace(/{{column_pos}}/g, pos);
                            new_col = new_col.replace(/{{([^}]+)\}}/g, "");
                            $("#formEditarCampo .columnas table tbody").append(
                                new_col
                            );
                        });
                        $("#formEditarCampo .columnas").on("click",".eliminar",function(){
                            var table = $(this).closest("table");
                            $(this).closest("tr").remove();
                            reindex_columns(table);
                        });
                    });
                    $("#cella_datos_externos_table_text_max_length").keydown(function(evt){
                        var key_code = evt.which;
                        if( key_code != 13 && key_code != 9 && key_code != 8 && ( key_code < 48 || key_code > 57 ) ) {
                            // 13 enter, 9 tab, 8 backspace
                            evt.preventDefault();
                            evt.stopPropagation();
                            return false;
                        }

                    });
                </script>
                <h4>Columnas</h4>
                <button class="btn btn-light nuevo" type="button"><i class="material-icons">add</i> Nuevo</button>
                <table class="table mt-3 table-striped">
                    <thead>
                        <tr>
                            <th>Etiqueta</th>
                            <th>Tipo</th>
                            <th>Es entrada</th>
                            <th>Texto al agregar</th>
                            <th>Nombre del campo<br/>si es objeto</th>
                            <th>Exportable</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    ';

        if ($columns) {
            foreach ($columns as $key => $c) {
                $text = isset($c->modal_add_text) ? $c->modal_add_text: "";

                $column = str_replace('{{column_pos}}', $key, $column_template_html);
                $column = str_replace('{{header}}', $c->header, $column);
                $column = str_replace('{{modal_add_text}}', $text, $column);
                $column = str_replace('{{is_input}}', $c->is_input, $column);
                $column = str_replace('{{object_field_name}}', (isset($c->object_field_name) ? $c->object_field_name : ''), $column);
                if(isset($c->is_input) && $c->is_input=="true"){
                    $column = str_replace('{{is_input}}', 'true', $column);
                    $column = str_replace('{{is_input_checked}}', 'checked', $column);
                }else{
                    $column = str_replace('{{is_input}}', 'false', $column);
                    $column = str_replace('{{is_input_checked}}', '', $column);
                }
                if(isset($c->type) && $c->type == 'numeric'){
                    $column = str_replace('{{numeric_selected}}', 'selected', $column);
                    $column = str_replace('{{text_selected}}', '', $column);
                }else{
                    $column = str_replace('{{numeric_selected}}', '', $column);
                    $column = str_replace('{{text_selected}}', 'selected', $column);
                }
                if(isset($c->is_exportable) && $c->is_exportable=="true"){
                    $column = str_replace('{{is_exportable}}', 'true', $column);
                    $column = str_replace('{{is_exportable_checked}}', 'checked', $column);
                }else{
                    $column = str_replace('{{is_exportable}}', 'false', $column);
                    $column = str_replace('{{is_exportable_checked}}', '', $column);
                }
                $output .= $column;
            }
        }

        $output .= '
        </tbody>
        </table>
        </div>

        ';

        return $output;
    }

    public function getJavascript()
    {
        return $this->javascript;
    }

    public function backendExtraValidate(Request $request)
    {
        $request->validate(['extra.columns' => 'required']);
    }

}
