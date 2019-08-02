<div class="row">
    <div class="col-xs-12 col-md-8">
        <h2>Etapas sin asignar</h2>
    </div>
    <div class="col-xs-12 col-md-4">
        <div class="float-right">
            <a href='#' onclick='toggleBusquedaAvanzada()'>Opciones de Búsqueda</a>
        </div>
    </div>
</div>

<div class="col-xs-12 col-md-12 ">
    <form method="GET" action="">
        <div id="filters" class="jumbotron" style='padding: 2rem 2rem;display: {{ !null ? 'block' : 'none' }}'>
            <input type='hidden' name='busqueda_avanzada' value='1'/>
            <div class="row">
                <div class="col-12">
                    <label class='col-form-label'>Seleccione tipo de búsqueda:</label><br>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input search-selector" type="radio"
                               name="params[option]" id="inlineRadio5" value="option5">
                        <label class="form-check-label" for="inlineRadio5">Sin filtro</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input search-selector" type="radio"
                               name="params[option]" id="inlineRadio1" value="option1">
                        <label class="form-check-label" for="inlineRadio1">Buscar por Nro</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input search-selector" type="radio"
                               name="params[option]" id="inlineRadio3" value="option3">
                        <label class="form-check-label" for="inlineRadio3">
                            Buscar por Referencia
                        </label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input search-selector" type="radio"
                               name="params[option]" id="inlineRadio4" value="option4">
                        <label class="form-check-label" for="inlineRadio4">Buscar por Nombre</label>
                    </div>
                </div>
                <div class="col-4">
                    <div class="search-inputs">
                        <div class='control-group seg-input-search' id="input1">
                            <label class='col-form-label'>Ingrese Nro:</label>
                            <input name="params[tramite_id]" value=""
                                   type="text" class="form-control"/>
                        </div>
                        <div class='control-group seg-input-search' id="input3">
                            <label class='col-form-label'>Ingrese Valor de referencia:</label>
                            <input name="params[ref]" value="" type="text"
                                   class="form-control"/>
                        </div>
                        <div class='control-group seg-input-search' id="input4">
                            <label class='col-form-label'>Ingrese nombre:</label>
                            <input name="params[name]" value="" type="text"
                                   class="form-control"/>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <br><br>
                    <div class="row">
                        <div class="col-6">
                            <div class="row">
                                <div class="col-12">
                                    <label class='col-form-label'>Última modificación (opcional):</label>
                                </div>
                                <div class="col-6">
                                    <input type='text' name='params[updated_date_from]' placeholder='Desde'
                                           class='datetimepicker form-control' value=''/>
                                </div>
                                <div class="col-6">
                                    <input type='text' name='params[updated_date_to]' placeholder='Hasta'
                                           class='datetimepicker form-control' value=''/>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="row">
                                <div class="col-12">
                                    <label class='col-form-label'>Fecha de vencimiento (opcional):</label>
                                </div>
                                <div class="col-6">
                                    <input type='text' name='params[deleted_date_from]' placeholder='Desde'
                                           class='datetimepicker form-control' value=''/>
                                </div>
                                <div class="col-6">
                                    <input type='text' name='params[deleted_date_to]' placeholder='Hasta'
                                           class='datetimepicker form-control' value=''/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <hr/>
            <div style='text-align: right;'>
                <button type="submit" class="btn btn-primary">Buscar</button>
            </div>
        </div>
    </form>
    <?php if (count($etapas) > 0): ?>
    <table id="mainTable" class="table">
        <thead>
            <tr>
                <th></th>
                <th>Nro</th>
                <th>Ref.</th>
                <th>Nombre</th>
                <th>Etapa</th>
                <th>Modificación</th>
                <th>Vencimiento</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php $registros = false; ?>
        <?php foreach ($etapas as $e): ?>
        <?php
        $file = false;
        if (\App\Helpers\Doctrine::getTable('File')->findByTramiteId($e->id)->count() > 0) {
            $file = true;
            $registros = true;
        }
        ?>
        <?php
            $previsualizacion = '';
            if ( ! empty($e->previsualizacion)){
                $r = new Regla($e->previsualizacion);
                $previsualizacion = $r->getExpresionParaOutput($e->etapa_id);
            }

        ?>
        <tr <?=$previsualizacion ? 'data-toggle="popover" data-html="true" data-title="<h4>Previsualización</h4>" data-content="' . htmlspecialchars($previsualizacion) . '" data-trigger="hover" data-placement="bottom"' : ''?>>
            <?php if (Cuenta::cuentaSegunDominio()->descarga_masiva): ?>
                <?php if ($file): ?>
                    <td>
                        <div class="checkbox"><label><input type="checkbox" class="checkbox1" name="select[]"
                                                            value="<?=$e->id?>"></label></div>
                    </td>
                <?php else: ?>
                    <td></td>
                <?php endif; ?>
            <?php else: ?>
                <td></td>
            <?php endif; ?>
            <td><?=$e->id?></td>
            <td class="name">
                <?php
                $t = \App\Helpers\Doctrine::getTable('Tramite')->find($e->id);
                $tramite_nro = '';
                foreach ($t->getValorDatoSeguimiento() as $tra_nro) {
                    if ($tra_nro->nombre == 'tramite_ref') {
                        $tramite_nro = $tra_nro->valor;
                    }
                }
                echo $tramite_nro != '' ? $tramite_nro : $e->p_nombre;
                ?>
            </td>
            <td class="name">
                <?php
                $tramite_descripcion = '';
                foreach ($t->getValorDatoSeguimiento() as $tra) {
                    if ($tra->nombre == 'tramite_descripcion') {
                        $tramite_descripcion = $tra->valor;
                    }
                }
                echo $tramite_descripcion != '' ? $tramite_descripcion : $e->p_nombre;
                ?>
            </td>
            <td><?=$e->t_nombre ?></td>
            <td class="time"><?= strftime('%d.%b.%Y', mysql_to_unix($e->updated_at))?>
                <br/><?= strftime('%H:%M:%S', mysql_to_unix($e->updated_at))?></td>
            <td><?=$e->vencimiento_at ? strftime('%c', strtotime($e->vencimiento_at)) : 'N/A'?></td>
            <td class="actions">
                <a href="<?=url('etapas/asignar/' . $e->etapa_id)?>" class="btn btn-link"><i
                            class="icon-check icon-white"></i> Asignármelo</a>
                <?php if (Cuenta::cuentaSegunDominio()->descarga_masiva): ?>
                <?php if ($file): ?>
                <a href="#" onclick="return descargarDocumentos(<?=$e->id?>);" class="btn btn-link"><i
                            class="icon-download icon-white"></i> Descargar</a>
                <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (Cuenta::cuentaSegunDominio()->descarga_masiva): ?>
    <?php if ($registros): ?>
    <div class="pull-right">
        <div class="checkbox">
            <input type="hidden" id="tramites" name="tramites"/>
            <label>
                <input type="checkbox" id="select_all" name="select_all"/> Seleccionar todos
                <a href="#" onclick="return descargarSeleccionados();" class="btn btn-success preventDoubleRequest">
                    <i class="icon-download icon-white"></i> Descargar seleccionados
                </a>
            </label>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    <p><?= $etapas->links('vendor.pagination.bootstrap-4') ?></p>
    <?php else: ?>
    <p>No hay trámites para ser asignados.</p>
    <?php endif; ?>
</div>
<div class="modal hide" id="modal"></div>
@push('script')
    <script>
        let SEARCH_OPT = {!! json_encode(null) !!};
        function checkSearchInputs(val) {
            $('.seg-input-search').hide();
            switch (val) {
                case 'option1':
                    $('#input1').show();
                    break;
                case 'option3':
                    $('#input3').show();
                    break;
                case 'option4':
                    $('#input4').show();
                    break;
            }
        }
        function descargarDocumentos(tramiteId) {
            $("#modal").load("/etapas/descargar/" + tramiteId);
            $("#modal").modal();
            return false;
        }

        $(document).ready(function () {
            $('#select_all').click(function (event) {
                var checked = [];
                $('#tramites').val();
                if (this.checked) {
                    $('.checkbox1').each(function () {
                        this.checked = true;
                    });
                } else {
                    $('.checkbox1').each(function () {
                        this.checked = false;
                    });
                }
                $('#tramites').val(checked);
            });

            checkSearchInputs(SEARCH_OPT);
            switch (SEARCH_OPT) {
                case 'option1':
                    $('#inlineRadio1').prop('checked', true);
                    break;
                case 'option3':
                    $('#inlineRadio3').prop('checked', true);
                    break;
                case 'option4':
                    $('#inlineRadio4').prop('checked', true);
                    break;
                default:
                    $('#inlineRadio5').prop('checked', true);
            }

            $('.datetimepicker').datetimepicker({
                format: 'DD-MM-YYYY',
                icons: {
                    previous: "glyphicon glyphicon-chevron-left",
                    next: "glyphicon glyphicon-chevron-right"
                },
                locale: 'es'
            });

            $('.search-selector').on('click', function() {
                checkSearchInputs($(this).val())
            });
        });

        function toggleBusquedaAvanzada() {
            $("#filters").slideToggle();
        }

        function descargarSeleccionados() {
            var numberOfChecked = $('.checkbox1:checked').length;
            if (numberOfChecked == 0) {
                alert('Debe seleccionar al menos un trámite');
                return false;
            } else {
                var checked = [];
                $('.checkbox1').each(function () {
                    if ($(this).is(':checked')) {
                        checked.push(parseInt($(this).val()));
                    }
                });
                $('#tramites').val(checked);
                var tramites = $('#tramites').val();
                $("#modal").load("/etapas/descargar/" + vtramites);
                $("#modal").modal();
                return false;
            }
        }
    </script>
@endpush