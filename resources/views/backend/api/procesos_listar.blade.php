@extends('layouts.backend')

@section('title', 'Procesos: listar')

@section('content')
    <div class="container-fluid">
        <div class="row mt-3">

            @include('backend.api.nav')

            <div class="span9">
                <h2>Procesos: listar</h2>

                <p>Lista todos los procesos.</p>

                <h3>Request HTTP</h3>

                <pre>GET {{env('APP_URL')}}/api/procesos?token={token}</pre>

                <h3>Response HTTP</h3>

                <p>Si el request es correcto, se devuelve la siguiente estructura:</p>

                <pre>{
    "procesos":{
        "titulo":"Listado de Procesos",
        "tipo":"#procesosFeed",
        "items":[
            <a href="{{route('backend.api.procesos_recurso')}}">recurso proceso</a>
        ]
    }
}</pre>
                <p>Las propiedades que incorpora esta respuesta son:</p>

                <table class="table table-bordered">
                    <tbody>
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
                    </tbody>
                </table>
            </div>

        </div>
    </div>
@endsection