@extends('layouts.procedure')

@section('content')
    <form method="POST" class="ajaxForm dynaForm"
          action="{{route('stage.ejecutar_fin_form', [$etapa->id])}}/{{$qs ? '?' . $qs : ''}}">
        {{csrf_field()}}
        <fieldset>
            <div class="validacion"></div>
            <legend><?= !is_null($etapa->Tarea->paso_confirmacion_titulo) ? $etapa->Tarea->paso_confirmacion_titulo : 'Paso final' ?> </legend>
            <?php if ($tareas_proximas->estado == 'pendiente'): ?>
            <?php foreach ($tareas_proximas->tareas as $t): ?>
            <p><?= !is_null($etapa->Tarea->paso_confirmacion_contenido) ? $etapa->Tarea->paso_confirmacion_contenido : "Para confirmar y enviar el formulario a la siguiente etapa ($t->nombre) haga click en
                Finalizar." ?> </p>
            <?php if ($t->asignacion == 'manual'): ?>
            <label>Asignar próxima etapa a</label>
            <select name="usuarios_a_asignar[<?= $t->id ?>]">
                <?php foreach ($t->getUsuarios($etapa->id) as $u): ?>
                <option value="<?= $u->id ?>"><?= $u->usuario ?> <?=$u->nombres ? '(' . $u->nombres . ' ' . $u->apellido_paterno . ')' : ''?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <?php endforeach; ?>
            <?php elseif($tareas_proximas->estado == 'standby'): ?>
            <p><?= !is_null($etapa->Tarea->paso_confirmacion_contenido) ? $etapa->Tarea->paso_confirmacion_contenido : 'Luego de hacer click en Finalizar esta etapa quedara detenida momentaneamente hasta que se completen el
                resto de etapas pendientes.' ?></p>
            <?php elseif($tareas_proximas->estado == 'completado'):?>
            <p><?= !is_null($etapa->Tarea->paso_confirmacion_contenido) ? $etapa->Tarea->paso_confirmacion_contenido : 'Luego de hacer click en Finalizar este trámite quedará completado.' ?></p>
            <?php elseif($tareas_proximas->estado == 'sincontinuacion'):?>
            <p><?= !is_null($etapa->Tarea->paso_confirmacion_contenido) ? $etapa->Tarea->paso_confirmacion_contenido : 'Este trámite no tiene una etapa donde continuar.' ?></p>
            <?php endif; ?>

            <div class="form-actions">
                <a class="btn btn-light"
                   href="<?= url('etapas/ejecutar/' . $etapa->id . '/' . (count($etapa->getPasosEjecutables()) - 1) . ($qs ? '?' . $qs : '')) ?>">
                    Volver
                </a>
                @if($tareas_proximas->estado != 'sincontinuacion')
                    <button class="btn btn-success" type="submit"><?= !is_null($etapa->Tarea->paso_confirmacion_texto_boton_final) ? $etapa->Tarea->paso_confirmacion_texto_boton_final : 'Finalizar' ?></button>
                @endif
            </div>
        </fieldset>
    </form>
@endsection
