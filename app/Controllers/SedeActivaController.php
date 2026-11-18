<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Aviso;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Url;
use App\Core\View;
use App\Models\Institucion;

final class SedeActivaController
{
    public function cambiar(): void
    {
        $request = new Request();
        $volverA = $this->volverSeguro((string) $request->input('volver', '/dashboard'));

        if (!Csrf::verify((string) $request->input('_csrf'))) {
            Aviso::enviar(\App\Helpers\Mensajes::sesionVencida(false));
            header('Location: ' . Url::to($volverA));
            exit;
        }

        if (Auth::rol() !== 'rector') {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }

        $destino = (int) $request->input('institucion_id');
        $familia = Institucion::familiaDe((int) Auth::institucionId());

        $sedeElegida = null;
        foreach ($familia as $sede) {
            if ((int) $sede['id'] === $destino) {
                $sedeElegida = $sede;
                break;
            }
        }

        if ($sedeElegida === null) {
            Aviso::enviar(Aviso::error('No se cambió de sede', 'La sede elegida no pertenece a tu institución.',
                'Elige una de las sedes de la lista de arriba.'));
            header('Location: ' . Url::to($volverA));
            exit;
        }

        Auth::cambiarSedeActiva($destino);
        Aviso::enviar(Aviso::exito('Ahora trabajas en la sede ' . $sedeElegida['nombre'] . '.',
            'Los listados y lo que registres desde ahora son de esta sede.'));
        header('Location: ' . Url::to($volverA));
        exit;
    }

    private function volverSeguro(string $ruta): string
    {
        return (str_starts_with($ruta, '/') && !str_starts_with($ruta, '//')) ? $ruta : '/dashboard';
    }
}
