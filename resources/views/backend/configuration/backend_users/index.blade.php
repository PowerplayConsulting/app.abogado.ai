@extends('layouts.backend')

@section('title', 'Configuración de Usuarios')

@section('content')
    <div class="container-fluid">
        <div class="row mt-3">

            @include('backend.configuration.nav')

            <div class="col-md-9">

                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="{{route('backend.configuration.my_site')}}">Configuración</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Usuarios</li>
                    </ol>
                </nav>

                <div class="row">
                    <div class="col-12">
                        <a href="{{route('backend.configuration.backend_users.add')}}"
                           class="btn btn-success">
                            <i class="material-icons">note_add</i> Nuevo
                        </a>
                    </div>
                    <br>
                    <br>
                    <div class="col-12">
                        <table class="table">
                            <thead>
                            <th>E-Mail</th>
                            <th>Nombre</th>
                            <th>Apellidos</th>
                            <th>Rol</th>
                            <th>Acciones</th>
                            </thead>
                            <tbody>
                            @foreach($users as $user)
                                <tr>
                                    <td>{{$user->email}}</td>
                                    <td>{{$user->nombre}}</td>
                                    <td>{{$user->apellidos}}</td>
                                    <td>{{$user->rol}}</td>
                                    <td>
                                        <a href="{{route('backend.configuration.backend_users.edit', $user->id)}}"
                                           class="btn btn-primary">
                                            <i class="material-icons">edit</i> Editar
                                        </a>
                                        <form id="form-<?= $user->id ?>" method="post"
                                              action="{{route('backend.configuration.backend_users.delete', $user->id)}}"
                                              style="display: inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="_method" value="DELETE"/>
                                            <a class="btn btn-danger"
                                               onclick="if(confirm('¿Está seguro que desea eliminar?')) document.querySelector('#form-<?= $user->id ?>').submit(); return false;"
                                               href="#">
                                                <i class="material-icons">close</i> Eliminar
                                            </a>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
