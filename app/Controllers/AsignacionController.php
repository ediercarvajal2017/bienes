<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Aviso;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Helpers\FechaMovimiento;
use App\Helpers\FiltrosBienes;
use App\Helpers\Paginador;
use App\Models\Asignacion;
use App\Models\Auditoria;
use App\Models\Bien;
use App\Models\Espacio;
use App\Models\Institucion;
use App\Models\Usuario;
use App\Services\CicloVidaBien;

final class AsignacionController
{
    private const POR_PAGINA_DEFECTO = 30;
    private const OPCIONES_POR_PAGINA = [10, 25, 50, 100, 0];

    public function index(): void
    {
        $institucionId = $this->institucionSeleccionada();

        $q = trim((string) ($_GET['q'] ?? ''));
        $termino = $q !== '' ? $q : null;
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $porPagina = (int) ($_GET['porPagina'] ?? self::POR_PAGINA_DEFECTO);
        if (!in_array($porPagina, self::OPCIONES_POR_PAGINA, true)) {
            $porPagina = self::POR_PAGINA_DEFECTO;
        }

        // Filtros para elegir los bienes: espacio, responsable y tipo de responsabilidad
        // (p. ej. "todos los individuales de Ana", para pasarlos a otra persona).
        $filtros = FiltrosBienes::desdeConsulta($_GET);
        $espacioFiltro = $institucionId !== null ? $filtros['espacio'] : null;
        $responsableFiltro = $institucionId !== null ? $filtros['responsable'] : null;
        $tipoFiltro = $filtros['tipo'];

        $total = $institucionId !== null ? Bien::contarOperables($institucionId, $termino, $espacioFiltro, $responsableFiltro, $tipoFiltro) : 0;

        View::layout('partials/layout', 'asignaciones/index', [
            'title' => 'Asignar bienes',
            'instituciones' => Auth::esSuperusuario() ? Institucion::listadoParaSelect(true) : [],
            'institucionId' => $institucionId,
            'espacios' => $institucionId !== null ? Espacio::listadoParaSelect($institucionId) : [],
            'personas' => $institucionId !== null ? Usuario::elegiblesACargo($institucionId) : [],

            'bienes' => $institucionId !== null ? Bien::operables($institucionId, $termino, $pagina, $porPagina, $espacioFiltro, $responsableFiltro, $tipoFiltro) : [],
            'q' => $q,
            'espacioFiltro' => $espacioFiltro,
            'responsableFiltro' => $responsableFiltro,
            'tipoFiltro' => $tipoFiltro,
            'responsablesFiltro' => $institucionId !== null ? Usuario::paraFiltroResponsable($institucionId) : [],
            'tipos' => FiltrosBienes::TIPOS,
            'pagina' => $pagina,
            'total' => $total,
            'totalPaginas' => Paginador::totalPaginas($total, $porPagina),
            'porPagina' => $porPagina,
            'opcionesPorPagina' => self::OPCIONES_POR_PAGINA,

            'viejo' => Session::pullOld(),
        ]);
    }

    public function guardar(): void
    {
        $request = new Request();

        $bienIds = array_unique(array_map('intval', (array) $request->input('bienes', [])));
        // Responsabilidad: Grupal (espacio obligatorio) o Individual (persona obligatoria,
        // espacio opcional como "guardado en").
        $individual = $request->input('tipo_responsabilidad') === 'individual';
        $espacioIdRaw = (string) $request->input('espacio_id');
        $personaId = $individual ? (int) $request->input('persona_id') : 0;
        $fecha = (string) $request->input('fecha_asignacion');
        $observaciones = trim((string) $request->input('observaciones')) ?: null;
        $viejo = ['bienes' => $bienIds, 'tipo_responsabilidad' => $individual ? 'individual' : 'grupal', 'espacio_id' => $espacioIdRaw,
            'persona_id' => $personaId ?: '', 'fecha_asignacion' => $fecha, 'observaciones' => $observaciones];

        $this->verificarCsrf($request, $viejo);

        $institucionId = Auth::esSuperusuario() ? (int) $request->input('institucion_id') : (int) Auth::institucionId();
        $volverA = '/asignaciones' . (Auth::esSuperusuario() ? '?institucion=' . $institucionId : '');

        if (empty($bienIds)) {
            Aviso::enviar(\App\Helpers\Mensajes::seleccionaAlMenosUno('No se asignó ningún bien', 'bien', 'Asignar'));
            Session::flashOld($viejo);
            header('Location: ' . Url::to($volverA));
            exit;
        }

        $espacioId = (int) $espacioIdRaw;
        $titulo = 'No se asignó ningún bien';
        $error = null;
        if (($errorFecha = FechaMovimiento::error($fecha)) !== null) {
            $error = Aviso::error($titulo, $errorFecha, 'Elige una fecha entre el 01/01/2000 y hoy y vuelve a pulsar «Asignar».',
                null, ['fecha_asignacion' => 'Elige una fecha válida.']);
        } elseif ($individual && $personaId <= 0) {
            $error = Aviso::error($titulo, 'Elegiste responsabilidad Individual pero no la persona.',
                'Elige la persona responsable, o cambia a Grupal y elige el espacio.', null, ['persona_id' => 'Elige la persona.']);
        } elseif ($individual && Usuario::elegibleACargo($personaId, $institucionId) === null) {
            $error = Aviso::error($titulo, 'La persona elegida está inactiva o es de otra institución, así que no puede tener bienes a cargo.',
                'Elige otra persona de la lista.', null, ['persona_id' => 'Elige otra persona.']);
        } elseif (!$individual && $espacioId <= 0) {
            $error = Aviso::error($titulo, 'No elegiste el espacio.',
                'Elige el espacio donde quedan los bienes (responsabilidad Grupal) y vuelve a pulsar «Asignar».', null, ['espacio_id' => 'Elige el espacio.']);
        } elseif ($espacioId > 0 && !Espacio::perteneceYActivo($espacioId, (int) $institucionId)) {
            $error = Aviso::error($titulo, 'El espacio elegido está inactivo o es de otra institución.',
                'Elige otro espacio de la lista. Si falta, créalo o actívalo en Espacios.', null, ['espacio_id' => 'Elige otro espacio.']);
        }
        if ($error !== null) {
            Aviso::enviar($error);
            Session::flashOld($viejo);
            header('Location: ' . Url::to($volverA));
            exit;
        }

        $resultado = $this->asignarLote($bienIds, $institucionId, $espacioId ?: null, $personaId ?: null, $fecha, $observaciones);

        $omitidos = $resultado !== null ? $this->textoOmitidos($resultado, $individual) : '';
        $comoSeguir = $resultado !== null && $resultado['reintegrados'] > 0
            ? 'Para asignar un bien reintegrado, primero reactívalo desde su ficha («Reactivar»).'
            : 'Elige otro destino, o marca otros bienes.';
        if ($resultado === null) {
            Aviso::enviar(Aviso::error($titulo, 'Ocurrió un error al procesar la asignación y no se aplicó ningún cambio.',
                'Vuelve a pulsar «Asignar». Si se repite, avisa a soporte.'));
            Session::flashOld($viejo);
        } elseif ($resultado['asignados'] === 0) {
            Aviso::enviar(Aviso::error($titulo, $omitidos !== '' ? $omitidos : 'Ninguno de los bienes marcados se pudo asignar.', $comoSeguir));
            Session::flashOld($viejo);
        } else {
            $n = $resultado['asignados'];
            $hecho = ($n === 1 ? '1 bien asignado' : "{$n} bienes asignados") . ' a ' . $this->nombreDestino($espacioId, $personaId) . '.';
            Aviso::enviar($omitidos !== ''
                ? Aviso::advertencia($hecho, $omitidos, $comoSeguir)
                : Aviso::exito($hecho, 'Puedes comprobarlo en Bienes con el filtro Espacio o Responsable.'));
        }

        header('Location: ' . Url::to($volverA));
        exit;
    }

    /**
     * Asigna (o traslada, si ya tenía una asignación) cada bien del lote con las MISMAS
     * reglas que la ficha del bien (CicloVidaBien::asignarResponsabilidad): así un bien que
     * cambia de espacio o de persona deja su movimiento de traslado en el historial, y no se
     * repite la asignación si ya tenía esa misma responsabilidad. Todo en una única
     * transacción. Se omiten los bienes de otra institución, los dados de baja y los
     * reintegrados (un reintegrado solo vuelve a circular con "Reactivar").
     *
     * @return array{asignados: int, reintegrados: int, mismoEspacio: int, otros: int}|null  null si falló todo
     */
    private function asignarLote(array $bienIds, int $institucionId, ?int $espacioId, ?int $personaId, string $fecha, ?string $observaciones): ?array
    {
        $resultado = ['asignados' => 0, 'reintegrados' => 0, 'mismoEspacio' => 0, 'otros' => 0];

        try {
            return Database::transaccion(static function () use ($bienIds, $institucionId, $espacioId, $personaId, $fecha, $observaciones, $resultado): array {
                foreach ($bienIds as $bienId) {
                    $bien = Bien::find($bienId);
                    if (!$bien || $bien['estado'] === 'dado_de_baja' || (int) $bien['institucion_id'] !== $institucionId) {
                        $resultado['otros']++;
                        continue;
                    }
                    if ($bien['estado'] === 'reintegrado') {
                        $resultado['reintegrados']++;
                        continue;
                    }
                    $actual = Asignacion::activaDe((int) $bienId);
                    if ($actual && (int) ($actual['espacio_id'] ?? 0) === (int) $espacioId
                        && (int) ($actual['usuario_responsable_id'] ?? 0) === (int) $personaId) {
                        $resultado['mismoEspacio']++;
                        continue;
                    }

                    CicloVidaBien::asignarResponsabilidad((array) $bien, $espacioId, $personaId, $fecha, $observaciones);
                    $resultado['asignados']++;
                }

                return $resultado;
            });
        } catch (\Throwable $e) {
            ErrorHandler::reportar($e, __METHOD__);

            return null;
        }
    }

    /** Por qué algunos bienes marcados no se asignaron ('' si se asignaron todos). */
    private function textoOmitidos(array $resultado, bool $individual): string
    {
        $partes = [];
        if ($resultado['mismoEspacio'] > 0) {
            $partes[] = $individual
                ? "{$resultado['mismoEspacio']} ya estaban a cargo de esa persona."
                : "{$resultado['mismoEspacio']} ya estaban en ese espacio.";
        }
        if ($resultado['reintegrados'] > 0) {
            $partes[] = "{$resultado['reintegrados']} están reintegrados (ya salieron de la institución).";
        }
        if ($resultado['otros'] > 0) {
            $partes[] = "{$resultado['otros']} están dados de baja o son de otra institución.";
        }

        return $partes === [] ? '' : 'No se asignaron todos: ' . implode(' ', $partes);
    }

    /** "el espacio A-101 Aula 1" o "Ana Pérez", para el aviso de la asignación. */
    private function nombreDestino(int $espacioId, int $personaId): string
    {
        if ($personaId > 0) {
            $persona = Usuario::find($personaId);

            return $persona !== null ? trim($persona['nombres'] . ' ' . $persona['apellidos']) : 'la persona elegida';
        }
        $espacio = Espacio::find($espacioId);

        return $espacio !== null ? trim($espacio['codigo'] . ' ' . $espacio['nombre']) : 'el espacio elegido';
    }

    private function institucionSeleccionada(): ?int
    {
        if (!Auth::esSuperusuario()) {
            return Auth::institucionId();
        }

        $solicitada = $_GET['institucion'] ?? '';

        return $solicitada !== '' ? (int) $solicitada : null;
    }

    /**
     * $datosAConservar: si la sesión ya expiró (token CSRF inválido) antes de esta
     * verificación, se pierde igual la oportunidad de flashOld() más abajo en el método —
     * por eso el llamador ya construye $viejo ANTES de este chequeo y lo pasa aquí, para
     * que el usuario no pierda la selección y los datos solo porque el token expiró
     * mientras llenaba el formulario.
     */
    private function verificarCsrf(Request $request, array $datosAConservar = []): void
    {
        Csrf::verificarORedirigir($request, '/asignaciones', $datosAConservar);
    }
}
