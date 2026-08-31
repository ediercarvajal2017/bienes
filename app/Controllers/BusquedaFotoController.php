<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\View;
use App\Models\BienFotoVector;

/**
 * Búsqueda de bienes por foto: el reconocimiento visual ocurre en el navegador (modelo
 * MobileNet vía TensorFlow.js, cargado por CDN igual que el lector de QR en /escanear).
 * Este controlador nunca recibe ni procesa imágenes -- solo guarda y compara los
 * vectores de números que el navegador ya calculó. Ver BienFotoVector para el porqué de
 * mantener esta lógica fuera de BienController/Bien.
 */
final class BusquedaFotoController
{
    private const MAX_DIMENSIONES = 4096;

    public function index(): void
    {
        $institucionId = Auth::esSuperusuario() ? Auth::filtroInstitucionId() : Auth::institucionId();

        View::layout('partials/layout', 'bienes/buscar_por_foto', [
            'title' => 'Buscar por foto',
            'sinInstitucion' => $institucionId === null,
        ]);
    }

    /**
     * Hasta 10 bienes con foto pero sin huella calculada, para que el navegador los
     * procese en segundo plano mientras el usuario está en esta pantalla.
     */
    public function pendientes(): void
    {
        header('Content-Type: application/json');

        $institucionId = Auth::esSuperusuario() ? Auth::filtroInstitucionId() : Auth::institucionId();
        if ($institucionId === null) {
            echo json_encode(['pendientes' => [], 'total' => 0]);
            exit;
        }

        $pendientes = array_map(
            static fn (array $b) => ['id' => (int) $b['id'], 'foto_path' => $b['foto_path']],
            BienFotoVector::pendientesDeIndexar($institucionId)
        );

        echo json_encode([
            'pendientes' => $pendientes,
            'total' => BienFotoVector::contarPendientes($institucionId),
        ]);
        exit;
    }

    /**
     * El navegador ya calculó la huella de la foto de un bien (durante el indexado en
     * segundo plano) y la envía para guardarla.
     */
    public function guardarVector(): void
    {
        header('Content-Type: application/json');

        $request = new Request();

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            http_response_code(403);
            echo json_encode(['error' => 'Tu sesión expiró, recarga la página.']);
            exit;
        }

        $institucionId = Auth::esSuperusuario() ? Auth::filtroInstitucionId() : Auth::institucionId();
        $id = (int) $request->input('id');
        $vector = $this->decodificarVector((string) $request->input('vector', ''));

        if ($institucionId === null || $id <= 0 || $vector === null) {
            http_response_code(422);
            echo json_encode(['error' => 'Datos inválidos.']);
            exit;
        }

        $guardado = BienFotoVector::guardarVector($id, $institucionId, $vector);

        echo json_encode(['guardado' => $guardado]);
        exit;
    }

    /**
     * El navegador ya calculó la huella de la foto de consulta (la que el usuario acaba
     * de tomar/subir, que no pertenece a ningún bien) y pide los bienes más parecidos.
     */
    public function buscar(): void
    {
        header('Content-Type: application/json');

        $request = new Request();

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            http_response_code(403);
            echo json_encode(['error' => 'Tu sesión expiró, recarga la página.']);
            exit;
        }

        $institucionId = Auth::esSuperusuario() ? Auth::filtroInstitucionId() : Auth::institucionId();
        $vector = $this->decodificarVector((string) $request->input('vector', ''));

        if ($institucionId === null) {
            http_response_code(422);
            echo json_encode(['error' => 'Selecciona una institución en el filtro del encabezado para buscar.']);
            exit;
        }

        if ($vector === null) {
            http_response_code(422);
            echo json_encode(['error' => 'No se pudo procesar la foto.']);
            exit;
        }

        $resultados = array_map(
            static function (array $b): array {
                $b['id'] = (int) $b['id'];
                $b['similitud'] = round((float) $b['similitud'], 4);

                return $b;
            },
            BienFotoVector::buscarSimilares($institucionId, $vector)
        );

        echo json_encode(['resultados' => $resultados]);
        exit;
    }

    /**
     * El vector llega como JSON dentro de un campo de formulario normal (no como cuerpo
     * JSON de la petición -- así no hace falta tocar Request::input(), que solo lee
     * $_POST/$_GET). Se valida tamaño y que todos los elementos sean numéricos antes de
     * aceptarlo, para no guardar basura ni acceder a un índice fuera de rango.
     *
     * @return float[]|null
     */
    private function decodificarVector(string $json): ?array
    {
        if ($json === '' || strlen($json) > 200_000) {
            return null;
        }

        $datos = json_decode($json, true);
        if (!is_array($datos) || $datos === [] || count($datos) > self::MAX_DIMENSIONES) {
            return null;
        }

        $vector = [];
        foreach ($datos as $n) {
            if (!is_int($n) && !is_float($n)) {
                return null;
            }
            $vector[] = (float) $n;
        }

        return $vector;
    }
}
