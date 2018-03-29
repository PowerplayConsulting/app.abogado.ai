@extends('layouts.backend')

@section('title', 'Trámites disponibles')

@section('content')
    <div class="container-fluid">
        <div class="row mt-3">

            @include('backend.api.nav')

            <div class="col-9">
                <h2><?=$title?></h2>

                <p>Lista todos los procesos.</p>

                <h3>Request HTTP</h3>

                <pre>GET <?= url('backend/api/procesos') ?>?token={token}</pre>

                <h3>Response HTTP</h3>

                <p>Si el request es correcto, se devuelve la siguiente estructura:</p>

                <pre>{
    "procesos":{
        "titulo":"Listado de Procesos",
        "tipo":"#procesosFeed",
        "items":[
            <a href="<?=url('backend/api/procesos_recurso')?>">recurso proceso</a>
        ]
    }
}</pre>
                <p>Las propiedades que incorpora esta respuesta son:</p>

                <table class="table table-bordered">
                    <tr>
                        <th>Nombre del Parámetro</th>
                        <th>Valor</th>
                        <th>Descripción</th>
                    </tr>
                    <tr>
                        <td>titulo</td>
                        <td>string</td>
                        <td>El título de este listado de procesos.</td>
                    </tr>
                    <tr>
                        <td>tipo</td>
                        <td>string</td>
                        <td>Identifica el nombre de este recurso.</td>
                    </tr>
                    <tr>
                        <td>items</td>
                        <td>array</td>
                        <td>El listado de procesos.</td>
                    </tr>
                </table>
            </div>

        </div>

    </div>
@endsection