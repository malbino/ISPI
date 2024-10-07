<?php

namespace App\Http\Controllers;

use App\Models\Movimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Almacene;
use App\Models\Recinto;
use App\Models\Persona;
use App\Models\Producto;
use App\Models\Detalle;
use App\Models\Cuota;
use App\Models\Contacto;
use App\Models\Empresa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Http\Requests\RegisterCuotasRequest;
use Exception;

class MovimientoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $movimientos = Movimiento::all();
        return view('pages.movimientos.vistaMovimientos', compact('movimientos')); // Mostrar lista de Movimientos
    }

    // Pagina para registro de movimiento
    public function register()
    {
        $almacenes = Almacene::all();
        $clientes = Persona::all();
        $recintos = Recinto::all();
        $productos = Producto::all();
        $proveedores = Persona::whereHas('contactos')->get();

        return view('pages.movimientos.registroMovimiento', compact('almacenes', 'clientes', 'recintos', 'productos', 'proveedores')); // Página para seleccionar el tipo de Movimiento
    }

    public function verificarStock(Request $request)
    {
        $id_almacen = $request->almacene;
        $productoNombre = $request->producto; // Get the product name
        $cantidad_solicitada = $request->cantidad; // Get the requested quantity

        // Find the product ID by name
        $producto = Producto::where('nombre', $productoNombre)->first();
        Log::info($request);

        if (!$producto) {
            return response()->json(['available' => false, 'productName' => $productoNombre, 'message' => 'Producto no encontrado.']);
        }

        $id_producto = $producto->id_producto; // Get the product ID

        // Sumar total de entradas
        $total_entradas = Detalle::join('movimientos', 'movimientos.id_movimiento', '=', 'detalles.id_movimiento')
            ->where('movimientos.id_almacen', $id_almacen)
            ->where('detalles.id_producto', $id_producto) // Use product ID for stock checking
            ->where('movimientos.tipo', 'ENTRADA')
            ->sum('detalles.cantidad');

        // Sumar total de salidas
        $total_salidas = Detalle::join('movimientos', 'movimientos.id_movimiento', '=', 'detalles.id_movimiento')
            ->where('movimientos.id_almacen', $id_almacen)
            ->where('detalles.id_producto', $id_producto) // Use product ID for stock checking
            ->where('movimientos.tipo', 'SALIDA')
            ->sum('detalles.cantidad');

        // Calcular existencias actuales
        $existencias_actuales = $total_entradas - $total_salidas;

        // Validar si hay suficiente stock
        if ($cantidad_solicitada > $existencias_actuales) {
            return response()->json([
                'available' => false,
                'productName' => $productoNombre,
                'cantidadDisponible' => $existencias_actuales // Include available quantity in the response
            ]);
        }

        return response()->json(['available' => true]); // Stock sufficient
    }

    // Verificar relación entre que la empresa del producto tiene una relación de contacto con el proveedor
    public function verificarContacto(Request $request)
    {
        $id_proveedor = $request->proveedor; // Get the proveedor ID
        $productoNombre = $request->producto; // Get the product name from the request

        // Find the product by its name
        $producto = Producto::where('nombre', $productoNombre)->first();
        // If product is not found, return a response indicating failure
        if (!$producto) {
            return response()->json(['contacto' => false, 'productName' => $productoNombre, 'message' => 'Producto no encontrado.']);
        }

        // Get the Empresa that owns the Producto
        $empresa = $producto->empresa; // Assuming the 'Producto' model has a 'empresa' relationship

        // If no Empresa is associated with the product, return an error
        if (!$empresa) {
            return response()->json(['contacto' => false, 'productName' => $productoNombre, 'message' => 'Empresa no encontrada para este producto.']);
        }

        // Get the Empresa ID
        $id_empresa = $empresa->id_empresa;

        // Check if there is a Contacto relation between the Proveedor (Persona) and the Empresa
        $existe_relacion = Contacto::where('id_persona', $id_proveedor)
            ->where('id_empresa', $id_empresa)
            ->exists();

        // Return a JSON response based on whether the Contacto relation exists
        if ($existe_relacion) {
            return response()->json([
                'available' => true,
                'productName' => $productoNombre,
                'empresaName' => $empresa->nombre,
                'message' => 'Relación de contacto encontrada.'
            ]);
        } else {
            return response()->json([
                'available' => false,
                'productName' => $productoNombre,
                'empresaName' => $empresa->nombre,
                'message' => 'No existe relación de contacto.'
            ]);
        }
    }

    public function checkCuotas($id)
    {
        // Verifica si existen cuotas relacionadas con el movimiento
        $cuotas = Cuota::where('id_movimiento', $id)->exists();

        if ($cuotas) {
            return response()->json(['exists' => true, 'message' => 'Este movimiento tiene cuotas asociadas. Sólo se pueden efectuar cambios en los detalles si no se tienen cuotas.'], 200);
        } else {
            return response()->json(['exists' => false], 200);
        }
    }

    public function store(Request $request)
    {
        Log::info(request()->all());

        // Validate the request
        $request->validate([
            'almacene' => 'required|exists:almacenes,id_almacen',
            'tipo' => 'required|in:ENTRADA,SALIDA',
            'proveedor' => 'required_if:tipo,ENTRADA|nullable|exists:personas,id_persona',
            'cliente' => 'nullable|exists:personas,id_persona',
            'recinto' => 'nullable|exists:recintos,id_recinto',
            'glose' => 'nullable|string',
        ]);

        // Decode the product data
        $productos = json_decode($request->productos[0], true); // Decode the first (and only) array element
        $cantidades = json_decode($request->cantidad[0], true);
        $precios = json_decode($request->precio[0], true);
        $subtotales = json_decode($request->subtotal[0], true);

        // Start a transaction
        DB::beginTransaction();

        try {
            // Generate codigo from carnet and current timestamp
            $carnet = Auth::user()->persona->carnet;
            $timestamp = now()->timestamp; // Get the current timestamp
            $codigo = $carnet . '_' . $timestamp; // Concatenate carnet and timestamp

            // Create the Movimiento
            $movimiento = Movimiento::create([
                'id_operador' => Auth::user()->id,
                'id_almacen' => $request->almacene,
                'tipo' => $request->tipo,
                'id_proveedor' => $request->tipo === 'ENTRADA' ? $request->proveedor : null,
                'id_cliente' => $request->tipo === 'SALIDA' ? $request->cliente : null,
                'id_recinto' => $request->tipo === 'SALIDA' ? $request->recinto : null,
                'glose' => $request->glose,
                'codigo' => $codigo, // Include the codigo here
            ]);

            // Prepare to create DetalleMovimiento entries
            $detalles = [];
            foreach ($productos as $index => $productoNombre) {
                // Look up the product ID based on the product name
                $producto = Producto::where('nombre', $productoNombre)->first();

                if (!$producto) {
                    // Handle the case where the product doesn't exist
                    Log::error('Product not found: ' . $productoNombre);
                    DB::rollBack();
                    return back()->withErrors(['error' => 'Product not found: ' . $productoNombre]);
                }

                // Group entries if the same product appears more than once
                if (isset($detalles[$producto->id_producto])) {
                    // Update existing detalle with new quantities and totals
                    $detalles[$producto->id_producto]['cantidad'] += $cantidades[$index];
                    $detalles[$producto->id_producto]['total'] += $subtotales[$index];
                } else {
                    // Create a new entry for the detalle
                    $detalles[$producto->id_producto] = [
                        'id_movimiento' => $movimiento->id_movimiento,
                        'id_producto' => $producto->id_producto,
                        'cantidad' => $cantidades[$index],
                        'precio' => $precios[$index],
                        'total' => $subtotales[$index],
                    ];
                }
            }

            // Insert all DetalleMovimiento entries at once
            foreach ($detalles as $detalle) {
                Detalle::create($detalle);
            }

            // Commit the transaction
            DB::commit();

            // Redirect based on the tipo of the movimiento
            if ($movimiento->tipo === 'SALIDA') {
                return $this->asignarCuotas($movimiento->id_movimiento);
            }

            // If tipo is not SALIDA, redirect to the normal view
            return redirect()->route('movimientos.vista')->with('success', 'Movimiento registrado satisfactoriamente en el código: ' . $movimiento->codigo);
        } catch (\Exception $e) {
            // Rollback the transaction if something goes wrong
            DB::rollBack();
            Log::error('Error registering movimiento: ' . $e->getMessage());
            return back()->withErrors(['error' => 'There was a problem registering the movimiento.']);
        }
    }

    public function asignarCuotas($id_movimiento)
    {

        // Retrieve the Movimiento details based on the ID
        $movimiento = Movimiento::findOrFail($id_movimiento);
        $detalles = $movimiento->detalles; // Assuming the relationship is defined
        $clientes = Persona::all();
        // dd($movimiento);
        // Calculate the total amount from Detalles
        $total = $detalles->sum('total');

        // Return the view with the necessary data
        return view('pages.movimientos.cuotas.asignarCuotas', [
            'movimiento' => $movimiento,
            'total' => $total,
            'clientes' => $clientes,
        ]);
    }

    public function storeCuotas(RegisterCuotasRequest $request)
    {
        // Retrieve the Movimiento to which the cuotas will be related
        $movimiento = Movimiento::findOrFail($request->id_movimiento);

        // Check if there are existing cuotas for this movimiento
        if ($movimiento->cuotas()->exists()) {
            return redirect()->route('movimientos.edit', $movimiento->id_movimiento)
                ->withErrors(['error' => 'Ya existen cuotas asignadas para este movimiento.']);
        }

        // Retrieve detalles related to the movimiento
        $detalles = $movimiento->detalles; // Assuming the relationship is defined

        // Calculate the total amount from Detalles
        $total = $detalles->sum('total');
        $codigoBase = 'CT0-' . now()->timestamp;

        if ($request->tipo_pago === 'CONTADO') {
            // Handle CONTADO payment type
            $descuento = $request->descuento ?? 0;
            $montoPagar = $total - $descuento;

            // Create the cuota for CONTADO
            Cuota::create([
                'numero' => 1,
                'codigo' => $codigoBase,
                'concepto' => 'Pago único',
                'fecha_venc' => now(),
                'monto_pagar' => $montoPagar,
                'monto_pagado' => $montoPagar,
                'monto_adeudado' => 0,
                'condicion' => 'PAGADA',
                'id_movimiento' => $movimiento->id_movimiento,
            ]);
        } elseif ($request->tipo_pago === 'CRÉDITO') {
            // Handle CRÉDITO payment type
            $aditivo = $request->aditivo ?? 0;
            $cantidadCuotas = $request->cantidad_cuotas;
            $primerPago = $request->primer_pago;
            $totalConAditivo = $total + $aditivo;
            $montoPagar = ceil($totalConAditivo / $cantidadCuotas); // Calculate amount per cuota
            $nuevoCliente = $request->id_cliente;
            Movimiento::where('id_movimiento', $request->id_movimiento)->update(['id_cliente' => $nuevoCliente]);

            // Initialize remaining payment
            $montoPagado = $primerPago;

            for ($i = 1; $i <= $cantidadCuotas; $i++) {
                $montoAdeudado = $montoPagar; // Initial amount due for this cuota
                $estado = 'PENDIENTE'; // Default condition

                // If there is enough payment to cover this cuota
                if ($montoPagado >= $montoPagar) {
                    $montoPagado -= $montoPagar; // Deduct the cuota amount from primer_pago
                    $montoPagadoCuota = $montoPagar; // Full cuota is paid
                    $montoAdeudado = 0; // No amount due
                    $estado = 'PAGADA'; // Mark cuota as paid
                } else {
                    // Not enough to cover the full cuota
                    $montoPagadoCuota = $montoPagado; // Use the remaining amount for this cuota
                    $montoAdeudado = $montoPagar - $montoPagado; // Remaining amount after payment
                    $montoPagado = 0; // All remaining payment is used
                }
                $codigoBase = 'CT' . $i . '-' . now()->timestamp;
                // Create each cuota
                Cuota::create([
                    'numero' => $i,
                    'codigo' => $codigoBase,
                    'concepto' => 'Cuota #' . $i,
                    'fecha_venc' => now()->addMonths($i - 1),
                    'monto_pagar' => $montoPagar,
                    'monto_pagado' => $montoPagadoCuota,
                    'monto_adeudado' => $montoAdeudado,
                    'condicion' => $estado,
                    'id_movimiento' => $movimiento->id_movimiento,
                ]);

                // If the cuota was fully paid, there's no need to continue
                if ($estado === 'PAGADA' && $montoAdeudado === 0) {
                    continue; // Move to the next cuota
                }
            }
        }

        return redirect()->route('movimientos.edit', $movimiento->id_movimiento)
            ->with('success', 'Cuotas asignadas exitosamente en el movimiento: ' . $movimiento->codigo);
    }


    //Mostrar formulario para editar Movimiento
    public function edit($id_movimiento)
    {
        $movimiento = Movimiento::findOrFail($id_movimiento);
        $clientes = Persona::all();
        $proveedores = Persona::has('contactos')->get();
        $recintos = Recinto::all();
        $detalles = Detalle::where('id_movimiento', $id_movimiento)->get();
        $cuotas = Cuota::where('id_movimiento', $id_movimiento)->get();

        return view('pages.movimientos.editarMovimiento', compact('movimiento', 'clientes', 'proveedores', 'recintos', 'detalles', 'cuotas'));
    }

    public function editDetalles($id_movimiento)
    {
        // Fetch the Movimiento by ID
        $movimiento = Movimiento::find($id_movimiento);
        // Check if the movimiento exists
        if (!$movimiento) {
            return redirect()->back()->with('error', 'Movimiento no encontrado.');
        }

        // Fetch the detalles based on the movimiento
        $detalles = Detalle::where('id_movimiento', $id_movimiento)->get();
        $almacen = $movimiento->id_almacen;
        $proveedor = $movimiento->id_proveedor;
        $productos = Producto::all();
        // Check if the type is ENTRADA or SALIDA and redirect accordingly
        return view('pages.movimientos.editarDetallesMovimiento', compact('movimiento', 'proveedor', 'detalles', 'almacen', 'productos'));
    }

    public function guardarDetalles(Request $request, $id_movimiento)
    {
        // Fetch the movimiento by ID
        $movimiento = Movimiento::findOrFail($id_movimiento);
        if (empty($request->productos)) {
            return redirect()->route('movimientos.edit', $id_movimiento)
                ->with('success', 'Detalles vacío');
        }
        // Validate request data
        $request->validate([
            'productos.*.id_producto' => 'required|exists:productos,id_producto',
            'productos.*.cantidad' => 'required|integer|min:1',
        ]);

        // Handle Entrada type
        if ($movimiento->tipo === 'ENTRADA') {
            foreach ($request->productos as $producto) {
                $id_producto = $producto['id_producto'];
                $proveedor_id = $movimiento->id_proveedor; // Assuming you have the supplier ID in movimiento
                $productoInstance = Producto::find($id_producto);
                $empresaInstance = Empresa::find($productoInstance->id_empresa);
                $proveedorInstance = Persona::find($proveedor_id);
                // Check if there's a contact relation with the supplier
                $hasContacto = Contacto::where('id_empresa', $empresaInstance->id_empresa)
                    ->where('id_persona', $proveedorInstance->id_persona)
                    ->exists();
                if (!$hasContacto) {
                    return redirect()->back()->withErrors(['error' => "No hay relación de contacto entre la empresa: {$empresaInstance->nombre} con el proveedor: [{$proveedorInstance->carnet}] {$proveedorInstance->papellido} para el producto: {$productoInstance->nombre}."])->withInput();
                }
            }
        }

        // Handle Salida type
        if ($movimiento->tipo === 'SALIDA') {
            foreach ($request->productos as $producto) {
                $id_producto = $producto['id_producto'];
                $cantidad_salida = $producto['cantidad'];
                $id_almacen = $movimiento->id_almacen; // Assuming you have the warehouse ID in movimiento
                $productoInstance = Producto::find($id_producto);
                $almaceneInstance = Almacene::find($id_almacen);
                // Calculate current stock
                $total_entradas = Detalle::join('movimientos', 'movimientos.id_movimiento', '=', 'detalles.id_movimiento')
                    ->where('movimientos.id_almacen', $id_almacen)
                    ->where('detalles.id_producto', $id_producto)
                    ->where('movimientos.tipo', 'ENTRADA')
                    ->sum('detalles.cantidad');

                $total_salidas = Detalle::join('movimientos', 'movimientos.id_movimiento', '=', 'detalles.id_movimiento')
                    ->where('movimientos.id_almacen', $id_almacen)
                    ->where('detalles.id_producto', $id_producto)
                    ->where('movimientos.tipo', 'SALIDA')
                    ->sum('detalles.cantidad');

                $existencias_actuales = $total_entradas - $total_salidas;

                if ($existencias_actuales < $cantidad_salida) {
                    return redirect()->back()->withErrors(['error' => "No hay suficiente stock del producto {$productoInstance->nombre}, en el almacen: {$almaceneInstance->nombre}. Solo hay {$existencias_actuales} disponibles."])->withInput();
                }
            }
        }

        // If validations pass, save details
        foreach ($request->productos as $producto) {
            $productoInstance = Producto::find($id_producto);
            // Verifica si 'id_detalle' existe
            if (isset($producto['id_detalle'])) {
                // Si 'id_detalle' existe, actualiza el detalle existente
                Detalle::updateOrCreate(
                    ['id_detalle' => $producto['id_detalle']],
                    [
                        'id_producto' => $producto['id_producto'],
                        'cantidad' => $producto['cantidad'],
                        'precio' => $productoInstance->precio,
                        'total' => $producto['cantidad'] * $productoInstance->precio,
                        'id_movimiento' => $id_movimiento,
                    ]
                );
            } else {
                // Si no existe 'id_detalle', crea un nuevo detalle
                Detalle::create([
                    'id_producto' => $producto['id_producto'],
                    'cantidad' => $producto['cantidad'],
                    'precio' => $productoInstance->precio,
                    'total' => $producto['cantidad'] * $productoInstance->precio,
                    'id_movimiento' => $id_movimiento,
                ]);
            }
        }

        return redirect()->route('movimientos.edit', $id_movimiento)
            ->with('success', 'Detalles guardados correctamente.');
    }






    public function eliminarDetalle($id_movimiento, $id_detalle)
    {
        $detalle = Detalle::findOrFail($id_detalle);
        $detalle->delete();

        return response()->json(['success' => 'Detalle eliminado correctamente']);
    }


    // Función para actualizar un Movimiento
    public function update(Request $request, $id_movimiento)
    {
        // Validación de los datos del formulario
        $request->validate([
            'glose' => 'nullable|string|max:255',
            'cliente' => 'nullable|exists:personas,id_persona', // Para SALIDA
            'recinto' => 'nullable|exists:recintos,id_recinto', // Para SALIDA
            'proveedor' => 'nullable|exists:personas,id_persona', // Para ENTRADA
        ]);

        // Buscar el movimiento a actualizar
        $movimiento = Movimiento::findOrFail($id_movimiento);

        // Actualizar los campos comunes (Glose)
        $movimiento->glose = $request->input('glose');

        // Actualización condicional según el tipo de movimiento
        if ($movimiento->tipo == 'SALIDA') {
            // Para los movimientos de tipo 'SALIDA'
            $movimiento->id_cliente = $request->input('cliente'); // Cliente opcional
            $movimiento->id_recinto = $request->input('recinto'); // Recinto opcional
        } elseif ($movimiento->tipo == 'ENTRADA') {
            // Para los movimientos de tipo 'ENTRADA'
            $movimiento->id_proveedor = $request->input('proveedor'); // Proveedor es requerido
        }

        // Guardar los cambios
        $movimiento->save();

        // Redirigir con un mensaje de éxito
        return redirect()->route('movimientos.edit', $movimiento->id_movimiento)
            ->with('success', 'Se actualizaron los campos del movimiento: ' . $movimiento->codigo);
    }


    // Función para eliminar un Movimiento
    public function destroy($id_movimiento)
    {
        $movimiento = Movimiento::findOrFail($id_movimiento);
        $movimiento->delete();

        return redirect()->route('movimientos.vista')->with('success', 'Movimiento eliminado exitosamente.');
    }

    // Función para eliminar las Cuotas pertenecientes a un movimiento
    public function cuotasDestroy($id_movimiento)
    {
        $movimiento = Movimiento::findOrFail($id_movimiento);

        try {
            Cuota::where('id_movimiento', $id_movimiento)->delete();

            return redirect()->route('movimientos.edit', $movimiento->id_movimiento)
                ->with('success', 'Cuotas eliminadas exitosamente en el movimiento: ' . $movimiento->codigo);
        } catch (Exception $e) {
            return redirect()->route('movimientos.edit', $movimiento->id_movimiento)
                ->with('error', 'No se pudieron eliminar las cuotas del movimiento: ' . $movimiento->codigo);
        }
    }

    //Función para pagar una cuota
    public function payCuota(Request $request, $id_cuota)
    {
        $cuota = Cuota::findOrFail($id_cuota);
        $cuota->condicion = 'PAGADA';
        $cuota->monto_pagado = $cuota->monto_pagar;
        $cuota->monto_adeudado = 0;
        $cuota->save();
        $movimiento = Movimiento::findOrFail($cuota->id_movimiento);

        return redirect()->route('movimientos.edit', $cuota->id_movimiento)
            ->with('success', 'Cuota ' . $cuota->numero . ' pagada exitosamente en el movimiento: ' . $movimiento->codigo);
    }

    //Función para resetear una cuota
    public function resetCuota(Request $request, $id_cuota)
    {
        $cuota = Cuota::findOrFail($id_cuota);
        $cuota->condicion = 'PENDIENTE';
        $cuota->monto_pagado = 0;
        $cuota->monto_adeudado =  $cuota->monto_pagar;
        $cuota->save();
        $movimiento = Movimiento::findOrFail($cuota->id_movimiento);
        return redirect()->route('movimientos.edit', $cuota->id_movimiento)
            ->with('success', 'Cuota ' . $cuota->numero . ' reseteada exitosamente en el movimiento: ' . $movimiento->codigo);
    }
}