<?php
declare(strict_types=1);

namespace GemMotors\Controllers;

use GemMotors\Config\App;
use GemMotors\Models\OrdenTrabajo;
use GemMotors\Models\Cliente;
use GemMotors\Models\Presupuesto;

class SeguimientoController
{
    /**
     * Rastrear orden de trabajo usando el código de seguimiento del cliente
     * GET /api/seguimiento/{codigo}
     */
    public static function track(string $codigo): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            App::jsonResponse(false, null, 'Método no permitido', 405);
            return;
        }

        // Buscar cliente por código de seguimiento
        $db = \GemMotors\Config\Database::getInstance();
        $stmt = $db->prepare('SELECT id FROM clientes WHERE codigo_seguimiento = :codigo');
        $stmt->execute(['codigo' => $codigo]);
        $clienteId = $stmt->fetchColumn();

        if (!$clienteId) {
            App::jsonResponse(false, null, 'Código de seguimiento no encontrado', 404);
            return;
        }

        $cliente = Cliente::find((int)$clienteId);

        // Buscar la orden más reciente o activa
        $ordenes = OrdenTrabajo::findAll(['cliente_id' => $cliente->id]);
        if (empty($ordenes)) {
            App::jsonResponse(false, null, 'No hay órdenes de trabajo para este código', 404);
            return;
        }
        
        $orden = $ordenes[0]; // Como findAll ordena por created_at DESC, la primera es la más reciente

        $evidencias = $orden->getEvidencias();
        $vehiculo = $orden->getVehiculo();
        $diagnosticos = $orden->getDiagnosticos();
        $historial = $orden->getHistorialEstados();
        $presupuesto = $orden->getPresupuesto();

        $evidenciasData = array_map(function ($ev) {
            return [
                'id' => $ev->id,
                'tipo' => $ev->tipo === 'foto' ? 'imagen' : $ev->tipo,
                'url' => $ev->url_cloudinary,
                'etiqueta' => $ev->etiqueta,
                'descripcion' => $ev->descripcion,
                'created_at' => $ev->created_at
            ];
        }, $evidencias);

        $diagnosticosData = array_map(function ($diag) {
            return [
                'observaciones' => $diag->observaciones,
                'codigos_falla' => $diag->codigos_falla,
                'created_at' => $diag->created_at
            ];
        }, $diagnosticos);

        App::jsonResponse(true, [
            'cliente' => [
                'nombre' => $cliente->nombre,
                'telefono' => $cliente->telefono
            ],
            'orden' => [
                'id' => $orden->id,
                'numero_ot' => $orden->numero_ot,
                'estado' => $orden->estado,
                'descripcion_problema' => $orden->descripcion_problema,
                'created_at' => $orden->created_at,
                'fecha_cierre' => $orden->fecha_cierre,
                'vehiculo' => $vehiculo ? [
                    'marca' => $vehiculo->marca,
                    'modelo' => $vehiculo->modelo,
                    'placa' => $vehiculo->placa,
                    'color' => $vehiculo->color
                ] : null
            ],
            'evidencias' => $evidenciasData,
            'diagnosticos' => $diagnosticosData,
            'historial_estados' => $historial,
            'presupuesto' => $presupuesto ? [
                'id' => $presupuesto->id,
                'total' => (float)$presupuesto->total,
                'estado' => $presupuesto->estado,
                'motivo_rechazo' => $presupuesto->motivo_rechazo,
                'fecha_emision' => $presupuesto->fecha_emision
            ] : null
        ], 'Información de seguimiento obtenida exitosamente');
    }

    /**
     * Responder a un presupuesto desde el portal público
     * PUT /api/seguimiento/{codigo}/presupuestos/{id}/respuesta
     */
    public static function responderPresupuesto(string $codigo, int $presupuestoId): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            App::jsonResponse(false, null, 'Método no permitido', 405);
            return;
        }

        // Validar el código de seguimiento primero
        $db = \GemMotors\Config\Database::getInstance();
        $stmt = $db->prepare('SELECT id FROM clientes WHERE codigo_seguimiento = :codigo');
        $stmt->execute(['codigo' => $codigo]);
        $clienteId = $stmt->fetchColumn();

        if (!$clienteId) {
            App::jsonResponse(false, null, 'Código de seguimiento no encontrado', 404);
            return;
        }

        $presupuesto = Presupuesto::find($presupuestoId);
        if ($presupuesto === null) {
            App::jsonResponse(false, null, 'Presupuesto no encontrado', 404);
            return;
        }

        // Validar que el presupuesto pertenezca al cliente de este código
        $orden = OrdenTrabajo::find($presupuesto->orden_id);
        if ($orden->cliente_id !== (int)$clienteId) {
            App::jsonResponse(false, null, 'El presupuesto no pertenece a este código de seguimiento', 403);
            return;
        }

        // Extraer datos
        $input = json_decode(file_get_contents('php://input'), true);
        $respuesta = $input['respuesta'] ?? ''; // 'aprobar' o 'rechazar'
        $motivoRechazo = $input['motivo_rechazo'] ?? null;

        if (!in_array($respuesta, ['aprobar', 'rechazar'], true)) {
            App::jsonResponse(false, null, 'Respuesta inválida', 400);
            return;
        }

        try {
            if ($respuesta === 'aprobar') {
                $presupuesto->aprobar();
                // Notificar al mecánico
                \GemMotors\Services\NotificacionService::notificarCambioEstado($orden->id, 'presupuesto_aprobado');
            } else {
                if (empty($motivoRechazo)) {
                    App::jsonResponse(false, null, 'Se requiere un motivo para rechazar', 400);
                    return;
                }
                $presupuesto->rechazar($motivoRechazo);
            }

            App::jsonResponse(true, [
                'estado' => $presupuesto->estado,
                'fecha_respuesta' => $presupuesto->fecha_respuesta
            ], "Presupuesto {$respuesta}do exitosamente");
        } catch (\Exception $e) {
            App::jsonResponse(false, null, $e->getMessage(), 400);
        }
    }
}