<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Nota;

/**
 * Notas rápidas (panel del ícono de la barra superior, public/assets/js/notas.js). Todo
 * responde en JSON. Cualquier usuario con sesión tiene las suyas; la nota de otro usuario
 * responde 404, igual que una que no existe (no se revela si existe).
 */
final class NotaController
{
    public function index(): void
    {
        $this->responder([
            'notas' => array_map([$this, 'aJson'], Nota::delUsuario((int) Auth::id())),
            'maximo' => Nota::MAX_POR_USUARIO,
        ]);
    }

    public function crear(): void
    {
        $this->verificarCsrf();
        $usuarioId = (int) Auth::id();

        $texto = $this->textoValido() ?? '';
        $color = $this->colorValido() ?? Nota::COLORES[0];

        if (Nota::contar($usuarioId) >= Nota::MAX_POR_USUARIO) {
            $this->responder(['error' => 'Ya tienes ' . Nota::MAX_POR_USUARIO . ' notas, el máximo. Borra alguna para crear otra.'], 422);
        }

        $id = Nota::crear($usuarioId, $texto, $color);
        $this->responder(['nota' => $this->aJson((array) Nota::buscar($id, $usuarioId))], 201);
    }

    public function actualizar(string $id): void
    {
        $this->verificarCsrf();
        $usuarioId = (int) Auth::id();

        if (Nota::buscar((int) $id, $usuarioId) === null) {
            $this->responder(['error' => 'Esa nota ya no existe.'], 404);
        }

        Nota::actualizar((int) $id, $usuarioId, $this->textoValido(), $this->colorValido());
        $this->responder(['nota' => $this->aJson((array) Nota::buscar((int) $id, $usuarioId))]);
    }

    public function eliminar(string $id): void
    {
        $this->verificarCsrf();

        if (!Nota::eliminar((int) $id, (int) Auth::id())) {
            $this->responder(['error' => 'Esa nota ya no existe.'], 404);
        }

        $this->responder(['eliminada' => true]);
    }

    private function verificarCsrf(): void
    {
        if (!Csrf::verify((string) ($_POST['_csrf'] ?? ''))) {
            $this->responder(['error' => 'Tu sesión expiró. Recarga la página.'], 403);
        }
    }

    /** null si el campo no vino (no se cambia); responde 422 si no es válido. */
    private function textoValido(): ?string
    {
        if (!isset($_POST['texto'])) {
            return null;
        }
        $texto = $_POST['texto'];
        if (!is_string($texto) || !mb_check_encoding($texto, 'UTF-8')) {
            $this->responder(['error' => 'El texto de la nota no es válido.'], 422);
        }
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        if (mb_strlen($texto) > Nota::MAX_CARACTERES) {
            $this->responder(['error' => 'Una nota puede tener máximo ' . number_format(Nota::MAX_CARACTERES, 0, ',', '.') . ' caracteres.'], 422);
        }

        return $texto;
    }

    private function colorValido(): ?string
    {
        $color = $_POST['color'] ?? null;
        if ($color === null || $color === '') {
            return null;
        }
        if (!in_array($color, Nota::COLORES, true)) {
            $this->responder(['error' => 'Ese color no existe.'], 422);
        }

        return $color;
    }

    /** @return array{id: int, texto: string, color: string, actualizada: string} */
    private function aJson(array $nota): array
    {
        $marca = strtotime((string) ($nota['updated_at'] ?? ''));

        return [
            'id' => (int) ($nota['id'] ?? 0),
            'texto' => (string) ($nota['texto'] ?? ''),
            'color' => (string) ($nota['color'] ?? Nota::COLORES[0]),
            'actualizada' => $marca !== false ? date('d/m/Y H:i', $marca) : '',
        ];
    }

    private function responder(array $datos, int $estado = 200): never
    {
        http_response_code($estado);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
