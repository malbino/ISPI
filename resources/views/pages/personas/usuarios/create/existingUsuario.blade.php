@extends('layouts.backend')

@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        @media (max-width: 900px) {
            .hide-on-small {
                display: none;
            }
        }

        .content-container {
            margin: 1%;
            max-width: 100%;
            padding: 0 15px;
        }

        .form-floating {
            margin-bottom: 1.5rem;
        }

        .form-control-lg {
            font-size: 1.125rem;
            padding: .75rem 1.25rem;
        }

        .form-group {
            margin-bottom: 15px;
        }

        /* Hide default buttons */
        #wizard .actions {
            display: none;
        }

        /* Hide dots if still visible */
        #wizard .steps ul li:before {
            display: none !important;
        }

        /* Hide default tabs if necessary */
        #wizard .steps {
            display: none;
        }

        /* Style custom tabs */
        #custom-tabs .nav-tabs {
            margin-bottom: 20px;
        }

        #custom-tabs .nav-link {
            cursor: pointer;
        }

        #custom-tabs .nav-link.active {
            font-weight: bold;
        }
    </style>
@endsection

@section('content')
    <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center py-2">
                <div class="flex-grow-1">
                    <h1 class="h3 fw-bold mb-1">Asignación de Usuario a Persona</h1>
                    <h2 class="fs-base lh-base fw-medium text-muted mb-0">Nuevo registro</h2>
                </div>
                <nav class="flex-shrink-0 mt-3 mt-sm-0 ms-sm-3" aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-alt">
                        <li class="breadcrumb-item">Usuarios</li>
                        <li class="breadcrumb-item active" aria-current="page">Asignación de Usuario</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
    <br>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">{{ __('Registrar Usuario') }}</h3>
                    </div>
                    <br>
                    <div class="container">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" id="newUserForm" action="{{ route('personas.usuarios.registerE') }}">
                            @csrf
                            <div class="row">
                                <!-- Nick Field -->
                                <div class="col-md-6 order-1 order-md-1">
                                    <div class="form-group">
                                        <label for="nick">Nick</label>
                                        <input type="text" class="form-control form-control-lg form-control-alt"
                                            id="nick" name="nick" placeholder="Nick" required>
                                        @error('nick')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Email Field -->
                                <div class="col-md-6 order-2 order-md-2">
                                    <div class="form-group">
                                        <label for="email">Correo Electrónico</label>
                                        <input type="email" class="form-control form-control-lg form-control-alt"
                                            id="email" name="email" placeholder="Correo Electrónico" required>
                                        @error('email')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Password Field -->
                                <div class="col-md-6 order-3 order-md-3">
                                    <div class="form-group">
                                        <label for="password">Contraseña</label>
                                        <input type="password" class="form-control form-control-lg form-control-alt"
                                            id="password" name="password" placeholder="Contraseña" required>
                                        @error('password')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Password Confirmation Field -->
                                <div class="col-md-6 order-4 order-md-4">
                                    <div class="form-group">
                                        <label for="password_confirmation">Confirmar Contraseña</label>
                                        <input type="password" class="form-control form-control-lg form-control-alt"
                                            id="password_confirmation" name="password_confirmation"
                                            placeholder="Confirmar Contraseña" required>
                                    </div>
                                </div>

                                <!-- Persona Field -->
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="id_persona">Persona</label>
                                        <div class="col-md-5 col-12">
                                            <select class="js-example-basic-single form-control form-control-lg"
                                                id="id_persona" name="id_persona" required>
                                                <option value="" disabled selected>Selecciona una persona</option>
                                                @foreach ($personas as $persona)
                                                    <option value="{{ $persona->id_persona }}">
                                                        [{{ $persona->carnet }}] {{ substr($persona->nombre, 0, 2) . '.' }}
                                                        {{ $persona->papellido }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @error('id_persona')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="form-group row mb-0">
                                <div class="col-md-3 offset-md-9 col-12 text-center text-md-right">
                                    <button type="submit" class="btn btn-primary w-100 w-md-25" disabled>Registrar</button>
                                </div>
                            </div>

                            <br>
                        </form>


                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('js')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/jquery.validation/1.19.3/jquery.validate.min.js"></script>

    <script>
        $(document).ready(function() {
            // Select2 for the persona select field
            $('#id_persona').select2({
                placeholder: "Selecciona una persona",
                allowClear: true,
                // width: 'resolve'
            });
            // jQuery Validation for the form
            $("#newUserForm").validate({
                ignore: [], // Ensures Select2 is not ignored
                rules: {
                    nick: {
                        required: true,
                        maxlength: 255
                    },
                    email: {
                        required: true,
                        email: true,
                        maxlength: 255
                    },
                    password: {
                        required: true,
                        minlength: 8
                    },
                    password_confirmation: {
                        required: true,
                        equalTo: "#password"
                    },
                    id_persona: {
                        required: true
                    }
                },
                messages: {
                    nick: {
                        required: "El nick es requerido.",
                        maxlength: "El nick no puede tener más de 255 caracteres."
                    },
                    email: {
                        required: "El correo electrónico es requerido.",
                        email: "Por favor ingrese un correo electrónico válido.",
                        maxlength: "El correo electrónico no puede tener más de 255 caracteres."
                    },
                    password: {
                        required: "La contraseña es requerida.",
                        minlength: "La contraseña debe tener al menos 8 caracteres."
                    },
                    password_confirmation: {
                        required: "Por favor confirme su contraseña.",
                        equalTo: "Las contraseñas no coinciden."
                    },
                    id_persona: {
                        required: "Debe seleccionar una persona."
                    }
                },
                errorPlacement: function(error, element) {
                    error.addClass('text-danger');
                    if (element.hasClass("js-example-basic-single")) {
                        error.insertAfter(element.next('span')); // For Select2 elements
                    } else {
                        error.insertAfter(element);
                    }
                },
                highlight: function(element) {
                    $(element).addClass("is-invalid").removeClass("is-valid");
                },
                unhighlight: function(element) {
                    $(element).addClass("is-valid").removeClass("is-invalid");
                },
                submitHandler: function(form) {
                    form.submit(); // Submit the form when valid
                }
            });

            // Function to check the entire form's validity and toggle the submit button
            function toggleSubmitButton() {
                if ($("#newUserForm").valid()) {
                    $('button[type="submit"]').removeAttr('disabled');
                } else {
                    $('button[type="submit"]').attr('disabled', 'disabled');
                }
            }

            // Run the validation check on any input/select change
            $('#newUserForm input, #newUserForm select').on('input change', toggleSubmitButton);

            // Trigger the initial state for the submit button
            toggleSubmitButton();
        });
    </script>
@endsection
